<?php
require_once __DIR__ . '/../config.php';
requireAuth();

$events = loadEvents();

// Ordenar por fecha de evento ascendente
usort($events, fn($a, $b) => strcmp($a['event_date'], $b['event_date']));

$today = date('Y-m-d');
$NAV = 'events';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kiosko CMS — Eventos</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="admin.css">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --teal:  #00A9A8;
    --blue:  #1672A3;
    --ink:   #0f1923;
    --mist:  #f0f4f7;
    --green: #059669;
    --amber: #d97706;
    --gray:  #6b7280;
  }

  body { font-family: 'DM Sans', sans-serif; background: var(--mist); color: var(--ink); }

  /* ── Header ── */
  header {
    background: #fff;
    border-bottom: 1px solid #e5e7eb;
    padding: 0 32px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 60px;
    position: sticky; top: 0; z-index: 10;
  }

  .brand { font-family: 'DM Mono', monospace; font-size: 13px; color: var(--teal); letter-spacing: .1em; }
  .brand span { color: var(--gray); }

  nav { display: flex; gap: 12px; align-items: center; }

  .btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border-radius: 8px; font-size: 13px;
    font-weight: 600; cursor: pointer; text-decoration: none;
    border: none; font-family: 'DM Sans', sans-serif;
    transition: all .15s;
  }

  .btn-primary { background: var(--teal); color: #fff; }
  .btn-primary:hover { background: var(--blue); }
  .btn-ghost { background: transparent; color: var(--gray); border: 1.5px solid #d1d5db; }
  .btn-ghost:hover { border-color: var(--teal); color: var(--teal); }
  .btn-danger { background: #fef2f2; color: #dc2626; border: 1.5px solid #fca5a5; }
  .btn-danger:hover { background: #dc2626; color: #fff; }

  /* ── Layout ── */
  main { max-width: 1100px; margin: 0 auto; padding: 32px 24px; }

  .page-title {
    font-size: 24px; font-weight: 600; margin-bottom: 8px;
  }

  .page-sub {
    font-size: 14px; color: var(--gray); margin-bottom: 28px;
  }

  /* ── Stats ── */
  .stats { display: flex; gap: 16px; margin-bottom: 32px; flex-wrap: wrap; }

  .stat-card {
    background: #fff; border-radius: 12px; padding: 20px 24px;
    flex: 1; min-width: 140px;
    box-shadow: 0 1px 4px rgba(0,0,0,.06);
  }

  .stat-label { font-size: 11px; text-transform: uppercase; letter-spacing: .1em; color: var(--gray); margin-bottom: 6px; }
  .stat-value { font-size: 28px; font-weight: 600; color: var(--ink); font-family: 'DM Mono', monospace; }

  /* ── Table ── */
  .table-wrap { background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.06); }

  table { width: 100%; border-collapse: collapse; }

  thead { background: var(--ink); }
  thead th {
    padding: 14px 16px; text-align: left;
    font-size: 11px; letter-spacing: .1em; text-transform: uppercase;
    color: rgba(255,255,255,.7); font-weight: 500;
  }

  tbody tr { border-bottom: 1px solid #f3f4f6; transition: background .1s; }
  tbody tr:last-child { border-bottom: none; }
  tbody tr:hover { background: #f9fafb; }

  td { padding: 14px 16px; font-size: 14px; vertical-align: middle; }

  .thumb {
    width: 60px; height: 40px; object-fit: cover;
    border-radius: 6px; display: block;
  }

  .badge {
    display: inline-block; padding: 3px 10px; border-radius: 20px;
    font-size: 11px; font-weight: 600; letter-spacing: .04em;
  }

  .badge-active  { background: #d1fae5; color: #065f46; }
  .badge-future  { background: #dbeafe; color: #1e40af; }
  .badge-expired { background: #f3f4f6; color: var(--gray); }

  .actions { display: flex; gap: 8px; }

  /* ── Empty state ── */
  .empty { text-align: center; padding: 64px 24px; color: var(--gray); }
  .empty-icon { font-size: 48px; margin-bottom: 12px; }
  .empty p { font-size: 15px; }

  /* ── API hint ── */
  .api-hint {
    margin-top: 32px;
    background: #f0f9f9;
    border: 1px solid #a7f3d0;
    border-radius: 12px;
    padding: 16px 20px;
    font-size: 13px;
  }

  .api-hint strong { color: var(--teal); }
  .api-hint code {
    font-family: 'DM Mono', monospace;
    background: #fff;
    padding: 2px 8px;
    border-radius: 4px;
    border: 1px solid #d1fae5;
    font-size: 12px;
  }
</style>
</head>
<body>

<?php require __DIR__ . '/_nav.php'; ?>

<main>
  <div style="display:flex;align-items:center;justify-content:space-between">
    <div>
      <div class="page-title">Eventos</div>
      <div class="page-sub">Afiches que aparecen en la agenda y en el espacio destacado del kiosko.</div>
    </div>
    <a href="upload.php" class="btn btn-primary">+ Nuevo evento</a>
  </div>

  <?php
    $active  = array_filter($events, fn($e) => $e['publish_from'] <= $today && $e['publish_until'] >= $today);
    $future  = array_filter($events, fn($e) => $e['publish_from'] > $today);
    $expired = array_filter($events, fn($e) => $e['publish_until'] < $today);
  ?>

  <div class="stats">
    <div class="stat-card">
      <div class="stat-label">Total</div>
      <div class="stat-value"><?= count($events) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Activos hoy</div>
      <div class="stat-value" style="color:var(--green)"><?= count($active) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Próximos</div>
      <div class="stat-value" style="color:var(--blue)"><?= count($future) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Expirados</div>
      <div class="stat-value" style="color:var(--gray)"><?= count($expired) ?></div>
    </div>
  </div>

  <?php if (empty($events)): ?>
    <div class="table-wrap">
      <div class="empty">
        <div class="empty-icon">📅</div>
        <p>No hay eventos aún. <a href="upload.php" style="color:var(--teal)">Sube el primero.</a></p>
      </div>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Imagen</th>
            <th>Título</th>
            <th>Tipo</th>
            <th>Fecha del evento</th>
            <th>Publicación</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($events as $e):
            if ($e['publish_from'] <= $today && $e['publish_until'] >= $today) {
                $badge = '<span class="badge badge-active">Activo</span>';
            } elseif ($e['publish_from'] > $today) {
                $badge = '<span class="badge badge-future">Próximo</span>';
            } else {
                $badge = '<span class="badge badge-expired">Expirado</span>';
            }
          ?>
          <tr>
            <td>
              <img src="<?= htmlspecialchars(UPLOAD_URL . $e['image']) ?>"
                   alt="" class="thumb">
            </td>
            <td><?= htmlspecialchars($e['title']) ?></td>
            <td><?= ($e['event_type'] ?? 'evento') === 'importante'
                ? '<span class="badge badge-imp">Importante</span>'
                : '<span class="badge badge-evt">Evento</span>' ?></td>
            <td style="font-family:'DM Mono',monospace;font-size:13px">
              <?= htmlspecialchars($e['event_date']) ?>
            </td>
            <td style="font-family:'DM Mono',monospace;font-size:12px;color:var(--gray)">
              <?= htmlspecialchars($e['publish_from']) ?> →
              <?= htmlspecialchars($e['publish_until']) ?>
            </td>
            <td><?= $badge ?></td>
            <td>
              <div class="actions">
                <a href="edit.php?id=<?= urlencode($e['id']) ?>" class="btn btn-ghost">Editar</a>
                <a href="delete.php?id=<?= urlencode($e['id']) ?>"
                   class="btn btn-danger"
                   onclick="return confirm('¿Eliminar este evento?')">Borrar</a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <div class="api-hint">
    <strong>Feed del kiosko:</strong>
    <code><?= SITE_URL ?>/api/kiosk.php</code>
    &nbsp;—&nbsp; reúne eventos, clubes, convenios y mareas en un solo JSON.
    <br><span style="color:var(--gray)">Compatibilidad: <code><?= SITE_URL ?>/api/events.php</code> sigue disponible (solo afiches).</span>
  </div>
</main>

</body>
</html>
