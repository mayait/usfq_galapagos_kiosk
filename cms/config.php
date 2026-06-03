<?php
// ============================================================
//  KIOSKO USFQ GALÁPAGOS — API + CMS · CONFIGURACIÓN PRINCIPAL
//  Edita solo este archivo para credenciales, claves y ajustes.
//  Este archivo se ejecuta como PHP: su contenido NUNCA se sirve
//  como texto, por eso las claves viven aquí de forma segura.
// ============================================================

// ── Credenciales del panel admin ────────────────────────────
define('ADMIN_USER',     'admin');
define('ADMIN_PASSWORD', 'n9gV4nmM2HTdiV');  // ← generada en el deploy; cámbiala cuando quieras

// ── URLs base ───────────────────────────────────────────────
define('SITE_URL',       'https://api.julianmaya.com');          // raíz del API/CMS
define('DATA_DIR',       __DIR__ . '/data');
define('UPLOAD_DIR',     __DIR__ . '/uploads/');
define('UPLOAD_URL',     SITE_URL . '/uploads/');

// ── Archivos de datos (JSON plano, sin base de datos) ───────
define('EVENTS_FILE',    DATA_DIR . '/events.json');     // afiches: eventos / importantes
define('CLUBS_FILE',     DATA_DIR . '/clubs.json');      // clubes permanentes
define('PROMOS_FILE',    DATA_DIR . '/promos.json');     // convenios + promociones
define('CALENDARS_FILE', DATA_DIR . '/calendars.json');  // URLs ICS a ingerir
define('MARINE_FILE',    DATA_DIR . '/marine.json');     // mareas + estado del mar (cron)
define('ICS_CACHE_FILE', DATA_DIR . '/ics_cache.txt');   // caché del ICS crudo

// Compat con código existente del CMS
define('DATA_FILE',      EVENTS_FILE);

// ── Subida de imágenes ──────────────────────────────────────
define('MAX_FILE_SIZE',  10 * 1024 * 1024);  // 10 MB
define('ALLOWED_TYPES',  ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
define('SESSION_NAME',   'kiosk_cms_session');

// ── Ubicación — San Cristóbal, Galápagos (UTC-6, sin DST) ───
define('GALAPAGOS_TZ',   'America/Costa_Rica');  // mismo offset, máxima compatibilidad
define('LAT',            -0.9024);
define('LNG',            -89.6105);

// ── Mareas NOAA (gratis, sin key) ───────────────────────────
define('NOAA_ENDPOINT',  'https://api.tidesandcurrents.noaa.gov/api/prod/datagetter');
define('NOAA_STATION',   '9992401');             // San Cristóbal

// ── Stormglass (estado del mar) — la key NUNCA va al cliente ─
define('STORMGLASS_KEY', '2b1b264c-8ce6-11f0-b41a-0242ac130006-2b1b26e2-8ce6-11f0-b41a-0242ac130006');
define('STORMGLASS_ENDPOINT', 'https://api.stormglass.io/v2/weather/point');

// ── Token para disparar el cron por URL ─────────────────────
define('CRON_TOKEN',     'x47ayo1iwhdxv8dqe97lj549');  // token del cron por URL

// ── Calendarios ICS por defecto (si calendars.json no existe) ─
//  "gestión de calendarios": se administran desde admin/calendars.php
const DEFAULT_CALENDARS = [
    [
        'id'      => 'usfq-principal',
        'name'    => 'USFQ Galápagos (Outlook)',
        'url'     => 'https://outlook.office365.com/owa/calendar/71a3663561a843bca1cccea1dffd9fa6@usfq.edu.ec/7755590ea9af4dcb84b49fb91d1b2a0f13028026135859137563/calendar.ics',
        'enabled' => true,
    ],
];

// ════════════════════════════════════════════════════════════
//  Helpers de datos (JSON plano)
// ════════════════════════════════════════════════════════════

/** Carga y decodifica un archivo JSON. Devuelve [] si no existe o es inválido. */
function loadJson(string $file): array {
    if (!file_exists($file)) return [];
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

/** Guarda un arreglo como JSON (escritura atómica). */
function saveJson(string $file, array $data): bool {
    $dir = dirname($file);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $tmp  = $file . '.tmp';
    if (file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    return rename($tmp, $file);
}

// ── Wrappers específicos (compat + legibilidad) ─────────────
function loadEvents(): array      { return loadJson(EVENTS_FILE); }
function saveEvents(array $e): void { saveJson(EVENTS_FILE, $e); }
function loadClubs(): array       { return loadJson(CLUBS_FILE); }
function saveClubs(array $c): void { saveJson(CLUBS_FILE, $c); }
function loadPromos(): array      { return loadJson(PROMOS_FILE); }
function savePromos(array $p): void { saveJson(PROMOS_FILE, $p); }

/** Calendarios configurados (o los de por defecto). */
function loadCalendars(): array {
    $cals = loadJson(CALENDARS_FILE);
    return $cals ?: DEFAULT_CALENDARS;
}
function saveCalendars(array $c): void { saveJson(CALENDARS_FILE, $c); }

function findEvent(string $id): ?array {
    foreach (loadEvents() as $e) {
        if (($e['id'] ?? null) === $id) return $e;
    }
    return null;
}

function generateId(): string {
    return bin2hex(random_bytes(6)); // 12-char hex
}

// ════════════════════════════════════════════════════════════
//  Auth
// ════════════════════════════════════════════════════════════

function startSecureSession(): void {
    session_name(SESSION_NAME);
    session_start();
}

function isAuthenticated(): bool {
    return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

function requireAuth(): void {
    startSecureSession();
    if (!isAuthenticated()) {
        header('Location: ' . SITE_URL . '/admin/login.php');
        exit;
    }
}

// ════════════════════════════════════════════════════════════
//  Respuesta JSON (CORS abierto para el frontend)
// ════════════════════════════════════════════════════════════

function jsonResponse(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
