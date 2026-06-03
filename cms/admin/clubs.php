<?php
require_once __DIR__ . '/../config.php';
requireAuth();

$CATS = ['deportes' => 'Deportes', 'bienestar' => 'Bienestar', 'mar' => 'Ciencia y mar', 'arte' => 'Arte y cultura', 'letras' => 'Académico'];
$errors = [];
$success = '';
$clubs = loadClubs();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $idx    = isset($_POST['idx']) && $_POST['idx'] !== '' ? (int)$_POST['idx'] : null;

    if ($action === 'delete' && $idx !== null && isset($clubs[$idx])) {
        array_splice($clubs, $idx, 1);
        saveClubs($clubs);
        $success = 'Club eliminado.';
    } elseif ($action === 'toggle' && $idx !== null && isset($clubs[$idx])) {
        $clubs[$idx]['enabled'] = !($clubs[$idx]['enabled'] ?? true);
        saveClubs($clubs);
        $success = 'Visibilidad actualizada.';
    } elseif ($action === 'save') {
        $club = [
            'name'    => trim($_POST['name'] ?? ''),
            'cat'     => in_array($_POST['cat'] ?? '', array_keys($CATS), true) ? $_POST['cat'] : 'letras',
            'days'    => trim($_POST['days'] ?? ''),
            'time'    => trim($_POST['time'] ?? ''),
            'place'   => trim($_POST['place'] ?? ''),
            'enabled' => true,
        ];
        if ($club['name'] === '') $errors[] = 'El nombre del club es obligatorio.';
        if (empty($errors)) {
            if ($idx !== null && isset($clubs[$idx])) {
                $club['enabled'] = $clubs[$idx]['enabled'] ?? true;
                $clubs[$idx] = $club;
                $success = 'Club actualizado.';
            } else {
                $clubs[] = $club;
                $success = 'Club agregado.';
            }
            saveClubs($clubs);
        }
    }
    $clubs = loadClubs();
}

// Prefill para edición
$editIdx = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
$edit = ($editIdx !== null && isset($clubs[$editIdx])) ? $clubs[$editIdx] : null;
$NAV = 'clubs';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kiosko CMS — Clubes</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="admin.css">
</head>
<body>
<?php require __DIR__ . '/_nav.php'; ?>
<main>
  <div class="page-title">Clubes</div>
  <div class="page-sub">Lista permanente de clubes que aparece en la columna izquierda del kiosko.</div>

  <?php if ($errors): ?><div class="errors"><ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <?php if ($success): ?><div class="success-box">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>

  <div class="card">
    <h2><?= $edit ? 'Editar club' : 'Agregar club' ?></h2>
    <form method="POST">
      <input type="hidden" name="action" value="save">
      <?php if ($edit !== null): ?><input type="hidden" name="idx" value="<?= $editIdx ?>"><?php endif; ?>
      <div class="row-2">
        <div class="field">
          <label for="name">Nombre</label>
          <input type="text" id="name" name="name" value="<?= htmlspecialchars($edit['name'] ?? '') ?>" placeholder="Ej. Club de Buceo" required>
        </div>
        <div class="field">
          <label for="cat">Categoría</label>
          <select id="cat" name="cat">
            <?php foreach ($CATS as $k => $label): ?>
              <option value="<?= $k ?>" <?= ($edit['cat'] ?? '') === $k ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="row-3">
        <div class="field">
          <label for="days">Días</label>
          <input type="text" id="days" name="days" value="<?= htmlspecialchars($edit['days'] ?? '') ?>" placeholder="Ej. Lun · Mié · Vie">
        </div>
        <div class="field">
          <label for="time">Hora</label>
          <input type="text" id="time" name="time" value="<?= htmlspecialchars($edit['time'] ?? '') ?>" placeholder="Ej. 18:00">
        </div>
        <div class="field">
          <label for="place">Lugar</label>
          <input type="text" id="place" name="place" value="<?= htmlspecialchars($edit['place'] ?? '') ?>" placeholder="Ej. Terraza USFQ">
        </div>
      </div>
      <button type="submit" class="btn btn-primary"><?= $edit ? 'Guardar cambios' : '+ Agregar club' ?></button>
      <?php if ($edit): ?><a href="clubs.php" class="btn btn-ghost">Cancelar</a><?php endif; ?>
    </form>
  </div>

  <div class="table-wrap">
    <table>
      <thead><tr><th>Club</th><th>Categoría</th><th>Días</th><th>Hora</th><th>Lugar</th><th>Estado</th><th>Acciones</th></tr></thead>
      <tbody>
        <?php if (!$clubs): ?>
          <tr><td colspan="7"><div class="empty">No hay clubes aún. Agrega el primero arriba.</div></td></tr>
        <?php else: foreach ($clubs as $i => $c): $on = $c['enabled'] ?? true; ?>
          <tr>
            <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
            <td><span class="cat-chip cat-<?= htmlspecialchars($c['cat'] ?? 'letras') ?>"><?= htmlspecialchars($CATS[$c['cat'] ?? 'letras'] ?? $c['cat']) ?></span></td>
            <td><?= htmlspecialchars($c['days'] ?? '') ?></td>
            <td class="mono"><?= htmlspecialchars($c['time'] ?? '') ?></td>
            <td style="color:var(--gray)"><?= htmlspecialchars($c['place'] ?? '') ?></td>
            <td><?= $on ? '<span class="badge badge-active">Visible</span>' : '<span class="badge badge-off">Oculto</span>' ?></td>
            <td>
              <div class="actions">
                <a href="clubs.php?edit=<?= $i ?>" class="btn btn-ghost btn-sm">Editar</a>
                <form method="POST" style="display:inline"><input type="hidden" name="action" value="toggle"><input type="hidden" name="idx" value="<?= $i ?>"><button class="btn btn-ghost btn-sm"><?= $on ? 'Ocultar' : 'Mostrar' ?></button></form>
                <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar este club?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="idx" value="<?= $i ?>"><button class="btn btn-danger btn-sm">Borrar</button></form>
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
