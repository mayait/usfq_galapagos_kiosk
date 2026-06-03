<?php
require_once __DIR__ . '/../config.php';
requireAuth();

$id    = trim($_GET['id'] ?? '');
$event = findEvent($id);

if ($event) {
    // Borrar imagen del disco
    $imagePath = UPLOAD_DIR . $event['image'];
    if (file_exists($imagePath)) {
        unlink($imagePath);
    }

    // Quitar del JSON
    $events = array_filter(loadEvents(), fn($e) => $e['id'] !== $id);
    saveEvents(array_values($events));
}

header('Location: ' . SITE_URL . '/admin/index.php');
exit;
