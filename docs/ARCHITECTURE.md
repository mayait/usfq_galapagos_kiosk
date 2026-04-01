# Arquitectura del Kiosko USFQ Galápagos

## Estructura del proyecto

```
usfq_galapagos_kiosk/
├── html/
│   ├── index.html          ← Pantalla principal (tema oscuro océano)
│   ├── index_white.html    ← Pantalla principal (tema blanco marca USFQ)
│   ├── kiosko.html         ← Lanzador que cicla pantallas en iframe
│   ├── config.js           ← Configuración centralizada
│   └── img/
│       ├── LOGO-USFQG-2025.png        ← Logo color (gradiente verde→azul)
│       ├── USFQG-NEGRO.png            ← Logo negro sobre transparente
│       ├── USGQG-LOGO-NUEVO-COLORES.jpg
│       ├── USGQG-LOGO-NUEVO-NEGRO.jpg
│       └── USGQG-LOGO-NUEVO-BLANCO.png ← Logo blanco (para fondos oscuros)
├── screens/
│   └── anuncios.html       ← Pantalla de anuncios (prototipo)
├── docs/                   ← Documentación técnica (esta carpeta)
└── README.md               ← Documentación general del proyecto
```

## Flujo general

El kiosko funciona como una aplicación 100% client-side (HTML + JS vanilla). No hay backend. Toda la lógica se ejecuta en el navegador.

### Ciclo de vida de una pantalla (index.html / index_white.html)

1. **Carga inicial** → se ejecuta `init()` al cargar el DOM
2. **Reloj** → `updateClock()` se llama cada segundo, muestra hora en `America/Costa_Rica` (UTC-6)
3. **Calendario ICS** → `fetchCalendar()` obtiene el .ics vía CORS proxy, parsea eventos, expande recurrencias
4. **Mareas NOAA** → `fetchTides()` obtiene predicciones del día desde la API de NOAA
5. **Auto-refresh** → el calendario se re-fetcha cada 2 minutos; las mareas cada 1 hora
6. **Auto-scroll** → si la lista de eventos desborda, hace scroll automático oscilante

### Lanzador (kiosko.html)

Usa un `<iframe>` que cicla entre las URLs definidas en `KIOSK_CONFIG.SCREENS`. Cada pantalla se muestra durante su `duration` (ms) y luego avanza a la siguiente. Incluye barra de progreso e indicadores de pantalla (dots).

## Configuración centralizada: config.js

```javascript
KIOSK_CONFIG = {
  ICS_URL,              // URL del calendario .ics de Outlook 365
  CALENDAR_REFRESH_MS,  // Intervalo de refresco del calendario (default 120000)
  NOAA_ENDPOINT,        // Base URL de la API de mareas NOAA
  NOAA_STATION,         // ID de estación NOAA (9992401 = San Cristóbal)
  TIDES_REFRESH_MS,     // Intervalo de refresco de mareas (default 3600000)
  LAT, LNG,             // Coordenadas de San Cristóbal
  TIMEZONE,             // 'America/Costa_Rica' (UTC-6)
  AUTO_SCROLL_SPEED,    // px por frame de animación
  AUTO_SCROLL_PAUSE_MS, // pausa en extremos del scroll
  CORS_PROXIES,         // Array de funciones proxy (ver ICS-CORS-GUIDE.md)
  SCREENS,              // Array de pantallas para el lanzador
}
```

**Nota importante**: `index.html` actualmente tiene sus valores hardcodeados (no importa `config.js`) para no romper producción. Las pantallas nuevas (anuncios.html, etc.) sí importan `config.js`.

## Zona horaria

Galápagos es **UTC-6** sin horario de verano. Usamos `America/Costa_Rica` como IANA timezone porque:

- `Pacific/Galapagos` existe en IANA pero tiene problemas de compatibilidad en algunos navegadores
- `America/Costa_Rica` es UTC-6 sin DST, idéntico a Galápagos
- Máxima compatibilidad con `Intl.DateTimeFormat` y `toLocaleString`

## Tecnologías

- **HTML/CSS/JS vanilla** — sin frameworks, sin bundlers, sin dependencias
- **Fuentes**: Outfit + Playfair Display (tema oscuro), Jost (tema blanco) — vía Google Fonts
- **APIs externas**: Outlook 365 ICS (vía CORS proxy), NOAA Tides & Currents
- **Sin backend** — todo corre en el navegador
- **Sin API keys** — se eliminó Stormglass (requería key + límite 10/día)
