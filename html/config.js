/**
 * config.js — Configuración centralizada del Kiosko USFQ Galápagos
 *
 * Edita este archivo para actualizar URLs, intervalos y
 * el listado de pantallas que cicla el lanzador (kiosko.html).
 *
 * Uso en cualquier pantalla:
 *   <script src="config.js"></script>
 *   // luego acceder a: KIOSK_CONFIG.ICS_URL, KIOSK_CONFIG.TIMEZONE, etc.
 */

const KIOSK_CONFIG = {

  // ── Calendario ICS (Outlook 365) ─────────────────────────────────────────
  ICS_URL: 'https://outlook.office365.com/owa/calendar/71a3663561a843bca1cccea1dffd9fa6@usfq.edu.ec/7755590ea9af4dcb84b49fb91d1b2a0f13028026135859137563/calendar.ics',

  /** Intervalo de refresco del calendario (ms). Default: 2 minutos */
  CALENDAR_REFRESH_MS: 120_000,

  // ── Mareas NOAA — San Cristóbal, Galápagos ──────────────────────────────
  /** Endpoint base de la API de mareas de NOAA (gratuita, sin API key) */
  NOAA_ENDPOINT: 'https://api.tidesandcurrents.noaa.gov/api/prod/datagetter',

  /** ID de estación NOAA en San Cristóbal */
  NOAA_STATION: '9992401',

  /** Intervalo de refresco de datos de mareas (ms). Default: 1 hora */
  TIDES_REFRESH_MS: 3_600_000,

  // ── Ubicación — San Cristóbal, Galápagos ─────────────────────────────────
  LAT: -0.9024,
  LNG: -89.6105,

  /** Zona horaria de Galápagos: UTC-6 (usamos Costa Rica, mismo offset, máxima compatibilidad) */
  TIMEZONE: 'America/Costa_Rica',

  // ── Auto-scroll del panel de eventos ─────────────────────────────────────
  /** Velocidad de scroll automático en píxeles por frame de animación */
  AUTO_SCROLL_SPEED: 0.4,

  /** Pausa en milisegundos al llegar al tope/inicio del scroll */
  AUTO_SCROLL_PAUSE_MS: 8_000,

  // ── Proxies CORS para obtener el ICS desde el navegador ──────────────────
  /** Se intentan en orden; el primero que funcione se usa */
  CORS_PROXIES: [
    url => `https://api.codetabs.com/v1/proxy?quest=${encodeURIComponent(url)}`,
    url => `https://api.allorigins.win/raw?url=${encodeURIComponent(url)}`,
    url => `https://corsproxy.io/?${encodeURIComponent(url)}`,
    url => url,   // directo (funciona si hay servidor local o sin CORS)
  ],

  // ── Ciclado de pantallas (usado por kiosko.html) ─────────────────────────
  /**
   * Lista de pantallas a mostrar en el lanzador.
   * Cada entrada tiene:
   *   url      → ruta relativa a la pantalla (dentro de html/)
   *   duration → milisegundos que se muestra antes de pasar a la siguiente
   *   label    → nombre corto para mostrar en el indicador de pantalla
   */
  SCREENS: [
    { url: 'index.html',               duration: 45_000, label: 'Eventos' },
    { url: '../screens/anuncios.html',  duration: 30_000, label: 'Anuncios' },
  ],

};
