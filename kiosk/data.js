/* ==========================================================================
   Kiosko USFQ Galápagos 2.0 — carga de datos EN VIVO
   Reemplaza el mock del diseño: obtiene todo desde el API unificado
   (api.julianmaya.com/api/kiosk.php) y entrega el objeto window.KIOSK con
   la misma forma que esperaba el prototipo, sumando los helpers de cliente.
   ========================================================================== */
(function () {
  // URL del API — override con ?api=... (útil para pruebas locales) o
  // window.KIOSK_API_URL antes de cargar este script.
  const API_URL =
    new URLSearchParams(location.search).get('api') ||
    window.KIOSK_API_URL ||
    'https://api.julianmaya.com/api/kiosk.php';

  // ---- Categorías de club (color-coded, paleta de marca) — lado cliente ----
  const CATS = {
    deportes:  { label: 'Deportes',       color: 'var(--color-turquesa)',   soft: 'rgba(0,169,168,0.16)' },
    bienestar: { label: 'Bienestar',      color: 'var(--color-azul-medio)', soft: 'rgba(17,130,197,0.16)' },
    mar:       { label: 'Ciencia y mar',  color: 'var(--color-azul-mar)',   soft: 'rgba(22,114,163,0.18)' },
    arte:      { label: 'Arte y cultura', color: 'var(--color-coral)',      soft: 'rgba(224,122,95,0.16)' },
    letras:    { label: 'Académico',      color: 'var(--color-arena)',      soft: 'rgba(233,220,196,0.14)' },
  };

  // ---- Foto de respaldo por categoría (para tiles sin afiche) --------------
  const PH = {
    deportes:  'assets/photos/campus.jpg',
    bienestar: 'assets/photos/terraza.jpg',
    mar:       'assets/photos/lobos-marinos.jpg',
    arte:      'assets/photos/estudio.jpg',
    letras:    'assets/photos/biblioteca.jpg',
  };

  function fmtTime(t) {
    if (!t) return '';
    const [h, m] = t.split(':').map(Number);
    const am = h < 12;
    const hr = h % 12 === 0 ? 12 : h % 12;
    return `${hr}:${String(m).padStart(2, '0')} ${am ? 'a. m.' : 'p. m.'}`;
  }

  // ---- Datos mínimos de respaldo (si el API no responde) ------------------
  const FALLBACK = {
    week: { label: 'Cartelera de la semana', count: 0 },
    clubs: [
      { name: 'Alfa Training Club', cat: 'deportes', days: 'Lun · Mié · Vie', time: '06:30' },
      { name: 'Club de Yoga',       cat: 'bienestar', days: 'Miércoles',       time: '18:00' },
      { name: 'Club de Buceo',      cat: 'mar',       days: 'Sábado',          time: '07:00' },
      { name: 'Coro USFQ',          cat: 'arte',      days: 'Martes',          time: '18:30' },
    ],
    pinned: [],
    promos: [],
    days: [],
    tides: { state: '—', station: 'NOAA · Estación San Cristóbal #9992401', points: [] },
    marine: null,
  };

  /** Obtiene y normaliza los datos del kiosko. Nunca lanza: cae a FALLBACK. */
  window.loadKioskData = async function loadKioskData() {
    let data;
    try {
      const res = await fetch(API_URL + (API_URL.includes('?') ? '&' : '?') + '_t=' + Date.now(),
        { cache: 'no-store' });
      const json = await res.json();
      data = json && json.ok ? json : FALLBACK;
    } catch (e) {
      console.warn('[kiosk] API no disponible, usando respaldo:', e);
      data = FALLBACK;
    }
    return Object.assign({}, data, { CATS, PH, fmtTime });
  };
})();
