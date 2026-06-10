<?php
/**
 * ════════════════════════════════════════════════════════════════════════════
 *  api/kiosk.php — FEED ÚNICO del kiosko Mosaico
 *  GET /api/kiosk.php
 * ════════════════════════════════════════════════════════════════════════════
 *
 *  Reúne en una sola respuesta todo lo que necesita la pantalla:
 *    clubs · pinned (destacados) · promos (convenios) · days (agenda semanal)
 *    tides · marine (estado del mar) · week
 *
 *  Fuentes fusionadas server-side:
 *    - Calendarios ICS de Outlook  → agenda semanal (lib/ics.php, sin proxies)
 *    - CMS (events.json)           → eventos/importantes con afiche
 *    - clubs.json / promos.json    → clubes y convenios gestionados en /admin
 *    - marine.json                 → mareas + estado del mar (cron cada 3 h)
 *
 *  CORS abierto → el kiosko lo consume directo desde cualquier origen.
 * ════════════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/ics.php';
require_once __DIR__ . '/../lib/marine.php';

date_default_timezone_set(GALAPAGOS_TZ);

const MES_ES_FULL = ['enero','febrero','marzo','abril','mayo','junio',
                     'julio','agosto','septiembre','octubre','noviembre','diciembre'];

const ACCENT_BY_CAT = [
    'mar'       => 'var(--color-turquesa)',
    'deportes'  => 'var(--color-turquesa)',
    'bienestar' => 'var(--color-azul-medio)',
    'arte'      => 'var(--color-coral)',
    'letras'    => 'var(--color-arena)',
];

/** Mapea un evento del CMS (afiche) a una diapositiva destacada (pinned). */
function eventToPinned(array $e): array {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $e['event_date'] ?? '', gtz())
            ?: new DateTimeImmutable('now', gtz());
    $cat  = $e['cat'] ?? detectCat($e['title'] ?? '');
    $img  = UPLOAD_URL . ($e['image'] ?? '');
    return [
        'kicker'      => $e['kicker']   ?? 'Evento destacado',
        'title'       => cleanTitle($e['title'] ?? ''),
        'subtitle'    => $e['subtitle'] ?? '',
        'description' => $e['description'] ?? '',
        'cta'         => $e['cta'] ?? '',
        'star'        => ($e['event_type'] ?? 'evento') === 'importante',
        'dateNum'     => $date->format('d'),
        'month'       => mb_strtoupper(MES_ES_SHORT[(int)$date->format('n') - 1]),
        'year'        => $date->format('Y'),
        'time'        => $e['event_time'] ?? '',
        'place'       => stripEmojis($e['place'] ?? ''),
        'partners'    => $e['partners'] ?? '',
        'photo'       => $img,
        'flyer'       => $img,
        'accent'      => $e['accent'] ?? (ACCENT_BY_CAT[$cat] ?? 'var(--color-turquesa)'),
    ];
}

/** Normaliza la foto de un convenio/promo a URL absoluta. */
function promoPhoto(array $p): string {
    if (!empty($p['photo']) && str_starts_with($p['photo'], 'http')) return $p['photo'];
    if (!empty($p['image'])) return UPLOAD_URL . $p['image'];
    return $p['photo'] ?? '';
}

// ── Semana actual + ventana de próximos eventos ──────────────────────────────
[$monday, $sunday] = getWeekBounds();
[$upFrom, $upTo]   = getUpcomingBounds();          // hoy → +60 días
$today = (new DateTimeImmutable('now', gtz()))->format('Y-m-d');

// ── 1) Destacados (pinned): TODOS los eventos del CMS con publicación activa ─
//  Cada afiche subido al CMS rota en el espacio destacado del kiosko, mezclado
//  con las promos. "Importante" solo añade la estrella y el badge en la agenda.
$cmsEvents       = loadEvents();
$pinned          = [];
$pinnedByEventId = [];
foreach ($cmsEvents as $e) {
    $active = ($e['publish_from'] ?? '0000-00-00') <= $today
           && ($e['publish_until'] ?? '9999-99-99') >= $today;
    if ($active) {
        $pinnedByEventId[$e['id']] = count($pinned);
        $pinned[] = eventToPinned($e);
    }
}

// ── Clubes (se usan también para NO duplicarlos en la agenda) ────────────────
$clubs = array_values(array_filter(loadClubs(), fn($c) => ($c['enabled'] ?? true)));
$clubNorms = array_values(array_filter(array_map(fn($c) => normalizeClubName($c['name'] ?? ''), $clubs)));

// ── 2) Agenda semanal (days[]) = ICS de Outlook + eventos del CMS de la semana
$dayBuckets = [];

// 2a) Eventos del calendario Outlook (con horas).
//     Se excluyen los que son clubes (#CLUB ya se filtró al ingerir; aquí además
//     se omiten los que coinciden con un club del CMS) → los clubes solo van en
//     el panel izquierdo, no duplicados en la agenda.
foreach (fetchUpcomingEvents() as $ev) {
    if (matchesClubName($ev['summary'] ?? '', $clubNorms)) continue;
    $d   = $ev['dtstart']->setTimezone(gtz());
    $key = $d->format('Y-m-d');
    $dayBuckets[$key][] = [
        'type'  => detectEventType($ev),
        'cat'   => detectCat($ev['summary']),
        'title' => cleanTitle($ev['summary']),
        'start' => fmtHM($ev['dtstart']),
        'end'   => !empty($ev['dtend']) ? fmtHM($ev['dtend']) : null,
        'place' => stripEmojis($ev['location'] ?? ''),
        '_ts'   => $ev['dtstart']->getTimestamp(),
    ];
}

// 2b) Eventos del CMS (afiches) cuyo event_date cae en la semana
foreach ($cmsEvents as $e) {
    if (empty($e['event_date'])) continue;
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $e['event_date'], gtz());
    if (!$d || $d < $upFrom || $d > $upTo) continue;

    $key  = $d->format('Y-m-d');
    $time = $e['event_time'] ?? null;
    $item = [
        'title'     => cleanTitle($e['title'] ?? ''),
        'place'     => stripEmojis($e['place'] ?? ''),
        'start'     => $time,
        'end'       => $e['event_end'] ?? null,
        'image_url' => UPLOAD_URL . ($e['image'] ?? ''),
        '_ts'       => $d->getTimestamp() + ($time ? (int)substr($time, 0, 2) * 3600 + (int)substr($time, 3, 2) * 60 : 0),
    ];
    if (($e['event_type'] ?? 'evento') === 'importante' && isset($pinnedByEventId[$e['id']])) {
        $item['type'] = 'importante';
        $item['ref']  = $pinnedByEventId[$e['id']];
    } else {
        $item['type'] = 'evento';
        $item['cat']  = $e['cat'] ?? detectCat($e['title'] ?? '');
    }
    $dayBuckets[$key][] = $item;
}

// 2c) Ordenar días y dentro de cada día por hora
ksort($dayBuckets);
$days  = [];
$count = 0;
foreach ($dayBuckets as $key => $items) {
    usort($items, fn($a, $b) => ($a['_ts'] ?? 0) <=> ($b['_ts'] ?? 0));
    foreach ($items as &$it) unset($it['_ts']);
    unset($it);
    $d = new DateTimeImmutable($key, gtz());
    $days[] = [
        'dow'   => dowEs($d),
        'date'  => dayDateEs($d),
        'today' => $key === $today,
        'items' => array_values($items),
    ];
    $count += count($items);
}

// ── 3) Clubes — ya cargados arriba ($clubs) ──────────────────────────────────

// ── 4) Convenios / promociones ───────────────────────────────────────────────
$promos = [];
foreach (loadPromos() as $p) {
    if (!($p['enabled'] ?? true)) continue;
    $p['photo'] = promoPhoto($p);
    unset($p['image'], $p['enabled']);
    $promos[] = $p;
}

// ── 5) Mareas + estado del mar (cacheado por el cron) ────────────────────────
//  El estado del mar (Stormglass) lo escribe SOLO el cron. Pero si el cron no
//  ha corrido o el dato está viejo (>6 h), refrescamos las mareas con NOAA en
//  línea (gratis) para que la franja nunca quede vacía.
$marineData = loadJson(MARINE_FILE);
$stale = empty($marineData['updated_at']) || (time() - strtotime($marineData['updated_at']) > 6 * 3600);
if ($stale) {
    $freshTides = fetchNoaaTides();
    if ($freshTides) {
        $marineData['tides'] = $freshTides;
        $marineData['marine'] = array_merge($marineData['marine'] ?? [], sunTimes());
        $marineData['updated_at'] = date('c');
        saveJson(MARINE_FILE, $marineData);   // cachea para el resto de pantallas
    }
}
$tides  = $marineData['tides'] ?? [
    'state'   => '—',
    'station' => 'NOAA · Estación San Cristóbal #' . NOAA_STATION,
    'points'  => [],
];
$marine = $marineData['marine'] ?? null;

// ── 6) Meta de la semana ─────────────────────────────────────────────────────
$week = [
    'label' => '',          // el kiosko muestra solo el conteo de próximos eventos
    'count' => $count,
];

// ── Responder ────────────────────────────────────────────────────────────────
jsonResponse([
    'ok'           => true,
    'generated_at' => date('c'),
    'week'         => $week,
    'clubs'        => $clubs,
    'pinned'       => $pinned,
    'promos'       => $promos,
    'days'         => $days,
    'tides'        => $tides,
    'marine'       => $marine,
    'marine_updated_at' => $marineData['updated_at'] ?? null,
]);
