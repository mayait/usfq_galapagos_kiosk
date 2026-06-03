<?php
require_once __DIR__ . '/../config.php';
requireAuth();

$ACCENTS = [
    'var(--color-coral)'      => 'Coral',
    'var(--color-turquesa)'   => 'Turquesa',
    'var(--color-azul-medio)' => 'Azul',
    'var(--color-arena)'      => 'Arena',
];
$errors = [];
$success = '';
$promos = loadPromos();

/** Sube una imagen opcional a uploads/promos/ y devuelve la ruta relativa o null. */
function uploadPromoImage(array &$errors): ?string {
    if (empty($_FILES['image']['name'])) return null;
    $file  = $_FILES['image'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if ($file['size'] > MAX_FILE_SIZE)     { $errors[] = 'La imagen supera 10 MB.'; return null; }
    if (!in_array($mime, ALLOWED_TYPES))   { $errors[] = 'Formato no permitido (JPG, PNG, WEBP, GIF).'; return null; }
    $dir = UPLOAD_DIR . 'promos/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
    $name = generateId() . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . $name)) { $errors[] = 'Error al guardar la imagen.'; return null; }
    return 'promos/' . $name;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $idx    = isset($_POST['idx']) && $_POST['idx'] !== '' ? (int)$_POST['idx'] : null;

    if ($action === 'delete' && $idx !== null && isset($promos[$idx])) {
        array_splice($promos, $idx, 1); savePromos($promos); $success = 'Convenio eliminado.';
    } elseif ($action === 'toggle' && $idx !== null && isset($promos[$idx])) {
        $promos[$idx]['enabled'] = !($promos[$idx]['enabled'] ?? true); savePromos($promos); $success = 'Visibilidad actualizada.';
    } elseif ($action === 'save') {
        $img = uploadPromoImage($errors);
        $p = [
            'kind'         => ($_POST['kind'] ?? 'convenio') === 'promo' ? 'promo' : 'convenio',
            'label'        => trim($_POST['label'] ?? ''),
            'audience'     => trim($_POST['audience'] ?? ''),
            'name'         => trim($_POST['name'] ?? ''),
            'tagline'      => trim($_POST['tagline'] ?? ''),
            'discount'     => trim($_POST['discount'] ?? ''),
            'discountNote' => trim($_POST['discountNote'] ?? ''),
            'category'     => trim($_POST['category'] ?? ''),
            'place'        => trim($_POST['place'] ?? ''),
            'terms'        => trim($_POST['terms'] ?? ''),
            'accent'       => isset($ACCENTS[$_POST['accent'] ?? '']) ? $_POST['accent'] : 'var(--color-coral)',
            'enabled'      => true,
        ];
        if ($p['name'] === '') $errors[] = 'El nombre es obligatorio.';
        if (!$p['label']) $p['label'] = $p['kind'] === 'promo' ? 'Promoción' : 'Convenio';
        if (empty($errors)) {
            if ($idx !== null && isset($promos[$idx])) {
                $p['enabled'] = $promos[$idx]['enabled'] ?? true;
                $p['image']   = $img ?: ($promos[$idx]['image'] ?? null);
                $promos[$idx] = array_filter($p, fn($v) => $v !== null);
                $success = 'Convenio actualizado.';
            } else {
                if ($img) $p['image'] = $img;
                $promos[] = $p;
                $success = 'Convenio agregado.';
            }
            savePromos($promos);
        }
    }
    $promos = loadPromos();
}

$editIdx = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
$edit = ($editIdx !== null && isset($promos[$editIdx])) ? $promos[$editIdx] : null;
$NAV = 'promos';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kiosko CMS — Convenios y promociones</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="admin.css">
</head>
<body>
<?php require __DIR__ . '/_nav.php'; ?>
<main>
  <div class="page-title">Convenios y promociones</div>
  <div class="page-sub">Beneficios para la comunidad USFQ que rotan en el espacio destacado del kiosko.</div>

  <?php if ($errors): ?><div class="errors"><ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <?php if ($success): ?><div class="success-box">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>

  <div class="card">
    <h2><?= $edit ? 'Editar convenio / promoción' : 'Agregar convenio / promoción' ?></h2>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="save">
      <?php if ($edit !== null): ?><input type="hidden" name="idx" value="<?= $editIdx ?>"><?php endif; ?>
      <div class="row-3">
        <div class="field">
          <label for="kind">Tipo</label>
          <select id="kind" name="kind">
            <option value="convenio" <?= ($edit['kind'] ?? '') === 'convenio' ? 'selected' : '' ?>>Convenio (alianza externa)</option>
            <option value="promo" <?= ($edit['kind'] ?? '') === 'promo' ? 'selected' : '' ?>>Promoción (interna)</option>
          </select>
        </div>
        <div class="field">
          <label for="audience">Audiencia</label>
          <input type="text" id="audience" name="audience" value="<?= htmlspecialchars($edit['audience'] ?? 'Comunidad USFQ') ?>" placeholder="Comunidad USFQ">
        </div>
        <div class="field">
          <label for="category">Categoría</label>
          <input type="text" id="category" name="category" value="<?= htmlspecialchars($edit['category'] ?? '') ?>" placeholder="Ej. Gastronomía">
        </div>
      </div>
      <div class="row-2">
        <div class="field">
          <label for="name">Nombre</label>
          <input type="text" id="name" name="name" value="<?= htmlspecialchars($edit['name'] ?? '') ?>" placeholder="Ej. Midori Sushi Pub" required>
        </div>
        <div class="field">
          <label for="tagline">Descripción corta</label>
          <input type="text" id="tagline" name="tagline" value="<?= htmlspecialchars($edit['tagline'] ?? '') ?>" placeholder="Sushi, café y postres">
        </div>
      </div>
      <div class="row-3">
        <div class="field">
          <label for="discount">Descuento / oferta</label>
          <input type="text" id="discount" name="discount" value="<?= htmlspecialchars($edit['discount'] ?? '') ?>" placeholder="Ej. 7%  ó  2×1">
        </div>
        <div class="field">
          <label for="discountNote">Nota del descuento</label>
          <input type="text" id="discountNote" name="discountNote" value="<?= htmlspecialchars($edit['discountNote'] ?? '') ?>" placeholder="de descuento">
        </div>
        <div class="field">
          <label for="accent">Color de acento</label>
          <select id="accent" name="accent">
            <?php foreach ($ACCENTS as $val => $label): ?>
              <option value="<?= htmlspecialchars($val) ?>" <?= ($edit['accent'] ?? 'var(--color-coral)') === $val ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="row-2">
        <div class="field">
          <label for="place">Lugar</label>
          <input type="text" id="place" name="place" value="<?= htmlspecialchars($edit['place'] ?? '') ?>" placeholder="Av. Charles Darwin · Pto. Baquerizo Moreno">
        </div>
        <div class="field">
          <label for="terms">Condiciones</label>
          <input type="text" id="terms" name="terms" value="<?= htmlspecialchars($edit['terms'] ?? '') ?>" placeholder="Presenta tu credencial USFQ vigente">
        </div>
      </div>
      <div class="field">
        <label>Foto del local / promo <?= $edit ? '(deja vacío para conservar la actual)' : '' ?></label>
        <input type="file" name="image" accept="image/*">
        <?php if (!empty($edit['image'])): ?><div class="hint">Actual: <?= htmlspecialchars($edit['image']) ?></div><?php endif; ?>
      </div>
      <button type="submit" class="btn btn-primary"><?= $edit ? 'Guardar cambios' : '+ Agregar' ?></button>
      <?php if ($edit): ?><a href="promos.php" class="btn btn-ghost">Cancelar</a><?php endif; ?>
    </form>
  </div>

  <div class="table-wrap">
    <table>
      <thead><tr><th>Foto</th><th>Nombre</th><th>Tipo</th><th>Oferta</th><th>Estado</th><th>Acciones</th></tr></thead>
      <tbody>
        <?php if (!$promos): ?>
          <tr><td colspan="6"><div class="empty">No hay convenios aún.</div></td></tr>
        <?php else: foreach ($promos as $i => $p): $on = $p['enabled'] ?? true; ?>
          <tr>
            <td><?php if (!empty($p['image'])): ?><img class="thumb" src="<?= htmlspecialchars(UPLOAD_URL . $p['image']) ?>" alt=""><?php else: ?>—<?php endif; ?></td>
            <td><strong><?= htmlspecialchars($p['name']) ?></strong><br><span style="color:var(--gray);font-size:12px"><?= htmlspecialchars($p['tagline'] ?? '') ?></span></td>
            <td><span class="badge <?= ($p['kind'] ?? '') === 'promo' ? 'badge-future' : 'badge-imp' ?>"><?= htmlspecialchars($p['label'] ?? $p['kind'] ?? '') ?></span></td>
            <td class="mono"><?= htmlspecialchars($p['discount'] ?? '') ?></td>
            <td><?= $on ? '<span class="badge badge-active">Visible</span>' : '<span class="badge badge-off">Oculto</span>' ?></td>
            <td>
              <div class="actions">
                <a href="promos.php?edit=<?= $i ?>" class="btn btn-ghost btn-sm">Editar</a>
                <form method="POST" style="display:inline"><input type="hidden" name="action" value="toggle"><input type="hidden" name="idx" value="<?= $i ?>"><button class="btn btn-ghost btn-sm"><?= $on ? 'Ocultar' : 'Mostrar' ?></button></form>
                <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="idx" value="<?= $i ?>"><button class="btn btn-danger btn-sm">Borrar</button></form>
              </div>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</main>
</body>
</html>
