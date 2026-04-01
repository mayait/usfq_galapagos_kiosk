# Kiosko USFQ Galápagos

Sistema de pantallas informativas para el campus USFQ San Cristóbal, Galápagos. Muestra eventos de la semana, mareas del día, y anuncios institucionales en monitores dentro de la universidad.

---

## Estructura del proyecto

```
usfq_galapagos_kiosk/
├── html/
│   ├── index.html        ← PRODUCCIÓN — pantalla principal de eventos
│   ├── config.js         ← Configuración centralizada (ICS, NOAA, intervalos)
│   └── kiosko.html       ← Lanzador con ciclado automático de pantallas
├── screens/              ← Prototipos de pantallas adicionales
│   └── anuncios.html     ← Prototipo: pantalla de anuncios del campus
└── README.md
```

> **Regla de oro:** `html/index.html` es siempre la versión de producción. Las pantallas en desarrollo se trabajan en `screens/` u otras carpetas y solo se mueven a `html/` cuando están listas.

---

## Pantalla principal — `html/index.html`

### Qué hace

Pantalla diseñada para monitores 16:9 a 1920×1080. Muestra dos paneles en paralelo:

**Panel izquierdo**: Logo / título, reloj en tiempo real (UTC-6 Galápagos), fecha en español, mareas del día con indicador visual, etiqueta de rango semanal.

**Panel derecho**: Lista de eventos de la semana actual agrupados por día, auto-scroll suave bidireccional, etiqueta "AHORA" en eventos en curso, estados de carga y vacío.

---

## Guía completa: Integración con calendarios ICS

Esta sección documenta todo lo aprendido al integrar calendarios ICS de Outlook 365 en un proyecto web que corre en el navegador (sin backend). Sirve como referencia para futuras integraciones con otros calendarios.

### Obtener la URL del ICS

Para Outlook 365, la URL del calendario público tiene esta forma:

```
https://outlook.office365.com/owa/calendar/{calendar-id}@{dominio}/{publish-id}/calendar.ics
```

Se obtiene desde Outlook → Configuración del calendario → Publicar calendario → Copiar el enlace ICS.

### El problema de CORS

Los servidores de Outlook **no envían headers `Access-Control-Allow-Origin`**, por lo que el navegador bloquea las peticiones directas por política de CORS. La solución es usar proxies CORS públicos que re-envían la petición y agregan los headers necesarios.

### Proxies CORS — qué funciona y qué no

Después de probar extensamente, este es el estado de los proxies CORS gratuitos:

| Proxy | URL | Estado | Notas |
|-------|-----|--------|-------|
| **codetabs** | `https://api.codetabs.com/v1/proxy/?quest={url}` | **Funciona** | Requiere trailing slash (`/proxy/?`). Puede devolver HTTP 400 pero con datos válidos en el body. |
| allorigins | `https://api.allorigins.win/raw?url={url}` | Inestable | Frecuentes errores 522. Cuando funciona es rápido. |
| corsproxy.io | `https://corsproxy.io/?{url}` | Limitado | Bloquea peticiones server-side (curl/Node). Funciona intermitentemente desde navegador. |
| cors-proxy.htmldriven | `https://cors-proxy.htmldriven.com/?url={url}` | No funciona | No devuelve datos de ICS. |
| thingproxy | `https://thingproxy.freeboard.io/fetch/{url}` | No funciona | No devuelve datos de ICS. |

**Recomendación**: Usar codetabs como primario, allorigins como fallback, y tener siempre datos de respaldo hardcodeados.

### Estrategia de fetch en cascada

El sistema prueba cada proxy en orden. Si uno falla, pasa al siguiente. La validación se hace por contenido (buscar `BEGIN:VCALENDAR` en el texto), **no por HTTP status code**, porque algunos proxies devuelven códigos 400 con datos válidos:

```javascript
const CORS_PROXIES = [
  url => `https://api.codetabs.com/v1/proxy/?quest=${encodeURIComponent(url)}`,
  url => `https://api.allorigins.win/raw?url=${encodeURIComponent(url)}`,
  url => `https://corsproxy.io/?${encodeURIComponent(url)}`,
  url => url  // directo — funciona si se sirve desde servidor local con proxy propio
];

async function fetchCalendar() {
  for (const proxy of CORS_PROXIES) {
    try {
      const resp = await fetch(bustCache(proxy(ICS_URL)), { cache: 'no-store' });
      const text = await resp.text();
      // Validar por contenido, NO por resp.ok
      if (!text.includes('BEGIN:VCALENDAR')) continue;
      return parseICS(text);
    } catch(e) { continue; }
  }
  return null; // todos los proxies fallaron → usar datos de respaldo
}
```

### Errores que evitar con CORS proxies

1. **No agregar headers custom al fetch** — Headers como `Pragma: no-cache` o `Authorization` disparan una petición OPTIONS de preflight. Los proxies CORS gratuitos generalmente no manejan OPTIONS, así que el navegador bloquea todo. Usar solo `{ cache: 'no-store' }` que es una opción de fetch, no un header HTTP.

2. **No confiar en `resp.ok`** — Codetabs puede devolver HTTP 400 con datos perfectamente válidos en el body y con `Access-Control-Allow-Origin: *`. Si solo verificas `resp.ok`, descartas datos buenos.

3. **No olvidar el trailing slash** — `api.codetabs.com/v1/proxy?quest=...` (sin slash) devuelve un 301 redirect a `/proxy/?quest=...`. Los navegadores no siempre siguen redirects correctamente en contexto CORS.

4. **Cache busting** — Los proxies pueden cachear respuestas. Agregar un timestamp como query param evita esto:
   ```javascript
   function bustCache(url) {
     const sep = url.includes('?') ? '&' : '?';
     return url + sep + '_t=' + Date.now();
   }
   ```

### Parsing del ICS — Zonas horarias de Microsoft

Este es el punto más complejo. Los calendarios de Outlook 365 usan **nombres de zona horaria propietarios de Microsoft** en las propiedades DTSTART/DTEND, no los estándar de IANA. Un solo calendario puede mezclar varias:

```
DTSTART;TZID="tzone://Microsoft/Utc":20260302T120000          → UTC
DTSTART;TZID=Central America Standard Time:20260304T180000     → UTC-6
DTSTART;TZID=Central Standard Time:20260313T160000             → UTC-6 (con DST en EEUU, pero no aplica aquí)
DTSTART;TZID=Greenwich Standard Time:20260308T203000           → UTC
DTSTART;TZID=Canada Central Standard Time:20260309T093000      → UTC-6
DTSTART:20260302T120000Z                                       → UTC (sufijo Z)
DTSTART;VALUE=DATE:20260304                                    → Evento de día completo
```

La función `resolveMicrosoftTZID` mapea cada nombre al offset correcto:

```javascript
function resolveMicrosoftTZID(keyPart) {
  // Dos patrones de regex: entrecomillado (tzone://...) y sin comillas
  const tzMatch = keyPart.match(/TZID="([^"]+)"/) || keyPart.match(/TZID=([^;:]+)/);
  if (!tzMatch) return '-06:00'; // sin TZID → asumir hora local (Galápagos)

  const tz = tzMatch[1].toLowerCase();

  // UTC
  if (tz.includes('utc') || tz.includes('greenwich') || tz.includes('gmt standard'))
    return '+00:00';

  // UTC-6 sin DST (Galápagos, Centro América, Saskatchewan)
  if (tz.includes('central america') || tz.includes('canada central'))
    return '-06:00';

  // US Central — tiene DST en EEUU, pero para Galápagos tratamos como -06:00
  if (tz.includes('central standard') || tz.includes('central time'))
    return '-06:00';

  // Ecuador continental / Colombia / Perú = UTC-5
  if (tz.includes('sa pacific') || tz.includes('colombia') ||
      tz.includes('lima') || tz.includes('bogota') || tz.includes('guayaquil'))
    return '-05:00';

  // US Eastern = UTC-5
  if (tz.includes('eastern'))
    return '-05:00';

  return '-06:00'; // fallback: Galápagos
}
```

**Importante**: El regex para TZID debe manejar dos formatos:
- `TZID="tzone://Microsoft/Utc"` — entrecomillado, contiene `://` (los dos puntos rompen un regex simple)
- `TZID=Central America Standard Time` — sin comillas, separado por `;` o `:`

El regex `TZID="([^"]+)"` captura el primero, y `TZID=([^;:]+)` captura el segundo.

### Parsing de fechas ICS

```javascript
function parseICSDate(value, keyPart) {
  const isDate = keyPart.includes('VALUE=DATE') || (value.length === 8);
  const clean = value.replace('Z', '');

  // Eventos de día completo → anclar a zona local (UTC-6)
  if (isDate)
    return new Date(clean.slice(0,4)+'-'+clean.slice(4,6)+'-'+clean.slice(6,8)+'T00:00:00-06:00');

  const y=clean.slice(0,4), mo=clean.slice(4,6), d=clean.slice(6,8);
  const h=clean.slice(9,11), mi=clean.slice(11,13), s=clean.slice(13,15)||'00';

  // Fechas UTC explícitas (terminan en Z)
  if (value.endsWith('Z'))
    return new Date(`${y}-${mo}-${d}T${h}:${mi}:${s}Z`);

  // Resolver offset según TZID de Microsoft
  const offset = resolveMicrosoftTZID(keyPart);
  return new Date(`${y}-${mo}-${d}T${h}:${mi}:${s}${offset}`);
}
```

### Zona horaria de display

Galápagos es UTC-6 sin horario de verano. En JavaScript, `Pacific/Galapagos` es el IANA correcto pero puede tener problemas de compatibilidad en algunos navegadores. **`America/Costa_Rica`** es el mismo offset (UTC-6 sin DST) y tiene soporte universal:

```javascript
const GALAPAGOS_TZ = 'America/Costa_Rica'; // UTC-6, equivalente a Galápagos

function formatTime(d) {
  return d.toLocaleTimeString('es-EC', {
    hour: '2-digit', minute: '2-digit',
    hour12: true, timeZone: GALAPAGOS_TZ
  }).toUpperCase();
}
```

### Expansión de eventos recurrentes (RRULE)

El parser soporta `FREQ=DAILY`, `FREQ=WEEKLY` y `FREQ=MONTHLY` con `UNTIL`, `COUNT`, `INTERVAL` y `BYDAY`. Solo expande instancias dentro de la semana actual (lunes–domingo) para mantener el rendimiento:

```javascript
function expandAndSort(vevents) {
  const { monday, sunday } = getWeekBounds();
  let expanded = [];
  for (const ev of vevents) {
    if (ev.rrule) {
      expanded.push(...expandRRule(ev, monday, sunday));
    } else if (ev.dtstart >= monday && ev.dtstart <= sunday) {
      expanded.push({ ...ev });
    }
  }
  // Deduplicar por summary + fecha/hora
  const seen = new Set();
  expanded = expanded.filter(ev => {
    const key = ev.summary + '|' + ev.dtstart.toISOString().slice(0,16);
    if (seen.has(key)) return false;
    seen.add(key); return true;
  });
  return expanded.sort((a, b) => a.dtstart - b.dtstart);
}
```

**Cuidado con UNTIL**: Muchos eventos recurrentes de Outlook tienen fechas UNTIL muy cercanas a la fecha de creación. Si un evento recurrente "desaparece", verificar que su UNTIL no haya expirado.

### Datos de respaldo

Si todos los proxies fallan, el kiosko carga eventos hardcodeados para que las pantallas nunca queden vacías. Estos datos se definen en `useFallbackData()` y deben actualizarse manualmente si cambian las actividades regulares del campus.

### Cómo agregar un nuevo calendario ICS

1. Obtener la URL pública del ICS (Outlook, Google Calendar, etc.)
2. Actualizar `ICS_URL` en `config.js`
3. Si el nuevo calendario usa zonas horarias no mapeadas, agregar el caso en `resolveMicrosoftTZID`
4. Para Google Calendar, las fechas suelen venir en UTC (sufijo Z) o con `TZID` estándar IANA — el parser ya maneja ambos

**URLs de Google Calendar**:
```
https://calendar.google.com/calendar/ical/{calendar-id}/public/basic.ics
```
Google Calendar sí envía headers CORS, por lo que se puede hacer fetch directo sin proxy.

---

## Guía completa: Mareas — NOAA Tides & Currents

### Cómo funciona

NOAA tiene estaciones de medición de mareas oficiales con una API completamente pública, sin costo y sin API key.

### Endpoint

```
https://api.tidesandcurrents.noaa.gov/api/prod/datagetter
  ?begin_date=YYYYMMDD
  &end_date=YYYYMMDD
  &station=9992401
  &product=predictions
  &datum=MLLW
  &time_zone=lst
  &interval=hilo
  &units=metric
  &format=json
```

### Parámetros

| Parámetro | Valor | Por qué |
|-----------|-------|---------|
| `begin_date` / `end_date` | `YYYYMMDD` | Fecha del día en formato compacto |
| `station` | `9992401` | Estación San Cristóbal de NOAA |
| `product` | `predictions` | Predicciones (no datos en tiempo real) |
| `datum` | `MLLW` | Nivel de referencia estándar (bajamar media) |
| `time_zone` | `lst` | Hora local de la estación |
| `interval` | `hilo` | Solo extremos (high/low), no cada hora |
| `units` | `metric` | Metros |
| `format` | `json` | Respuesta en JSON |

### Respuesta

```json
{
  "predictions": [
    { "t": "2026-03-31 01:34", "v": "1.716", "type": "H" },
    { "t": "2026-03-31 07:41", "v": "0.107", "type": "L" },
    { "t": "2026-03-31 13:55", "v": "1.868", "type": "H" },
    { "t": "2026-03-31 20:09", "v": "0.070", "type": "L" }
  ]
}
```

`"H"` = pleamar (high tide), `"L"` = bajamar (low tide). Normalmente hay 4 extremos por día.

### Parsing de las horas

Las horas vienen en hora local de la estación (`lst`). Para Galápagos eso es UTC-6:

```javascript
function parseTideTime(tStr) {
  const [datePart, timePart] = tStr.split(' ');
  return new Date(`${datePart}T${timePart}:00-06:00`);
}
```

### Estaciones disponibles para Galápagos

| Isla | Estación | ID |
|------|----------|----|
| San Cristóbal | San Cristobal, Galapagos | `9992401` |
| Santa Cruz / Baltra | Caleta Aeolian, Baltra | `TWC0291` |

Para cambiar de estación, actualizar `NOAA_STATION` en `config.js`.

### Ventajas sobre Stormglass

| | NOAA | Stormglass |
|--|------|------------|
| Costo | Gratis | Gratis (10 llamadas/día) |
| API key | No necesita | Sí (se expone en el repo) |
| CORS | Funciona directo | Funciona directo |
| Datos | Predicciones de mareas exactas | Oleaje, viento, temperatura |
| Fiabilidad | Muy alta (gobierno EEUU) | Media (límites de uso) |

---

## Tipos de eventos y colores

| Tipo | Color | Palabras clave detectadas |
|------|-------|---------------------------|
| `academico` | Azul cyan `#00B4D8` | coloquio, taller, charla, seminario |
| `club` | Verde `#2DD4A0` | club, training, creative |
| `deporte` | Naranja `#F4A261` | basket, peloteo, jiu, alfa |
| `social` | Violeta `#E879F9` | pelis, pizza, celebra, almuerzo |
| `bienestar` | Verde claro `#34D399` | mental, salud, bienestar, yoga |
| `evento` | Gris `#9BA4B5` | (cualquier otro) |

La detección se hace por coincidencia de palabras clave en el SUMMARY del evento ICS.

---

## Sistema de diseño

| Variable | Valor |
|----------|-------|
| Fondo | `#041C32` — azul noche marino |
| Primario | `#0F4C81` — azul océano |
| Acento | `#00B4D8` — cian galápagos |
| Verde | `#2DD4A0` |
| Naranja | `#F4A261` |
| Texto | `#F1F6F9` |
| Texto muted | `#9BA4B5` |
| Superficie | `rgba(4,41,58,0.55)` + blur |
| Fuente serif | Playfair Display (títulos) |
| Fuente sans | Outfit (cuerpo, datos) |
| Radio tarjeta | `24px` |

El fondo usa capas de ondas SVG animadas + orbes difuminados + partículas flotantes para evocar el entorno oceánico de Galápagos.

---

## Configuración — `html/config.js`

Todas las constantes configurables del sistema. Las pantallas nuevas lo importan con:

```html
<script src="config.js"></script>
<!-- o desde screens/: -->
<script src="../html/config.js"></script>
```

| Clave | Descripción |
|-------|-------------|
| `ICS_URL` | URL del calendario ICS de Outlook |
| `CALENDAR_REFRESH_MS` | Intervalo de refresco del calendario (default: 2 min) |
| `NOAA_ENDPOINT` | URL base de la API de mareas NOAA |
| `NOAA_STATION` | ID de estación NOAA (`9992401` = San Cristóbal) |
| `TIDES_REFRESH_MS` | Intervalo de refresco de mareas (default: 1 hora) |
| `LAT` / `LNG` | Coordenadas de San Cristóbal |
| `TIMEZONE` | `America/Costa_Rica` (UTC-6, equiv. Galápagos) |
| `AUTO_SCROLL_SPEED` | Velocidad de auto-scroll en px/frame |
| `AUTO_SCROLL_PAUSE_MS` | Pausa al llegar al límite del scroll |
| `CORS_PROXIES` | Array de funciones proxy para el ICS |
| `SCREENS` | Lista de pantallas para ciclado automático |

---

## Ciclado de pantallas — `html/kiosko.html`

Lanzador que carga las pantallas en un iframe y cicla automáticamente entre ellas. Las pantallas y duraciones se configuran en `KIOSK_CONFIG.SCREENS`.

**Cómo abrir en modo kiosko (Chrome/Chromium):**

```bash
chromium-browser --kiosk --noerrdialogs --disable-infobars \
  --no-first-run file:///ruta/al/proyecto/html/kiosko.html
```

---

## Cómo añadir eventos al calendario

Los eventos se gestionan desde el calendario de Outlook configurado en `ICS_URL`. Cualquier evento creado ahí aparecerá en el kiosko en los próximos 2 minutos.

Para cambiar el calendario, actualizar la URL en `config.js`.

---

## Pantallas en desarrollo (`screens/`)

| Archivo | Estado | Descripción |
|---------|--------|-------------|
| `anuncios.html` | Prototipo | Información estática del campus (horarios, servicios, contactos) |

---

## Despliegue

El proyecto no requiere servidor ni build. Es HTML puro que puede abrirse directamente o servirse con cualquier servidor estático:

```bash
# Servidor rápido de desarrollo
cd html/
python3 -m http.server 8080
# Abrir en: http://localhost:8080/kiosko.html
```

Para producción en pantallas de la universidad, se recomienda servir desde un servidor local en la red de la USFQ para evitar dependencia de CORS proxies externos.
