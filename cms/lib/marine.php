<?php
/**
 * ════════════════════════════════════════════════════════════════════════════
 *  lib/marine.php — Mareas (NOAA) + estado del mar (Stormglass) + sol (local)
 * ════════════════════════════════════════════════════════════════════════════
 *
 *  Funciones compartidas por el cron (cron/fetch_marine.php) y por el feed
 *  (api/kiosk.php, como respaldo). Stormglass solo se llama desde el cron para
 *  no gastar el límite gratuito (10/día) ni exponer la API key al navegador.
 * ════════════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/ics.php';   // gtz(), MES_ES_SHORT

/** GET simple con cURL. Devuelve el cuerpo o null. */
function httpGet(string $url, array $headers = []): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_USERAGENT      => 'KioskoUSFQ/2.0 (+https://api.julianmaya.com)',
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return ($code >= 200 && $code < 300 && is_string($body)) ? $body : null;
}

// ════════════════════════════════════════════════════════════════════════════
//  Mareas — NOAA (gratis, sin key)
// ════════════════════════════════════════════════════════════════════════════

function fetchNoaaTides(): ?array {
    $dateStr = (new DateTimeImmutable('now', gtz()))->format('Ymd');
    $url = NOAA_ENDPOINT . '?' . http_build_query([
        'begin_date' => $dateStr, 'end_date' => $dateStr,
        'station'    => NOAA_STATION, 'product' => 'predictions',
        'datum'      => 'MLLW', 'time_zone' => 'lst',
        'interval'   => 'hilo', 'units' => 'metric', 'format' => 'json',
    ]);
    $json = httpGet($url);
    if (!$json) return null;
    $preds = json_decode($json, true)['predictions'] ?? [];
    if (!$preds) return null;

    $now     = new DateTimeImmutable('now', gtz());
    $nextIdx = -1;
    foreach ($preds as $i => $p) {
        $t = DateTimeImmutable::createFromFormat('Y-m-d H:i', $p['t'], gtz());
        if ($nextIdx < 0 && $t && $t > $now) { $nextIdx = $i; break; }
    }
    $state = 'Estable';
    if ($nextIdx >= 0) $state = $preds[$nextIdx]['type'] === 'H' ? 'Subiendo' : 'Bajando';

    $points = [];
    foreach ($preds as $i => $p) {
        $points[] = [
            'kind' => $p['type'] === 'H' ? 'Pleamar' : 'Bajamar',
            'time' => substr($p['t'], 11, 5),                 // HH:MM
            'h'    => number_format((float)$p['v'], 2),       // metros
            'next' => $i === $nextIdx,
        ];
    }
    return [
        'state'   => $state,
        'station' => 'NOAA · Estación San Cristóbal #' . NOAA_STATION,
        'points'  => $points,
    ];
}

// ════════════════════════════════════════════════════════════════════════════
//  Estado del mar — Stormglass
// ════════════════════════════════════════════════════════════════════════════

/** Stormglass devuelve cada parámetro como {fuente: valor}; toma 'sg' o la 1ª. */
function sgPick($field) {
    if (!is_array($field)) return $field;
    if (isset($field['sg'])) return $field['sg'];
    $v = reset($field);
    return $v === false ? null : $v;
}

/** Escala tipo Douglas a partir de la altura de ola significativa. */
function seaStateLabel(?float $wave): string {
    if ($wave === null) return '—';
    if ($wave < 0.5)  return 'Tranquilo';
    if ($wave < 1.25) return 'Ligero';
    if ($wave < 2.5)  return 'Moderado';
    if ($wave < 4.0)  return 'Agitado';
    return 'Fuerte';
}

function windCompass(?float $deg): ?string {
    if ($deg === null) return null;
    $dirs = ['N','NE','E','SE','S','SO','O','NO'];
    return $dirs[((int)round($deg / 45)) % 8];
}

/**
 * Keys de Stormglass con failover: se leen de data/settings.json (editable
 * desde /admin/settings.php); si no hay, cae a la constante de config.php.
 */
if (!defined('SETTINGS_FILE')) define('SETTINGS_FILE', DATA_DIR . '/settings.json');

function getStormglassKeys(): array {
    $s = loadJson(SETTINGS_FILE);
    $keys = array_values(array_filter(array_map('trim', $s['stormglass_keys'] ?? [])));
    if (!$keys && defined('STORMGLASS_KEY') && STORMGLASS_KEY !== '__CAMBIAR_EN_SERVIDOR__') {
        $keys = [STORMGLASS_KEY];
    }
    return $keys;
}

function fetchStormglass(): ?array {
    $now = time();
    $url = STORMGLASS_ENDPOINT . '?' . http_build_query([
        'lat'    => LAT, 'lng' => LNG,
        'params' => 'waveHeight,swellHeight,swellPeriod,swellDirection,waterTemperature,airTemperature,'
                  . 'windSpeed,windDirection,gust,currentSpeed,currentDirection,visibility,cloudCover,precipitation',
        'start'  => $now, 'end' => $now,
    ]);

    // Failover: intenta cada key en orden (quota agotada, key inválida o error de red)
    $json = null;
    foreach (getStormglassKeys() as $key) {
        $json = httpGet($url, ['Authorization: ' . $key]);
        if ($json) break;
    }
    if (!$json) return null;
    $hours = json_decode($json, true)['hours'] ?? [];
    if (!$hours) return null;
    $h = $hours[0];

    $wave    = sgPick($h['waveHeight']       ?? null);
    $swellH  = sgPick($h['swellHeight']      ?? null);
    $swellP  = sgPick($h['swellPeriod']      ?? null);
    $swellD  = sgPick($h['swellDirection']   ?? null);
    $water   = sgPick($h['waterTemperature'] ?? null);
    $air     = sgPick($h['airTemperature']   ?? null);
    $windMs  = sgPick($h['windSpeed']        ?? null);
    $windDir = sgPick($h['windDirection']    ?? null);
    $gustMs  = sgPick($h['gust']             ?? null);
    $curMs   = sgPick($h['currentSpeed']     ?? null);
    $curDir  = sgPick($h['currentDirection'] ?? null);
    $visKm   = sgPick($h['visibility']       ?? null);
    $clouds  = sgPick($h['cloudCover']       ?? null);
    $precip  = sgPick($h['precipitation']    ?? null);

    return [
        'seaState'    => seaStateLabel($wave !== null ? (float)$wave : null),
        'waveHeight'  => $wave   !== null ? round((float)$wave, 1)  : null,   // m
        'swellHeight' => $swellH !== null ? round((float)$swellH, 1) : null,  // m
        'swellPeriod' => $swellP !== null ? (int)round((float)$swellP) : null, // s
        'swellDir'    => windCompass($swellD !== null ? (float)$swellD : null), // de dónde viene
        'waterTemp'   => $water  !== null ? (int)round((float)$water) : null, // °C
        'airTemp'     => $air    !== null ? (int)round((float)$air)   : null, // °C
        'wind'        => [
            'speed' => $windMs !== null ? (int)round((float)$windMs * 3.6) : null, // km/h
            'dir'   => windCompass($windDir !== null ? (float)$windDir : null),
            'gust'  => $gustMs !== null ? (int)round((float)$gustMs * 3.6) : null, // km/h
        ],
        'current'     => [
            'kn'  => $curMs !== null ? round((float)$curMs * 1.9438, 1) : null,   // nudos
            'dir' => windCompass($curDir !== null ? (float)$curDir : null),       // hacia dónde va
        ],
        'visibility'  => $visKm  !== null ? (int)round((float)$visKm)  : null,    // km
        'cloudCover'  => $clouds !== null ? (int)round((float)$clouds) : null,    // %
        'precip'      => $precip !== null ? round((float)$precip, 1)   : null,    // mm/h
    ];
}

// ════════════════════════════════════════════════════════════════════════════
//  Amanecer / atardecer — cálculo local (sin llamadas API)
// ════════════════════════════════════════════════════════════════════════════

function sunTimes(): array {
    $info = date_sun_info(time(), LAT, LNG);   // timestamps UTC
    $fmt = function ($t) {
        if (!$t || !is_int($t)) return null;
        return (new DateTimeImmutable('@' . $t))->setTimezone(gtz())->format('H:i');
    };
    return ['sunrise' => $fmt($info['sunrise'] ?? null), 'sunset' => $fmt($info['sunset'] ?? null)];
}

// ════════════════════════════════════════════════════════════════════════════
//  Índice UV — Open-Meteo (gratis, sin key, no toca el quota de Stormglass)
// ════════════════════════════════════════════════════════════════════════════

/**
 * Trae la curva UV del día (por hora) + máximo. El cron la cachea y el feed
 * elige el valor de la hora actual al responder — UV fresco sin más llamadas.
 */
function fetchUv(): ?array {
    $url = 'https://api.open-meteo.com/v1/forecast?' . http_build_query([
        'latitude' => LAT, 'longitude' => LNG,
        'hourly'   => 'uv_index', 'daily' => 'uv_index_max',
        'timezone' => GALAPAGOS_TZ, 'forecast_days' => 1,
    ]);
    $json = httpGet($url);
    if (!$json) return null;
    $d = json_decode($json, true);
    $times = $d['hourly']['time'] ?? [];
    $vals  = $d['hourly']['uv_index'] ?? [];
    if (!$times || count($times) !== count($vals)) return null;
    $hours = [];
    foreach ($times as $i => $t) $hours[substr($t, 11, 5)] = round((float)$vals[$i], 1);
    return [
        'uvHours' => $hours,                                              // 'HH:00' => uv
        'uvMax'   => round((float)($d['daily']['uv_index_max'][0] ?? 0), 1),
    ];
}

// ════════════════════════════════════════════════════════════════════════════
//  Luna + aguaje — cálculo local (sin llamadas API)
// ════════════════════════════════════════════════════════════════════════════

/**
 * Fase lunar por edad sinódica (referencia: luna nueva 2000-01-06 18:14 UTC).
 * "Aguaje" = mareas vivas (±2 días de luna nueva o llena): mayor rango de
 * marea y corrientes más fuertes — dato que la comunidad costera sí usa.
 */
function moonData(): array {
    $synodic = 29.530588853;
    $age = fmod((time() - 947182440) / 86400.0, $synodic);
    if ($age < 0) $age += $synodic;

    if     ($age <  1.85 || $age >= 27.68) $label = 'Luna nueva';
    elseif ($age <  5.53)                  $label = 'Creciente';
    elseif ($age <  9.22)                  $label = 'Cuarto creciente';
    elseif ($age < 12.91)                  $label = 'Gibosa creciente';
    elseif ($age < 16.61)                  $label = 'Luna llena';
    elseif ($age < 20.30)                  $label = 'Gibosa menguante';
    elseif ($age < 23.99)                  $label = 'Cuarto menguante';
    else                                   $label = 'Menguante';

    $dNew  = min($age, $synodic - $age);          // días a la luna nueva más cercana
    $dFull = abs($age - $synodic / 2);            // días a la luna llena
    return [
        'moonPhase' => $label,
        'moonAge'   => round($age, 1),
        'aguaje'    => ($dNew <= 2.0 || $dFull <= 2.0),
    ];
}
