<?php
/**
 * admin/staff.php — Reconocimiento al staff (cromos)
 * Tarjetas del personal que rotan en el espacio destacado del kiosko,
 * intercaladas con eventos y promos. Campaña actual: cromos Mundial 2026.
 */
require_once __DIR__ . '/../config.php';
requireAuth();

if (!defined('STAFF_FILE')) define('STAFF_FILE', DATA_DIR . '/staff.json');

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $staff  = array_values(loadJson(STAFF_FILE));

    if ($action === 'toggle' || $action === 'del') {
        $id = $_POST['id'] ?? '';
        foreach ($staff as $i => &$s) {
            if (($s['id'] ?? '') !== $id) continue;
            if ($action === 'toggle') { $s['enabled'] = !($s['enabled'] ?? true); $success = 'Estado actualizado.'; }
            else {
                if (!empty($s['image']) && is_file(UPLOAD_DIR . $s['image'])) unlink(UPLOAD_DIR . $s['image']);
                unset($staff[$i]); $success = 'Cromo eliminado.';
            }
            break;
        }
        unset($s);
        saveJson(STAFF_FILE, array_values($staff));
    }

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $role = trim($_POST['role'] ?? '');
        if (!$name) $errors[] = 'El nombre es obligatorio.';
        if (empty($_FILES['image']['name'])) $errors[] = 'Debes subir la imagen del cromo.';
        if (empty($errors)) {
            $file  = $_FILES['image'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($file['tmp_name']);
            if ($file['size'] > MAX_FILE_SIZE)       $errors[] = 'La imagen supera 10 MB.';
            if (!in_array($mime, ALLOWED_TYPES))     $errors[] = 'Formato no permitido (JPG, PNG, WEBP, GIF).';
        }
        if (empty($errors)) {
            $dir = UPLOAD_DIR . 'staff/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $id  = generateId();
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $rel = 'staff/' . $id . '.' . $ext;
            if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $rel)) {
                $errors[] = 'Error al guardar la imagen (permisos de uploads/).';
            } else {
                $staff[] = ['id' => $id, 'name' => $name, 'role' => $role, 'image' => $rel,
                            'enabled' => true, 'created_at' => date('c')];
                saveJson(STAFF_FILE, array_values($staff));
                $success = 'Cromo agregado.';
            }
        }
    }
}

$staff = array_values(loadJson(STAFF_FILE));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kiosko CMS — Staff</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="admin.css">
<style>
  main { max-width: 960px; margin: 0 auto; padding: 40px 24px; }
  h1   { font-size: 22px; font-weight: 600; margin-bottom: 8px; }
  .sub { font-size: 14px; color: var(--gray); margin-bottom: 28px; }
  .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 16px; margin-bottom: 36px; }
  .cromo { background: #fff; border-radius: 14px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.06); }
  .cromo.off { opacity: .45; }
  .cromo img { width: 100%; aspect-ratio: 2/3; object-fit: cover; display: block; }
  .cromo .bd { padding: 10px 12px; }
  .cromo .nm { font-size: 14px; font-weight: 600; }
  .cromo .rl { font-size: 12px; color: var(--gray); margin-top: 2px; min-height: 16px; }
  .cromo .acts { display: flex; gap: 8px; margin-top: 8px; }
  .cromo button { flex: 1; font-size: 11.5px; padding: 5px 0; border-radius: 6px; border: 1.5px solid #d1d5db;
    background: #fff; color: var(--gray); cursor: pointer; font-family: 'DM Sans', sans-serif; font-weight: 600; }
  .cromo button:hover { border-color: var(--teal); color: var(--teal); }
  .cromo button.danger:hover { border-color: #dc2626; color: #dc2626; }
  .card { background: #fff; border-radius: 16px; padding: 28px; box-shadow: 0 1px 4px rgba(0,0,0,.06); }
  .card h2 { font-size: 16px; font-weight: 600; margin-bottom: 18px; }
  .row3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; align-items: end; }
  label { display: block; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .08em; color: var(--gray); margin-bottom: 8px; }
  input[type=text], input[type=file] { width: 100%; border: 1.5px solid #d1d5db; border-radius: 8px; padding: 9px 12px;
    font-family: 'DM Sans', sans-serif; font-size: 14px; color: var(--ink); outline: none; }
  input:focus { border-color: var(--teal); }
  .btn-primary { background: var(--teal); color: #fff; font-size: 14px; padding: 11px 24px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-family: 'DM Sans', sans-serif; }
  .btn-primary:hover { background: var(--blue); }
  .errors { background: #fef2f2; border: 1px solid #fca5a5; color: #dc2626; border-radius: 10px; padding: 14px 16px; margin-bottom: 24px; font-size: 14px; }
  .success-box { background: #f0fdf4; border: 1px solid #86efac; color: #166534; border-radius: 10px; padding: 14px 16px; margin-bottom: 24px; font-size: 14px; }
</style>
</head>
<body>
<?php $NAV = 'staff'; include __DIR__ . '/_nav.php'; ?>

<main>
  <h1>Reconocimiento al staff</h1>
  <div class="sub">Los cromos <strong>activos</strong> rotan en el espacio destacado del kiosko, intercalados con eventos y convenios (3 al azar por ciclo).</div>

  <?php if ($errors): ?><div class="errors"><?= htmlspecialchars(implode(' ', $errors)) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="success-box">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>

  <div class="grid">
    <?php foreach ($staff as $s): ?>
      <div class="cromo<?= ($s['enabled'] ?? true) ? '' : ' off' ?>">
        <img src="<?= htmlspecialchars(UPLOAD_URL . $s['image']) ?>" alt="">
        <div class="bd">
          <div class="nm"><?= htmlspecialchars($s['name']) ?></div>
          <div class="rl"><?= htmlspecialchars($s['role'] ?? '') ?></div>
          <div class="acts">
            <form method="POST"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= htmlspecialchars($s['id']) ?>">
              <button type="submit"><?= ($s['enabled'] ?? true) ? 'Pausar' : 'Activar' ?></button></form>
            <form method="POST" onsubmit="return confirm('¿Eliminar este cromo?')"><input type="hidden" name="action" value="del"><input type="hidden" name="id" value="<?= htmlspecialchars($s['id']) ?>">
              <button type="submit" class="danger">Eliminar</button></form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$staff): ?><div class="sub">Aún no hay cromos. Agrega el primero abajo.</div><?php endif; ?>
  </div>

  <div class="card">
    <h2>Agregar cromo</h2>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="add">
      <div class="row3">
        <div><label for="name">Nombre</label><input type="text" id="name" name="name" placeholder="Ej. JULI" required></div>
        <div><label for="role">Puesto</label><input type="text" id="role" name="role" placeholder="Ej. Director Técnico"></div>
        <div><label for="image">Imagen (vertical 2:3)</label><input type="file" id="image" name="image" accept="image/*" required></div>
      </div>
      <div style="margin-top:18px"><button type="submit" class="btn-primary">Agregar cromo</button></div>
    </form>
  </div>
</main>
</body>
</html>
