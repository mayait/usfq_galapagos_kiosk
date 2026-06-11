<?php
/**
 * ════════════════════════════════════════════════════════════════════════════
 *  api/cromos.php — ÁLBUM COLABORATIVO "PALINI 26"
 *  GET  /api/cromos.php?me=<id>   → staff + ranking (+ mis cromos y mi muro)
 *  POST /api/cromos.php           → {from,to,msg}  pega un cromo (mensaje)
 * ════════════════════════════════════════════════════════════════════════════
 *
 *  Dinámica: cada persona del staff elige "quién soy" y desbloquea cada cromo
 *  escribiéndole algo lindo/chistoso. En su propio cromo ve su MURO: todos los
 *  mensajes que le escribieron. Hay un RANKING de quién completa el álbum primero.
 *
 *  Persistencia: flat-file JSON (data/cromos.json), mismo patrón que el resto del
 *  CMS. Escritura serializada con flock para no perder mensajes simultáneos.
 *  CORS abierto → el álbum (cromos/index.html) lo consume directo.
 * ════════════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/../config.php';
date_default_timezone_set(GALAPAGOS_TZ);

if (!defined('STAFF_FILE'))  define('STAFF_FILE',  DATA_DIR . '/staff.json');
if (!defined('CROMOS_FILE')) define('CROMOS_FILE', DATA_DIR . '/cromos.json');

const CROMO_MIN_LEN = 12;
const CROMO_MAX_LEN = 600;

// ── CORS (lectura y escritura) ───────────────────────────────────────────────
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { http_response_code(204); exit; }

function out(array $d, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Staff activo, en orden, con foto absoluta. */
function cromoStaff(): array {
    $list = [];
    foreach (loadJson(STAFF_FILE) as $s) {
        if (!($s['enabled'] ?? true)) continue;
        if (empty($s['id'])) continue;
        $list[] = [
            'id'    => $s['id'],
            'name'  => $s['name'] ?? '',
            'role'  => $s['role'] ?? '',
            'photo' => UPLOAD_URL . ($s['image'] ?? ''),
        ];
    }
    return $list;
}

/** Serializa lectura+escritura del archivo de mensajes (evita updates perdidos). */
function withCromosLock(callable $fn) {
    $fp = @fopen(CROMOS_FILE . '.lock', 'c');
    if ($fp) flock($fp, LOCK_EX);
    try {
        return $fn();
    } finally {
        if ($fp) { flock($fp, LOCK_UN); fclose($fp); }
    }
}

$staff = cromoStaff();
$ids   = array_column($staff, 'id');
$N     = count($staff);
$target = max(0, $N - 1);   // meta: escribirle a todos menos a ti mismo
$nameById = $photoById = [];
foreach ($staff as $s) { $nameById[$s['id']] = $s['name']; $photoById[$s['id']] = $s['photo']; }

// ════════════════════════════════════════════════════════════════════════════
//  POST — pegar un cromo (crear/actualizar el mensaje from→to)
// ════════════════════════════════════════════════════════════════════════════
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) out(['ok' => false, 'error' => 'bad_json'], 400);

    $from = trim((string)($body['from'] ?? ''));
    $to   = trim((string)($body['to']   ?? ''));
    $msg  = trim((string)($body['msg']  ?? ''));

    if (!in_array($from, $ids, true) || !in_array($to, $ids, true)) out(['ok' => false, 'error' => 'invalid_person'], 400);
    if ($from === $to) out(['ok' => false, 'error' => 'self'], 400);

    $len = mb_strlen($msg);
    if ($len < CROMO_MIN_LEN) out(['ok' => false, 'error' => 'too_short'], 400);
    if ($len > CROMO_MAX_LEN) $msg = mb_substr($msg, 0, CROMO_MAX_LEN);

    $sentCount = withCromosLock(function () use ($from, $to, $msg) {
        $data = loadJson(CROMOS_FILE);
        $messages = $data['messages'] ?? [];
        $now = date('c');
        $found = false;
        foreach ($messages as &$m) {
            if (($m['from'] ?? '') === $from && ($m['to'] ?? '') === $to) {
                $m['msg'] = $msg; $m['ts'] = $now; $found = true; break;
            }
        }
        unset($m);
        if (!$found) $messages[] = ['from' => $from, 'to' => $to, 'msg' => $msg, 'ts' => $now];
        $data['messages'] = $messages;
        saveJson(CROMOS_FILE, $data);
        $c = 0;
        foreach ($messages as $m) if (($m['from'] ?? '') === $from) $c++;
        return $c;
    });

    out(['ok' => true, 'sent' => $sentCount, 'target' => $target, 'completed' => ($sentCount >= $target && $target > 0)]);
}

// ════════════════════════════════════════════════════════════════════════════
//  GET — feed: staff + ranking (+ mis cromos y mi muro si ?me=)
// ════════════════════════════════════════════════════════════════════════════
$me = trim((string)($_GET['me'] ?? ''));
$messages = loadJson(CROMOS_FILE)['messages'] ?? [];

// ── ?wall=<id> : TODOS los comentarios que le escribieron a esa persona ───────
//  (el muro de cualquier cromo: lo que el equipo le ha dicho a esa persona)
$wallId = trim((string)($_GET['wall'] ?? ''));
if ($wallId !== '') {
    if (!in_array($wallId, $ids, true)) out(['ok' => false, 'error' => 'invalid_person'], 400);
    $comments = [];
    foreach ($messages as $m) {
        $f = $m['from'] ?? ''; $t = $m['to'] ?? '';
        if ($t === $wallId && in_array($f, $ids, true) && $f !== $wallId) {
            $comments[] = [
                'from'       => $f,
                'from_name'  => $nameById[$f] ?? $f,
                'from_photo' => $photoById[$f] ?? '',
                'msg'        => $m['msg'] ?? '',
                'ts'         => $m['ts'] ?? '',
            ];
        }
    }
    usort($comments, fn($a, $b) => strcmp($b['ts'], $a['ts']));   // más recientes primero
    out([
        'ok'       => true,
        'id'       => $wallId,
        'name'     => $nameById[$wallId] ?? $wallId,
        'photo'    => $photoById[$wallId] ?? '',
        'count'    => count($comments),
        'comments' => $comments,
    ]);
}

// Agregados por autor (solo mensajes válidos: emisor/receptor activos, no a sí mismo)
$byAuthor = [];   // id => ['count'=>int, 'last_ts'=>string]
foreach ($messages as $m) {
    $f = $m['from'] ?? ''; $t = $m['to'] ?? '';
    if (!in_array($f, $ids, true) || !in_array($t, $ids, true) || $f === $t) continue;
    if (!isset($byAuthor[$f])) $byAuthor[$f] = ['count' => 0, 'last_ts' => ''];
    $byAuthor[$f]['count']++;
    if (($m['ts'] ?? '') > $byAuthor[$f]['last_ts']) $byAuthor[$f]['last_ts'] = $m['ts'] ?? '';
}

$ranking = [];
foreach ($byAuthor as $id => $a) {
    $completed = $target > 0 && $a['count'] >= $target;
    $ranking[] = [
        'id'           => $id,
        'name'         => $nameById[$id] ?? $id,
        'photo'        => $photoById[$id] ?? '',
        'count'        => $a['count'],
        'completed'    => $completed,
        'completed_at' => $completed ? $a['last_ts'] : null,
    ];
}
// Orden: completados primero por fecha de término (quien acabó antes gana);
// luego por # de cromos (desc).
usort($ranking, function ($a, $b) {
    if ($a['completed'] && $b['completed']) return strcmp($a['completed_at'], $b['completed_at']);
    if ($a['completed']) return -1;
    if ($b['completed']) return 1;
    return $b['count'] <=> $a['count'];
});

// Cromos más queridos: mensajes RECIBIDOS por persona (top 10)
$byTarget = [];
foreach ($messages as $m) {
    $f = $m['from'] ?? ''; $t = $m['to'] ?? '';
    if (!in_array($f, $ids, true) || !in_array($t, $ids, true) || $f === $t) continue;
    $byTarget[$t] = ($byTarget[$t] ?? 0) + 1;
}
$popular = [];
foreach ($byTarget as $id => $n) {
    $popular[] = ['id' => $id, 'name' => $nameById[$id] ?? $id, 'photo' => $photoById[$id] ?? '', 'count' => $n];
}
usort($popular, fn($a, $b) => $b['count'] <=> $a['count']);
$popular = array_slice($popular, 0, 10);

$resp = [
    'ok'      => true,
    'staff'   => $staff,
    'total'   => $N,
    'target'  => $target,
    'ranking' => $ranking,
    'popular' => $popular,
    'players' => count($byAuthor),
];

if ($me !== '' && in_array($me, $ids, true)) {
    $sent = [];   // to_id => {msg, ts}   (mis cromos pegados)
    $wall = [];   // mensajes que ME escribieron
    foreach ($messages as $m) {
        $f = $m['from'] ?? ''; $t = $m['to'] ?? '';
        if ($f === $me && in_array($t, $ids, true) && $t !== $me) {
            $sent[$t] = ['msg' => $m['msg'] ?? '', 'ts' => $m['ts'] ?? ''];
        }
        if ($t === $me && in_array($f, $ids, true) && $f !== $me) {
            $wall[] = [
                'from'       => $f,
                'from_name'  => $nameById[$f] ?? $f,
                'from_photo' => $photoById[$f] ?? '',
                'msg'        => $m['msg'] ?? '',
                'ts'         => $m['ts'] ?? '',
            ];
        }
    }
    usort($wall, fn($a, $b) => strcmp($b['ts'], $a['ts']));   // más recientes primero
    $resp['me']         = $me;
    $resp['sent']       = (object)$sent;
    $resp['sent_count'] = count($sent);
    $resp['wall']       = $wall;
}

out($resp);
