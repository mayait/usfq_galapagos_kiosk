<?php
require_once __DIR__ . '/../config.php';
startSecureSession();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';

    if ($user === ADMIN_USER && $pass === ADMIN_PASSWORD) {
        $_SESSION['authenticated'] = true;
        header('Location: ' . SITE_URL . '/admin/index.php');
        exit;
    } else {
        $error = 'Usuario o contraseña incorrectos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Events CMS — Acceso</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --teal:   #00A9A8;
    --blue:   #1672A3;
    --ink:    #0f1923;
    --mist:   #f0f4f7;
    --danger: #e05050;
  }

  body {
    font-family: 'DM Sans', sans-serif;
    background: var(--mist);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .card {
    background: #fff;
    border-radius: 16px;
    padding: 48px 40px;
    width: 100%;
    max-width: 380px;
    box-shadow: 0 4px 32px rgba(0,0,0,.08);
  }

  .logo {
    font-family: 'DM Mono', monospace;
    font-size: 11px;
    letter-spacing: .15em;
    color: var(--teal);
    text-transform: uppercase;
    margin-bottom: 8px;
  }

  h1 {
    font-size: 22px;
    font-weight: 600;
    color: var(--ink);
    margin-bottom: 32px;
  }

  label {
    display: block;
    font-size: 12px;
    font-weight: 500;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: .08em;
    margin-bottom: 6px;
  }

  input[type=text], input[type=password] {
    width: 100%;
    border: 1.5px solid #d1d5db;
    border-radius: 8px;
    padding: 10px 14px;
    font-family: 'DM Mono', monospace;
    font-size: 14px;
    color: var(--ink);
    outline: none;
    transition: border-color .2s;
    margin-bottom: 20px;
  }

  input:focus { border-color: var(--teal); }

  .error {
    background: #fef2f2;
    border: 1px solid #fca5a5;
    color: var(--danger);
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 13px;
    margin-bottom: 20px;
  }

  button {
    width: 100%;
    background: var(--teal);
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 12px;
    font-family: 'DM Sans', sans-serif;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: background .2s;
  }

  button:hover { background: var(--blue); }
</style>
</head>
<body>
<div class="card">
  <div class="logo">USFQ Galápagos</div>
  <h1>Eventos — Admin</h1>

  <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <label for="username">Usuario</label>
    <input type="text" id="username" name="username" autocomplete="username" required>

    <label for="password">Contraseña</label>
    <input type="password" id="password" name="password" autocomplete="current-password" required>

    <button type="submit">Ingresar</button>
  </form>
</div>
</body>
</html>
