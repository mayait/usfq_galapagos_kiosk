<?php
/**
 * cromos/cromo.php — Página pública de UN cromo
 * URL linda: /cromos/<slug>  (ej. /cromos/juli, /cromos/dr_paez)
 * Muestra la carta + los mensajes (anónimos) que le ha escrito el equipo,
 * con Open Graph por persona: compartir tu cromo por WhatsApp muestra TU carta.
 */
require_once __DIR__ . '/../config.php';
date_default_timezone_set(GALAPAGOS_TZ);

if (!defined('STAFF_FILE'))  define('STAFF_FILE',  DATA_DIR . '/staff.json');
if (!defined('CROMOS_FILE')) define('CROMOS_FILE', DATA_DIR . '/cromos.json');

/** Slug a partir del nombre: "DR. PÁEZ" → dr_paez */
function cromoSlug(string $name): string {
    $s = mb_strtolower(trim($name), 'UTF-8');
    $s = strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u']);
    $s = preg_replace('/[^a-z0-9]+/', '_', $s);
    return trim($s, '_');
}

$slug = cromoSlug($_GET['slug'] ?? '');

$person = null;
foreach (loadJson(STAFF_FILE) as $s) {
    if (!($s['enabled'] ?? true)) continue;
    if (cromoSlug($s['name'] ?? '') === $slug || ($s['id'] ?? '') === $slug) { $person = $s; break; }
}
if (!$person || $slug === '') { header('Location: /cromos/'); exit; }

$photo = UPLOAD_URL . ($person['image'] ?? '');
$name  = $person['name'] ?? '';
$role  = $person['role'] ?? '';

// Mensajes para esta persona — ANÓNIMOS, en desorden estable (no cronológico)
$msgs = [];
foreach ((loadJson(CROMOS_FILE)['messages'] ?? []) as $m) {
    if (($m['to'] ?? '') === ($person['id'] ?? '') && trim($m['msg'] ?? '') !== '') {
        $msgs[] = trim($m['msg']);
    }
}
usort($msgs, fn($a, $b) => crc32($a) <=> crc32($b));

$title = "Cromo de $name · GALAPALINI 26";
$desc  = $msgs
    ? "⚽ " . count($msgs) . ($msgs && count($msgs) === 1 ? " mensaje" : " mensajes") . " del equipo para $name. Escríbele algo lindo y pega su cromo en tu álbum."
    : "⚽ El cromo mundialista de $name. ¡Escríbele algo lindo y pégalo en tu álbum!";
$e = fn($x) => htmlspecialchars($x, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($title) ?></title>
<meta property="og:type" content="profile">
<meta property="og:url" content="https://api.julianmaya.com/cromos/<?= $e($slug) ?>">
<meta property="og:title" content="<?= $e($title) ?>">
<meta property="og:description" content="<?= $e($desc) ?>">
<meta property="og:image" content="<?= $e($photo) ?>">
<meta property="og:locale" content="es_EC">
<meta name="twitter:card" content="summary_large_image">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Anton&family=Space+Grotesk:wght@400;500;600;700&family=Caveat:wght@600;700&display=swap" rel="stylesheet">
<style>
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  :root{--navy:#0c2f40;--gold:#ffd573;--red:#e0492f;--paper:#f7f1de}
  body{
    min-height:100vh;font-family:'Space Grotesk',sans-serif;color:#fff;
    display:flex;flex-direction:column;align-items:center;padding:26px 16px 40px;
    background:
      radial-gradient(560px 420px at 108% -6%, rgba(240,169,63,.30), transparent 60%),
      radial-gradient(620px 460px at -12% 30%, rgba(224,73,47,.26), transparent 60%),
      linear-gradient(168deg,#15506b,var(--navy) 64%);
  }
  .brand{font-family:'Anton',sans-serif;font-size:19px;letter-spacing:3px;color:var(--gold);text-decoration:none}
  .brand small{display:block;font-family:'Space Grotesk',sans-serif;font-size:9.5px;letter-spacing:2.5px;color:rgba(255,255,255,.75);text-align:center;margin-top:2px}
  .card{
    width:min(74vw,300px);margin:24px 0 14px;border-radius:14px;display:block;
    transform:rotate(-1.6deg);
    box-shadow:0 24px 60px rgba(0,0,0,.5), 0 0 0 1px rgba(255,255,255,.18);
  }
  h1{font-family:'Anton',sans-serif;font-size:30px;letter-spacing:2px;margin-top:6px}
  .role{font-size:13px;letter-spacing:1.5px;text-transform:uppercase;color:var(--gold);margin-top:3px}
  .count{font-size:12.5px;color:rgba(255,255,255,.75);margin:16px 0 4px}
  .msgs{width:min(94vw,460px);display:flex;flex-direction:column;gap:11px;margin-top:10px}
  .msg{
    background:#fffbe8;border-radius:8px 18px 18px 18px;padding:13px 16px 11px;position:relative;
    box-shadow:0 6px 18px rgba(0,0,0,.30);color:#3b3325;
  }
  .msg::before{content:"";position:absolute;top:0;left:0;right:0;height:6px;border-radius:8px 18px 0 0;
    background:linear-gradient(90deg,#ff8a8a,#ffd573,#9ff0c8,#9ad8ff,#d9a8ff)}
  .msg p{font-family:'Caveat',cursive;font-size:23px;line-height:1.25;padding-top:5px}
  .msg:nth-child(even){transform:rotate(.6deg)}
  .msg:nth-child(3n){transform:rotate(-.5deg)}
  .empty{font-size:14px;color:rgba(255,255,255,.8);margin-top:18px;text-align:center;line-height:1.6}
  .actions{display:flex;gap:10px;flex-wrap:wrap;justify-content:center;margin-top:26px}
  .cta{
    display:inline-block;text-decoration:none;color:#fff;cursor:pointer;border:none;
    font-family:'Space Grotesk',sans-serif;
    background:linear-gradient(120deg,var(--red),#ff7a52);border-radius:99px;
    font-weight:700;font-size:13.5px;letter-spacing:1px;padding:13px 28px;
    box-shadow:0 6px 22px rgba(224,73,47,.5)}
  .cta.share{background:transparent;border:1.5px solid var(--gold);color:var(--gold);box-shadow:none}
  .foot{margin-top:14px;font-size:11px;color:rgba(255,255,255,.55)}
</style>
</head>
<body>
  <a class="brand" href="/cromos/">GALAPALINI 26<small>TEAM USFQ GALÁPAGOS</small></a>
  <img class="card" src="<?= $e($photo) ?>" alt="Cromo de <?= $e($name) ?>">
  <h1><?= $e($name) ?></h1>
  <?php if ($role): ?><div class="role"><?= $e($role) ?></div><?php endif; ?>

  <?php if ($msgs): ?>
    <div class="count">💌 <?= count($msgs) ?> <?= count($msgs) === 1 ? 'mensaje' : 'mensajes' ?> del equipo</div>
    <div class="msgs">
      <?php foreach ($msgs as $m): ?><div class="msg"><p>“<?= $e($m) ?>”</p></div><?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="empty">Todavía nadie le ha escrito a <?= $e($name) ?>…<br>¡Sé la primera persona! ✍️</div>
  <?php endif; ?>

  <div class="actions">
    <a class="cta" href="/cromos/">⚽ Escríbele algo y pega su cromo</a>
    <button class="cta share" id="shareBtn">📤 Compartir</button>
  </div>
  <div class="foot">Los mensajes son anónimos 💛</div>
<script>
// Compartir: hoja nativa del teléfono; en desktop copia el link al portapapeles
document.getElementById('shareBtn').addEventListener('click', async function(){
  const url = 'https://api.julianmaya.com/cromos/<?= $e($slug) ?>';
  const data = { title: <?= json_encode($title, JSON_UNESCAPED_UNICODE) ?>, url: url };
  if (navigator.share) { try { await navigator.share(data); } catch(e){} return; }
  try { await navigator.clipboard.writeText(url); this.textContent = '✅ Link copiado'; }
  catch(e) { prompt('Copia el link:', url); }
  setTimeout(()=>{ this.textContent = '📤 Compartir'; }, 2200);
});
</script>
</body>
</html>
