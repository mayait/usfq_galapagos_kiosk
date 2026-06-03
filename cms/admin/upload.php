<?php
require_once __DIR__ . '/../config.php';
requireAuth();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title        = trim($_POST['title']        ?? '');
    $event_date   = trim($_POST['event_date']   ?? '');
    $publish_from = trim($_POST['publish_from'] ?? '');
    $publish_until= trim($_POST['publish_until']?? '');
    $event_type   = ($_POST['event_type'] ?? 'evento') === 'importante' ? 'importante' : 'evento';
    $cat          = in_array($_POST['cat'] ?? '', ['deportes','bienestar','mar','arte','letras'], true) ? $_POST['cat'] : null;
    $event_time   = trim($_POST['event_time'] ?? '');
    $place        = trim($_POST['place']      ?? '');
    $kicker       = trim($_POST['kicker']     ?? '');
    $subtitle     = trim($_POST['subtitle']   ?? '');
    $partners     = trim($_POST['partners']   ?? '');

    // Validaciones
    if (!$title)         $errors[] = 'El título es obligatorio.';
    if (!$event_date)    $errors[] = 'La fecha del evento es obligatoria.';
    if (!$publish_from)  $errors[] = 'La fecha de inicio de publicación es obligatoria.';
    if (!$publish_until) $errors[] = 'La fecha de fin de publicación es obligatoria.';
    if ($publish_until < $publish_from) $errors[] = 'La fecha de fin no puede ser anterior al inicio.';

    // Imagen
    if (empty($_FILES['image']['name'])) {
        $errors[] = 'Debes subir una imagen.';
    } else {
        $file     = $_FILES['image'];
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if ($file['size'] > MAX_FILE_SIZE)           $errors[] = 'La imagen supera 10 MB.';
        if (!in_array($mimeType, ALLOWED_TYPES))     $errors[] = 'Formato no permitido. Usa JPG, PNG, WEBP o GIF.';
    }

    if (empty($errors)) {
        // Crear carpeta del mes
        $monthDir = UPLOAD_DIR . date('Y-m') . '/';
        if (!is_dir($monthDir)) mkdir($monthDir, 0755, true);

        // Nombre de archivo único
        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $id       = generateId();
        $filename = $id . '_' . preg_replace('/[^a-z0-9]/', '', strtolower(pathinfo($file['name'], PATHINFO_FILENAME))) . '.' . $ext;
        $destPath = $monthDir . $filename;
        $imageRel = date('Y-m') . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            $errors[] = 'Error al guardar la imagen. Verifica permisos de la carpeta uploads/.';
        } else {
            $events = loadEvents();
            $events[] = array_filter([
                'id'            => $id,
                'title'         => $title,
                'image'         => $imageRel,
                'event_date'    => $event_date,
                'publish_from'  => $publish_from,
                'publish_until' => $publish_until,
                'event_type'    => $event_type,
                'cat'           => $cat,
                'event_time'    => $event_time,
                'place'         => $place,
                'kicker'        => $kicker,
                'subtitle'      => $subtitle,
                'partners'      => $partners,
                'created_at'    => date('c'),
            ], fn($v) => $v !== null && $v !== '');
            saveEvents($events);
            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Events CMS — Nuevo evento</title>
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
    position: sticky; top: 0; z-index: 10;
  }

  .brand { font-family: 'DM Mono', monospace; font-size: 13px; color: var(--teal); letter-spacing: .1em; }
  .brand span { color: var(--gray); }

  .btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border-radius: 8px; font-size: 13px;
    font-weight: 600; cursor: pointer; text-decoration: none;
    border: none; font-family: 'DM Sans', sans-serif; transition: all .15s;
  }

  .btn-ghost { background: transparent; color: var(--gray); border: 1.5px solid #d1d5db; }
  .btn-ghost:hover { border-color: var(--teal); color: var(--teal); }
  .btn-primary { background: var(--teal); color: #fff; font-size: 15px; padding: 12px 28px; }
  .btn-primary:hover { background: var(--blue); }

  main { max-width: 640px; margin: 0 auto; padding: 40px 24px; }

  h1 { font-size: 22px; font-weight: 600; margin-bottom: 28px; }

  .card { background: #fff; border-radius: 16px; padding: 32px; box-shadow: 0 1px 4px rgba(0,0,0,.06); }

  .field { margin-bottom: 24px; }

  label {
    display: block; font-size: 12px; font-weight: 600;
    text-transform: uppercase; letter-spacing: .08em;
    color: var(--gray); margin-bottom: 8px;
  }

  .hint { font-size: 12px; color: #9ca3af; margin-top: 4px; }

  input[type=text], input[type=date] {
    width: 100%; border: 1.5px solid #d1d5db; border-radius: 8px;
    padding: 10px 14px; font-family: 'DM Sans', sans-serif; font-size: 15px;
    color: var(--ink); outline: none; transition: border-color .2s;
  }

  input:focus { border-color: var(--teal); }

  .date-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

  /* Dropzone */
  .dropzone {
    border: 2px dashed #d1d5db; border-radius: 12px;
    padding: 32px; text-align: center; cursor: pointer;
    transition: all .2s; position: relative;
  }

  .dropzone:hover, .dropzone.dragover {
    border-color: var(--teal); background: #f0fdfc;
  }

  .dropzone input { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }

  .dropzone-icon { font-size: 36px; margin-bottom: 8px; }
  .dropzone-text { font-size: 14px; color: var(--gray); }
  .dropzone-text strong { color: var(--teal); }

  #preview-wrap { margin-top: 12px; display: none; }
  #preview-img  { max-height: 140px; border-radius: 8px; }

  /* Mensajes */
  .errors {
    background: #fef2f2; border: 1px solid #fca5a5; color: #dc2626;
    border-radius: 10px; padding: 14px 16px; margin-bottom: 24px;
    font-size: 14px;
  }
  .errors ul { margin-left: 18px; }

  .success-box {
    background: #f0fdf4; border: 1px solid #86efac; color: #166534;
    border-radius: 10px; padding: 14px 16px; margin-bottom: 24px;
    font-size: 14px;
  }

  .divider { border: none; border-top: 1px solid #f3f4f6; margin: 28px 0; }
</style>
</head>
<body>

<header>
  <div class="brand">USFQ · <span>Events CMS</span></div>
  <a href="index.php" class="btn btn-ghost">← Volver</a>
</header>

<main>
  <h1>Nuevo evento</h1>

  <?php if (!empty($errors)): ?>
    <div class="errors">
      <strong>Revisa los siguientes campos:</strong>
      <ul>
        <?php foreach ($errors as $err): ?>
          <li><?= htmlspecialchars($err) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="success-box">
      ✅ <strong>Evento guardado correctamente.</strong>
      <a href="index.php" style="color:inherit;text-decoration:underline">Ver todos los eventos</a>
      o sube otro abajo.
    </div>
  <?php endif; ?>

  <div class="card">
    <form method="POST" enctype="multipart/form-data">

      <div class="field">
        <label for="title">Título del evento</label>
        <input type="text" id="title" name="title"
               value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
               placeholder="Ej. Seminario de Biología Marina 2026" required>
      </div>

      <div class="field">
        <label for="event_type">Tipo</label>
        <select id="event_type" name="event_type" style="width:100%;border:1.5px solid #d1d5db;border-radius:8px;padding:10px 14px;font-family:'DM Sans',sans-serif;font-size:15px;color:var(--ink);background:#fff">
          <option value="evento" <?= ($_POST['event_type'] ?? '') === 'evento' ? 'selected' : '' ?>>Evento (afiche en la agenda)</option>
          <option value="importante" <?= ($_POST['event_type'] ?? '') === 'importante' ? 'selected' : '' ?>>Importante (destacado grande, rota arriba)</option>
        </select>
        <div class="hint">Los “importantes” ocupan el espacio destacado del kiosko con su afiche completo.</div>
      </div>

      <div id="destacado-fields" style="display:none">
        <div class="field">
          <label for="kicker">Antetítulo (kicker)</label>
          <input type="text" id="kicker" name="kicker" value="<?= htmlspecialchars($_POST['kicker'] ?? '') ?>" placeholder="Ej. Invitación · Lanzamiento de proyecto">
        </div>
        <div class="field">
          <label for="subtitle">Subtítulo</label>
          <input type="text" id="subtitle" name="subtitle" value="<?= htmlspecialchars($_POST['subtitle'] ?? '') ?>" placeholder="Una línea que describe el evento">
        </div>
        <div class="date-row">
          <div class="field">
            <label for="event_time">Hora</label>
            <input type="text" id="event_time" name="event_time" value="<?= htmlspecialchars($_POST['event_time'] ?? '') ?>" placeholder="Ej. 17:30">
          </div>
          <div class="field">
            <label for="cat">Categoría</label>
            <select id="cat" name="cat" style="width:100%;border:1.5px solid #d1d5db;border-radius:8px;padding:10px 14px;font-family:'DM Sans',sans-serif;font-size:15px;color:var(--ink);background:#fff">
              <?php foreach (['mar'=>'Ciencia y mar','deportes'=>'Deportes','bienestar'=>'Bienestar','arte'=>'Arte y cultura','letras'=>'Académico'] as $k=>$v): ?>
                <option value="<?= $k ?>" <?= ($_POST['cat'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="field">
          <label for="place">Lugar</label>
          <input type="text" id="place" name="place" value="<?= htmlspecialchars($_POST['place'] ?? '') ?>" placeholder="Ej. Salón Multiusos · USFQ Galápagos">
        </div>
        <div class="field">
          <label for="partners">Aliados / organizadores</label>
          <input type="text" id="partners" name="partners" value="<?= htmlspecialchars($_POST['partners'] ?? '') ?>" placeholder="Ej. Galápagos Life Fund · Galápagos Science Center">
        </div>
      </div>

      <div class="field">
        <label>Imagen del evento</label>
        <div class="dropzone" id="dropzone">
          <input type="file" name="image" id="image-input" accept="image/*" required>
          <div class="dropzone-icon">🖼</div>
          <div class="dropzone-text">
            <strong>Haz clic para seleccionar</strong> o arrastra aquí<br>
            JPG, PNG, WEBP, GIF — máx. 10 MB
          </div>
        </div>
        <div id="preview-wrap">
          <img id="preview-img" src="" alt="Vista previa">
        </div>
      </div>

      <div class="field">
        <label for="event_date">Fecha del evento</label>
        <input type="date" id="event_date" name="event_date"
               value="<?= htmlspecialchars($_POST['event_date'] ?? '') ?>" required>
        <div class="hint">El día que ocurre el evento.</div>
      </div>

      <hr class="divider">

      <div class="field">
        <label>Rango de publicación en pantallas</label>
        <div class="date-row">
          <div>
            <label for="publish_from" style="margin-top:0">Desde</label>
            <input type="date" id="publish_from" name="publish_from"
                   value="<?= htmlspecialchars($_POST['publish_from'] ?? date('Y-m-d')) ?>" required>
          </div>
          <div>
            <label for="publish_until" style="margin-top:0">Hasta</label>
            <input type="date" id="publish_until" name="publish_until"
                   value="<?= htmlspecialchars($_POST['publish_until'] ?? '') ?>" required>
          </div>
        </div>
        <div class="hint">Las pantallas mostrarán este evento solo dentro de este rango.</div>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">
        Guardar evento
      </button>

    </form>
  </div>
</main>

<script>
const input   = document.getElementById('image-input');
const preview = document.getElementById('preview-img');
const wrap    = document.getElementById('preview-wrap');
const zone    = document.getElementById('dropzone');

input.addEventListener('change', () => {
  const file = input.files[0];
  if (!file) return;
  preview.src = URL.createObjectURL(file);
  wrap.style.display = 'block';
});

zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('dragover'); });
zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
zone.addEventListener('drop', e => {
  e.preventDefault(); zone.classList.remove('dragover');
  if (e.dataTransfer.files[0]) {
    input.files = e.dataTransfer.files;
    input.dispatchEvent(new Event('change'));
  }
});

// Mostrar campos del destacado solo cuando el tipo es "importante"
const typeSel = document.getElementById('event_type');
const destFields = document.getElementById('destacado-fields');
function toggleDest(){ destFields.style.display = typeSel.value === 'importante' ? 'block' : 'none'; }
typeSel.addEventListener('change', toggleDest); toggleDest();
</script>

</body>
</html>
