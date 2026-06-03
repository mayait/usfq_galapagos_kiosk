<?php
/**
 * ════════════════════════════════════════════════════════════════════════════
 *  EVENTS CMS — API PÚBLICA
 *  GET /api/events.php
 * ════════════════════════════════════════════════════════════════════════════
 *
 *  Devuelve los eventos activos en formato JSON, con CORS habilitado para
 *  ser consumido desde cualquier frontend (pantallas, web, apps).
 *
 *  ── PARÁMETROS DE QUERY (todos opcionales) ────────────────────────────────
 *
 *  from    string  YYYY-MM-DD   Filtrar eventos cuya publish_from >= este valor.
 *                               Por defecto: ayer (yesterday).
 *
 *  until   string  YYYY-MM-DD   Filtrar eventos cuya publish_until <= este valor.
 *                               Por defecto: sin límite superior.
 *
 *  ── EJEMPLOS DE USO ──────────────────────────────────────────────────────
 *
 *  Eventos activos (hoy y futuro próximo):
 *    GET /api/events.php
 *
 *  Eventos de un rango específico:
 *    GET /api/events.php?from=2026-05-01&until=2026-05-31
 *
 *  Todos los eventos desde una fecha:
 *    GET /api/events.php?from=2026-04-01
 *
 *  ── RESPUESTA (200 OK) ────────────────────────────────────────────────────
 *
 *  {
 *    "ok": true,
 *    "generated_at": "2026-04-16T14:00:00-05:00",   // ISO 8601
 *    "filter": {
 *      "from":  "2026-04-15",
 *      "until": null
 *    },
 *    "count": 3,
 *    "events": [
 *      {
 *        "id":            "a3f9b2c1d4e5",            // ID único del evento
 *        "title":         "Seminario de Biología Marina",
 *        "image_url":     "https://tudominio.com/events-cms/uploads/2026-04/a3f9b2_banner.jpg",
 *        "event_date":    "2026-04-22",               // Fecha del evento (YYYY-MM-DD)
 *        "publish_from":  "2026-04-16",               // Inicio de publicación
 *        "publish_until": "2026-04-22",               // Fin de publicación
 *        "days_until_event": 6,                       // Días hasta el evento (negativo = pasó)
 *        "is_active":     true                        // Dentro del rango de publicación hoy
 *      }
 *    ]
 *  }
 *
 *  ── RESPUESTA EN ERROR ────────────────────────────────────────────────────
 *
 *  400 Bad Request:
 *  { "ok": false, "error": "Formato de fecha inválido. Usa YYYY-MM-DD." }
 *
 *  ── NOTAS PARA EL FRONTEND ───────────────────────────────────────────────
 *
 *  - Todos los campos de fecha son strings YYYY-MM-DD para fácil comparación.
 *  - image_url es una URL absoluta; úsala directamente en <img src="...">.
 *  - days_until_event = 0 significa que el evento es HOY.
 *  - Los eventos están ordenados por event_date ascendente (próximos primero).
 *  - CORS está habilitado (Access-Control-Allow-Origin: *).
 *
 * ════════════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/../config.php';

// ── Parsear parámetros ───────────────────────────────────────────────────────

$today     = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

$fromParam  = trim($_GET['from']  ?? $yesterday);
$untilParam = trim($_GET['until'] ?? '');

// Validar fechas
function isValidDate(string $d): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return false;
    $parts = explode('-', $d);
    return checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0]);
}

if (!isValidDate($fromParam)) {
    jsonResponse(['ok' => false, 'error' => 'Formato de "from" inválido. Usa YYYY-MM-DD.'], 400);
}

if ($untilParam !== '' && !isValidDate($untilParam)) {
    jsonResponse(['ok' => false, 'error' => 'Formato de "until" inválido. Usa YYYY-MM-DD.'], 400);
}

// ── Filtrar eventos ──────────────────────────────────────────────────────────

$allEvents = loadEvents();
$filtered  = [];

foreach ($allEvents as $e) {
    // El evento debe estar activo desde $fromParam en adelante
    if ($e['publish_until'] < $fromParam) continue;

    // Si se pasa un límite superior, el evento debe empezar antes de esa fecha
    if ($untilParam !== '' && $e['publish_from'] > $untilParam) continue;

    $eventDate      = new DateTime($e['event_date']);
    $todayDate      = new DateTime($today);
    $diff           = (int)$todayDate->diff($eventDate)->format('%r%a');

    $filtered[] = [
        'id'               => $e['id'],
        'title'            => $e['title'],
        'image_url'        => UPLOAD_URL . $e['image'],
        'event_date'       => $e['event_date'],
        'publish_from'     => $e['publish_from'],
        'publish_until'    => $e['publish_until'],
        'days_until_event' => $diff,
        'is_active'        => ($e['publish_from'] <= $today && $e['publish_until'] >= $today),
    ];
}

// Ordenar por fecha de evento ascendente
usort($filtered, fn($a, $b) => strcmp($a['event_date'], $b['event_date']));

// ── Responder ────────────────────────────────────────────────────────────────

jsonResponse([
    'ok'           => true,
    'generated_at' => date('c'),
    'filter'       => [
        'from'  => $fromParam,
        'until' => $untilParam ?: null,
    ],
    'count'  => count($filtered),
    'events' => $filtered,
]);
