<?php
require_once __DIR__ . '/../config.php';
requireAuth();

$id     = trim($_GET['id'] ?? '');
$event  = findEvent($id);
$errors = [];
$success= false;

if (!$event) {
    header('Location: ' . SITE_URL . '/admin/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title         = trim($_POST['title']         ?? '');
    $event_date    = trim($_POST['event_date']    ?? '');
    $publish_from  = trim($_POST['publish_from']  ?? '');
    $publish_until = trim($_POST['publish_until'] ?? '');
    $event_type    = ($_POST['event_type'] ?? 'evento') === 'importante' ? 'importante' : 'evento';
    $cat           = in_array($_POST['cat'] ?? '', ['deportes','bienestar','mar','arte','letras'], true) ? $_POST['cat'] : null;
    $event_time    = trim($_POST['event_time'] ?? '');
    $place         = trim($_POST['place']      ?? '');
    $kicker        = trim($_POST['kicker']     ?? '');
    $subtitle      = trim($_POST['subtitle']   ?? '');
    $description   = trim($_POST['description'] ?? '');
    $cta           = trim($_POST['cta']        ?? '');
    $partners      = trim($_POST['partners']   ?? '');

    if (!$title)         $errors[] = 'El título es obligatorio.';
    if (!$event_date)    $errors[] = 'La fecha del evento es obligatoria.';
    if (!$publish_from)  $errors[] = 'La fecha de inicio es obligatoria.';
    if (!$publish_until) $errors[] = 'La fecha de fin es obligatoria.';
    if ($publish_until < $publish_from) $errors[] = 'La fecha de fin no puede ser anterior al inicio.';

    if (empty($errors)) {
        $events = loadEvents();
        foreach ($events as &$e) {
            if ($e['id'] === $id) {
                $e['title']         = $title;
                $e['event_date']    = $event_date;
                $e['publish_from']  = $publish_from;
                $e['publish_until'] = $publish_until;
                $e['event_type']    = $event_type;
                // Campos del destacado: guardar si tienen valor, limpiar si quedaron vacíos
                foreach (['cat'=>$cat,'event_time'=>$event_time,'place'=>$place,'kicker'=>$kicker,'subtitle'=>$subtitle,'description'=>$description,'cta'=>$cta,'partners'=>$partners] as $k=>$v) {
                    if ($v !== null && $v !== '') $e[$k] = $v; else unset($e[$k]);
                }
                $e['updated_at']    = date('c');
                $event = $e; // refresh local copy
                break;
            }
        }
        unset($e);
        saveEvents($events);
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Events CMS — Editar evento</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root { --teal:#00A9A8; --blue:#1672A3; --ink:#0f1923; --mist:#f0f4f7; --gray:#6b7280; }
  body { font-family: 'DM Sans', sans-serif; background: var(--mist); color: var(--ink); }

  header {
    background: #fff; border-bottom: 1px solid #e5e7eb;
    padding: 0 32px; display: flex; align-items: center;
    justify-content: space-between; height: 60px;
  }

  .brand { font-family: 'DM Mono', monospace; font-size: 13px; color: var(--teal); letter-spacing: .1em; }
  .brand span { color: var(--gray); }

  .btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border-radius: 8px; font-size: 13px;
    font-weight: 600; cursor: pointer; text-decoration: none;
    border: none; font-family: 'DM Sans', sans-serif; transition: all .15s;
  }
  .btn-ghost   { background: transparent; color: var(--gray); border: 1.5px solid #d1d5db; }
  .btn-ghost:hover { border-color: var(--teal); color: var(--teal); }
  .btn-primary { background: var(--teal); color: #fff; font-size: 15px; padding: 12px 28px; }
  .btn-primary:hover { background: var(--blue); }

  main { max-width: 640px; margin: 0 auto; padding: 40px 24px; }
  h1   { font-size: 22px; font-weight: 600; margin-bottom: 28px; }

  .card { background: #fff; border-radius: 16px; padding: 32px; box-shadow: 0 1px 4px rgba(0,0,0,.06); }

  /* Current image preview */
  .current-image {
    display: flex; align-items: center; gap: 16px;
    background: var(--mist); border-radius: 10px;
    padding: 14px 16px; margin-bottom: 28px;
  }
  .current-image img { width: 80px; height: 56px; object-fit: cover; border-radius: 6px; }
  .current-image-meta { font-size: 13px; color: var(--gray); }
  .current-image-note { font-size: 12px; color: #9ca3af; margin-top: 4px; }

  .field { margin-bottom: 24px; }

  label {
    display: block; font-size: 12px; font-weight: 600;
    text-transform: uppercase; letter-spacing: .08em;
    color: var(--gray); margin-bottom: 8px;
  }

  .hint { font-size: 12px; color: #9ca3af; margin-top: 4px; }

  input[type=text], input[type=date], textarea {
    width: 100%; border: 1.5px solid #d1d5db; border-radius: 8px;
    padding: 10px 14px; font-family: 'DM Sans', sans-serif; font-size: 15px;
    color: var(--ink); outline: none; transition: border-color .2s;
  }
  textarea { resize: vertical; min-height: 76px; }
  input:focus, textarea:focus { border-color: var(--teal); }

  .date-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

  .errors {
    background: #fef2f2; border: 1px solid #fca5a5; color: #dc2626;
    border-radius: 10px; padding: 14px 16px; margin-bottom: 24px; font-size: 14px;
  }
  .errors ul { margin-left: 18px; }

  .success-box {
    background: #f0fdf4; border: 1px solid #86efac; color: #166534;
    border-radius: 10px; padding: 14px 16px; margin-bottom: 24px; font-size: 14px;
  }

  .meta { font-family: 'DM Mono', monospace; font-size: 11px; color: #9ca3af; margin-top: 28px; }

  .divider { border: none; border-top: 1px solid #f3f4f6; margin: 28px 0; }
</style>
</head>
<body>

<header>
  <div class="brand">USFQ · <span>Events CMS</span></div>
  <a href="index.php" class="btn btn-ghost">← Volver</a>
</header>

<main>
  <h1>Editar evento</h1>

  <?php if (!empty($errors)): ?>
    <div class="errors">
      <strong>Revisa los siguientes campos:</strong>
      <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="success-box">✅ <strong>Cambios guardados.</strong></div>
  <?php endif; ?>

  <div class="card">

    <div class="current-image">
      <img src="<?= htmlspecialchars(UPLOAD_URL . $event['image']) ?>" alt="">
      <div>
        <div class="current-image-meta">Imagen actual</div>
        <div class="current-image-note">La imagen no se puede cambiar en edición.<br>Si necesitas otra imagen, borra y crea un nuevo evento.</div>
      </div>
    </div>

    <form method="POST">

      <div class="field">
        <label for="title">Título del evento</label>
        <input type="text" id="title" name="title"
               value="<?= htmlspecialchars($_POST['title'] ?? $event['title']) ?>" required>
      </div>

      <div class="field">
        <label for="event_type">Tipo</label>
        <select id="event_type" name="event_type" style="width:100%;border:1.5px solid #d1d5db;border-radius:8px;padding:10px 14px;font-family:'DM Sans',sans-serif;font-size:15px;color:var(--ink);background:#fff">
          <option value="evento" <?= ($_POST['event_type'] ?? $event['event_type'] ?? 'evento') === 'evento' ? 'selected' : '' ?>>Evento</option>
          <option value="importante" <?= ($_POST['event_type'] ?? $event['event_type'] ?? '') === 'importante' ? 'selected' : '' ?>>Importante (con estrella y prioridad en la agenda)</option>
        </select>
        <div class="hint">Todos los eventos publicados rotan en el espacio destacado del kiosko, mezclados con las promos.</div>
      </div>

      <div class="field">
        <label for="event_date">Fecha del evento</label>
        <input type="date" id="event_date" name="event_date"
               value="<?= htmlspecialchars($_POST['event_date'] ?? $event['event_date']) ?>" required>
      </div>

      <div id="destacado-fields">
        <hr class="divider">
        <div class="field">
          <label for="kicker">Antetítulo (kicker)</label>
          <input type="text" id="kicker" name="kicker" value="<?= htmlspecialchars($_POST['kicker'] ?? $event['kicker'] ?? '') ?>" placeholder="Ej. Invitación · Lanzamiento de proyecto">
        </div>
        <div class="field">
          <label for="subtitle">Subtítulo <span style="text-transform:none;letter-spacing:0;color:#9ca3af">(opcional)</span></label>
          <input type="text" id="subtitle" name="subtitle" value="<?= htmlspecialchars($_POST['subtitle'] ?? $event['subtitle'] ?? '') ?>" placeholder="Una línea que describe el evento">
        </div>
        <div class="field">
          <label for="description">Texto <span style="text-transform:none;letter-spacing:0;color:#9ca3af">(opcional)</span></label>
          <textarea id="description" name="description" placeholder="Texto breve que acompaña al afiche en pantalla (2–3 líneas máximo)"><?= htmlspecialchars($_POST['description'] ?? $event['description'] ?? '') ?></textarea>
        </div>
        <div class="field">
          <label for="cta">Llamada a la acción <span style="text-transform:none;letter-spacing:0;color:#9ca3af">(opcional)</span></label>
          <input type="text" id="cta" name="cta" value="<?= htmlspecialchars($_POST['cta'] ?? $event['cta'] ?? '') ?>" placeholder="Ej. Inscríbete en eventos.usfq.edu.ec · Cupos limitados">
        </div>
        <div class="date-row">
          <div class="field">
            <label for="event_time">Hora</label>
            <input type="text" id="event_time" name="event_time" value="<?= htmlspecialchars($_POST['event_time'] ?? $event['event_time'] ?? '') ?>" placeholder="Ej. 17:30">
          </div>
          <div class="field">
            <label for="cat">Categoría</label>
            <select id="cat" name="cat" style="width:100%;border:1.5px solid #d1d5db;border-radius:8px;padding:10px 14px;font-family:'DM Sans',sans-serif;font-size:15px;color:var(--ink);background:#fff">
              <?php foreach (['mar'=>'Ciencia y mar','deportes'=>'Deportes','bienestar'=>'Bienestar','arte'=>'Arte y cultura','letras'=>'Académico'] as $k=>$v): ?>
                <option value="<?= $k ?>" <?= ($_POST['cat'] ?? $event['cat'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="field">
          <label for="place">Lugar</label>
          <input type="text" id="place" name="place" value="<?= htmlspecialchars($_POST['place'] ?? $event['place'] ?? '') ?>" placeholder="Ej. Salón Multiusos · USFQ Galápagos">
        </div>
        <div class="field">
          <label for="partners">Aliados / organizadores</label>
          <input type="text" id="partners" name="partners" value="<?= htmlspecialchars($_POST['partners'] ?? $event['partners'] ?? '') ?>" placeholder="Ej. Galápagos Life Fund · Galápagos Science Center">
        </div>
      </div>

      <hr class="divider">

      <div class="field">
        <label>Rango de publicación en pantallas</label>
        <div class="date-row">
          <div>
            <label for="publish_from" style="margin-top:0">Desde</label>
            <input type="date" id="publish_from" name="publish_from"
                   value="<?= htmlspecialchars($_POST['publish_from'] ?? $event['publish_from']) ?>" required>
          </div>
          <div>
            <label for="publish_until" style="margin-top:0">Hasta</label>
            <input type="date" id="publish_until" name="publish_until"
                   value="<?= htmlspecialchars($_POST['publish_until'] ?? $event['publish_until']) ?>" required>
          </div>
        </div>
        <div class="hint">Las pantallas mostrarán este evento solo dentro de este rango.</div>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">
        Guardar cambios
      </button>

    </form>

    <div class="meta">
      ID: <?= htmlspecialchars($event['id']) ?> ·
      Creado: <?= htmlspecialchars($event['created_at']) ?>
      <?php if (!empty($event['updated_at'])): ?>
       · Editado: <?= htmlspecialchars($event['updated_at']) ?>
      <?php endif; ?>
    </div>

  </div>
</main>

<script>
  // Los campos de contenido del destacado aplican a todos los eventos (siempre visibles)
</script>
</body>
</html>
