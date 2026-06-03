<?php
/** Cabecera + navegación compartida del admin. Define $NAV antes de incluir. */
$NAV = $NAV ?? '';
$links = [
    'events'    => ['index.php',     'Eventos'],
    'clubs'     => ['clubs.php',     'Clubes'],
    'promos'    => ['promos.php',    'Convenios'],
    'calendars' => ['calendars.php', 'Calendarios'],
];
?>
<header>
  <div class="brand">USFQ · <span>Kiosko CMS</span></div>
  <nav>
    <?php foreach ($links as $key => [$href, $label]): ?>
      <a href="<?= $href ?>" class="navlink<?= $NAV === $key ? ' active' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
    <a href="logout.php" class="btn btn-ghost btn-sm" style="margin-left:8px">Salir</a>
  </nav>
</header>
