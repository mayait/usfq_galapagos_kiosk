<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/ics.php';
requireAuth();

$errors = [];
$success = '';
$cals = loadCalendars();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $idx    = isset($_POST['idx']) && $_POST['idx'] !== '' ? (int)$_POST['idx'] : null;

    if ($action === 'delete' && $idx !== null && isset($cals[$idx])) {
        array_splice($cals, $idx, 1); saveCalendars($cals); $success = 'Calendario eliminado.';
    } elseif ($action === 'toggle' && $idx !== null && isset($cals[$idx])) {
        $cals[$idx]['enabled'] = !($cals[$idx]['enabled'] ?? true); saveCalendars($cals); $success = 'Estado actualizado.';
    } elseif ($action === 'save') {
        $cal = [
            'id'      => trim($_POST['name'] ?? '') !== '' ? strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($_POST['name']))) : 'cal-' . substr(generateId(), 0, 4),
            'name'    => trim($_POST['name'] ?? ''),
            'url'     => trim($_POST['url'] ?? ''),
            'enabled' => true,
        ];
        if ($cal['name'] === '') $errors[] = 'El nombre es obligatorio.';
        if ($cal['url'] === '' || !filter_var($cal['url'], FILTER_VALIDATE_URL)) $errors[] = 'La URL del ICS no es válida.';
        if (empty($errors)) {
            if ($idx !== null && isset($cals[$idx])) {
                $cal['enabled'] = $cals[$idx]['enabled'] ?? true;
                $cal['id']      = $cals[$idx]['id'] ?? $cal['id'];
                $cals[$idx] = $cal; $success = 'Calendario actualizado.';
            } else { $cals[] = $cal; $success = 'Calendario agregado.'; }
            saveCalendars($cals);
            @unlink(ICS_CACHE_FILE);   // invalida la caché para reflejar el cambio
        }
    }
    $cals = loadCalendars();
}

// Probar un calendario (cuenta eventos de la semana)
$testResult = null;
if (isset($_GET['test'])) {
    $ti = (int)$_GET['test'];
    if (isset($cals[$ti])) {
        $raw = fetchCalendarRaw($cals[$ti]['url']);
        $testResult = $raw ? ['ok' => true, 'n' => count(parseICS($raw))] : ['ok' => false];
        $testName = $cals[$ti]['name'];
    }
}

$editIdx = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
$edit = ($editIdx !== null && isset($cals[$editIdx])) ? $cals[$editIdx] : null;
$NAV = 'calendars';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kiosko CMS — Calendarios</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="admin.css">
</head>
<body>
<?php require __DIR__ . '/_nav.php'; ?>
<main>
  <div class="page-title">Calendarios</div>
  <div class="page-sub">Fuentes ICS (Outlook u otras) que el API ingiere para armar la agenda semanal del kiosko.</div>

  <?php if ($errors): ?><div class="errors"><ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <?php if ($success): ?><div class="success-box">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($testResult): ?>
    <div class="<?= $testResult['ok'] ? 'success-box' : 'errors' ?>">
      <?= $testResult['ok'] ? '✅ <strong>' . htmlspecialchars($testName) . '</strong> respondió correctamente — ' . (int)$testResult['n'] . ' eventos en el feed.' : '⚠️ No se pudo leer el ICS (¿URL incorrecta o sin BEGIN:VCALENDAR?).' ?>
    </div>
  <?php endif; ?>

  <div class="card">
    <h2><?= $edit ? 'Editar calendario' : 'Agregar calendario' ?></h2>
    <form method="POST">
      <input type="hidden" name="action" value="save">
      <?php if ($edit !== null): ?><input type="hidden" name="idx" value="<?= $editIdx ?>"><?php endif; ?>
      <div class="field">
        <label for="name">Nombre</label>
        <input type="text" id="name" name="name" value="<?= htmlspecialchars($edit['name'] ?? '') ?>" placeholder="Ej. USFQ Galápagos (Outlook)" required>
      </div>
      <div class="field">
        <label for="url">URL del archivo ICS</label>
        <input type="url" id="url" name="url" value="<?= htmlspecialchars($edit['url'] ?? '') ?>" placeholder="https://outlook.office365.com/owa/calendar/.../calendar.ics" required>
        <div class="hint">Calendario público de Outlook 365 (u otro proveedor que entregue .ics).</div>
      </div>
      <button type="submit" class="btn btn-primary"><?= $edit ? 'Guardar cambios' : '+ Agregar' ?></button>
      <?php if ($edit): ?><a href="calendars.php" class="btn btn-ghost">Cancelar</a><?php endif; ?>
    </form>
  </div>

  <div class="table-wrap">
    <table>
      <thead><tr><th>Nombre</th><th>URL</th><th>Estado</th><th>Acciones</th></tr></thead>
      <tbody>
        <?php if (!$cals): ?>
          <tr><td colspan="4"><div class="empty">No hay calendarios configurados.</div></td></tr>
        <?php else: foreach ($cals as $i => $c): $on = $c['enabled'] ?? true; ?>
          <tr>
            <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
            <td style="max-width:420px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--gray);font-size:12px" class="mono"><?= htmlspecialchars($c['url']) ?></td>
            <td><?= $on ? '<span class="badge badge-active">Activo</span>' : '<span class="badge badge-off">Inactivo</span>' ?></td>
            <td>
              <div class="actions">
                <a href="calendars.php?test=<?= $i ?>" class="btn btn-ghost btn-sm">Probar</a>
                <a href="calendars.php?edit=<?= $i ?>" class="btn btn-ghost btn-sm">Editar</a>
                <form method="POST" style="display:inline"><input type="hidden" name="action" value="toggle"><input type="hidden" name="idx" value="<?= $i ?>"><button class="btn btn-ghost btn-sm"><?= $on ? 'Desactivar' : 'Activar' ?></button></form>
                <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar este calendario?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="idx" value="<?= $i ?>"><button class="btn btn-danger btn-sm">Borrar</button></form>
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
