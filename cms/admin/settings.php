<?php
/**
 * admin/settings.php — Ajustes: API keys de Stormglass (con failover)
 * Se guardan en data/settings.json. El cron usa la key 1; si falla
 * (quota agotada, key inválida, error de red) intenta con la key 2.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/marine.php';
requireAuth();

$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $k1 = trim($_POST['key1'] ?? '');
    $k2 = trim($_POST['key2'] ?? '');
    $settings = loadJson(SETTINGS_FILE);
    $settings['stormglass_keys'] = array_values(array_filter([$k1, $k2]));
    $settings['openuv_key'] = trim($_POST['openuv_key'] ?? '');
    saveJson(SETTINGS_FILE, $settings);
    $success = true;
}

$settings = loadJson(SETTINGS_FILE);
$keys = $settings['stormglass_keys'] ?? [];
$marine = loadJson(MARINE_FILE);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kiosko CMS — Ajustes</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="admin.css">
<style>
  main { max-width: 640px; margin: 0 auto; padding: 40px 24px; }
  h1   { font-size: 22px; font-weight: 600; margin-bottom: 28px; }
  .card { background: #fff; border-radius: 16px; padding: 32px; box-shadow: 0 1px 4px rgba(0,0,0,.06); }
  .field { margin-bottom: 24px; }
  label { display: block; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .08em; color: var(--gray); margin-bottom: 8px; }
  .hint { font-size: 12px; color: #9ca3af; margin-top: 4px; }
  input[type=text] { width: 100%; border: 1.5px solid #d1d5db; border-radius: 8px; padding: 10px 14px;
    font-family: 'DM Mono', monospace; font-size: 13px; color: var(--ink); outline: none; transition: border-color .2s; }
  input:focus { border-color: var(--teal); }
  .success-box { background: #f0fdf4; border: 1px solid #86efac; color: #166534; border-radius: 10px; padding: 14px 16px; margin-bottom: 24px; font-size: 14px; }
  .status { background: var(--mist); border-radius: 10px; padding: 14px 16px; margin-bottom: 28px; font-size: 13px; color: var(--gray); line-height: 1.6; }
  .status b { color: var(--ink); }
  .btn-primary { background: var(--teal); color: #fff; font-size: 15px; padding: 12px 28px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-family: 'DM Sans', sans-serif; }
  .btn-primary:hover { background: var(--blue); }
</style>
</head>
<body>
<?php $NAV = 'settings'; include __DIR__ . '/_nav.php'; ?>

<main>
  <h1>Ajustes — Estado del mar (Stormglass)</h1>

  <?php if ($success): ?>
    <div class="success-box">✅ <strong>Keys guardadas.</strong> El próximo cron las usará (corre cada 3 horas).</div>
  <?php endif; ?>

  <div class="status">
    <b>Último dato del mar:</b> <?= htmlspecialchars($marine['updated_at'] ?? 'sin datos') ?><br>
    <b>Fuentes:</b> mareas → <?= htmlspecialchars($marine['sources']['tides'] ?? '—') ?> ·
    estado del mar → <?= htmlspecialchars($marine['sources']['stormglass'] ?? '—') ?><br>
    <b>Quota Stormglass:</b> 10 llamadas/día por key (el cron usa 8/día). Con dos keys hay respaldo automático.
  </div>

  <div class="card">
    <form method="POST">
      <div class="field">
        <label for="key1">API key 1 (principal)</label>
        <input type="text" id="key1" name="key1" value="<?= htmlspecialchars($keys[0] ?? '') ?>" placeholder="xxxxxxxx-xxxx-…">
        <div class="hint">Se usa siempre primero.</div>
      </div>
      <div class="field">
        <label for="key2">API key 2 (respaldo)</label>
        <input type="text" id="key2" name="key2" value="<?= htmlspecialchars($keys[1] ?? '') ?>" placeholder="xxxxxxxx-xxxx-…">
        <div class="hint">Solo se usa si la key 1 falla (quota agotada o key inválida).</div>
      </div>
      <div class="field">
        <label for="openuv_key">OpenUV API key (respaldo del índice UV)</label>
        <input type="text" id="openuv_key" name="openuv_key" value="<?= htmlspecialchars($settings['openuv_key'] ?? '') ?>" placeholder="openuv-…">
        <div class="hint">El UV viene de Open-Meteo (gratis, sin key). OpenUV solo se usa si Open-Meteo falla. 50 llamadas/día.</div>
      </div>
      <button type="submit" class="btn-primary" style="width:100%">Guardar keys</button>
    </form>
  </div>
</main>
</body>
</html>
