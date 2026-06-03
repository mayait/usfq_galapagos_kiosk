<?php
/**
 * ════════════════════════════════════════════════════════════════════════════
 *  cron/fetch_marine.php — Refresca mareas + estado del mar (cada 3 h)
 * ════════════════════════════════════════════════════════════════════════════
 *
 *  Llama a NOAA (mareas) y Stormglass (estado del mar), calcula amanecer/
 *  atardecer localmente y cachea todo en data/marine.json. Tolerante a fallos:
 *  si una fuente falla, conserva el último dato bueno.
 *
 *  Ejecutar:
 *    • Cron cPanel (CLI):  php /home/humaiyld/api.julianmaya.com/cron/fetch_marine.php
 *    • Por URL (token):    https://api.julianmaya.com/cron/fetch_marine.php?token=XXXX
 * ════════════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/marine.php';

date_default_timezone_set(GALAPAGOS_TZ);

$isCli = (PHP_SAPI === 'cli');

// ── Seguridad: por URL exige token ───────────────────────────────────────────
if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
    if (($_GET['token'] ?? '') !== CRON_TOKEN) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Token inválido']);
        exit;
    }
}

// ── Estado previo (para conservar si una fuente falla) ───────────────────────
$prev = loadJson(MARINE_FILE);

// Cada fuente se llama UNA sola vez
$tidesFresh  = fetchNoaaTides();      // NOAA (gratis)
$marineFresh = fetchStormglass();     // Stormglass (cuenta para el límite diario)
$sun = sunTimes();                    // cálculo local, siempre disponible

$tides = $tidesFresh ?? ($prev['tides'] ?? null);

if ($marineFresh !== null) {
    $marine = array_merge($marineFresh, $sun);
} elseif (is_array($prev['marine'] ?? null)) {
    $marine = array_merge($prev['marine'], $sun);   // conserva mar previo, refresca sol
} else {
    $marine = $sun;                                  // al menos amanecer/atardecer
}

$payload = [
    'updated_at' => date('c'),
    'sources'    => [
        'tides'      => $tidesFresh  !== null ? 'noaa'       : ($tides  ? 'cache' : 'sin-datos'),
        'stormglass' => $marineFresh !== null ? 'stormglass' : (isset($marine['seaState']) ? 'cache' : 'solo-sol'),
    ],
    'tides'  => $tides,
    'marine' => $marine,
];

$ok = saveJson(MARINE_FILE, $payload);

if ($isCli) {
    fwrite(STDOUT, ($ok ? '[OK] ' : '[ERROR] ') . 'marine.json actualizado: ' . $payload['updated_at'] . PHP_EOL);
    fwrite(STDOUT, '  mareas: ' . (isset($tides['points']) ? count($tides['points']) . ' puntos (' . ($tides['state'] ?? '—') . ')' : 'sin datos') . PHP_EOL);
    fwrite(STDOUT, '  mar:    ' . ($marine['seaState'] ?? '—')
        . ' · ola ' . ($marine['waveHeight'] ?? '—') . 'm'
        . ' · agua ' . ($marine['waterTemp'] ?? '—') . '°C'
        . ' · viento ' . ($marine['wind']['speed'] ?? '—') . 'km/h ' . ($marine['wind']['dir'] ?? '')
        . ' · sol ' . ($marine['sunrise'] ?? '—') . '→' . ($marine['sunset'] ?? '—') . PHP_EOL);
} else {
    echo json_encode(['ok' => $ok, 'updated_at' => $payload['updated_at']], JSON_UNESCAPED_UNICODE);
}
