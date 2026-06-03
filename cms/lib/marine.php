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

function fetchStormglass(): ?array {
    $now = time();
    $url = STORMGLASS_ENDPOINT . '?' . http_build_query([
        'lat'    => LAT, 'lng' => LNG,
        'params' => 'waveHeight,swellHeight,swellPeriod,waterTemperature,windSpeed,windDirection,airTemperature',
        'start'  => $now, 'end' => $now,
    ]);
    $json = httpGet($url, ['Authorization: ' . STORMGLASS_KEY]);
    if (!$json) return null;
    $hours = json_decode($json, true)['hours'] ?? [];
    if (!$hours) return null;
    $h = $hours[0];

    $wave    = sgPick($h['waveHeight']       ?? null);
    $swellH  = sgPick($h['swellHeight']      ?? null);
    $swellP  = sgPick($h['swellPeriod']      ?? null);
    $water   = sgPick($h['waterTemperature'] ?? null);
    $air     = sgPick($h['airTemperature']   ?? null);
    $windMs  = sgPick($h['windSpeed']        ?? null);
    $windDir = sgPick($h['windDirection']    ?? null);

    return [
        'seaState'    => seaStateLabel($wave !== null ? (float)$wave : null),
        'waveHeight'  => $wave   !== null ? round((float)$wave, 1)  : null,   // m
        'swellHeight' => $swellH !== null ? round((float)$swellH, 1) : null,  // m
        'swellPeriod' => $swellP !== null ? (int)round((float)$swellP) : null, // s
        'waterTemp'   => $water  !== null ? (int)round((float)$water) : null, // °C
        'airTemp'     => $air    !== null ? (int)round((float)$air)   : null, // °C
        'wind'        => [
            'speed' => $windMs !== null ? (int)round((float)$windMs * 3.6) : null, // km/h
            'dir'   => windCompass($windDir !== null ? (float)$windDir : null),
        ],
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
