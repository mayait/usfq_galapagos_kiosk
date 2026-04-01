# Guía de Integración ICS + CORS Proxies

## Resumen

El calendario se obtiene de Outlook 365 como archivo `.ics` (iCalendar). Como el navegador no puede hacer fetch directo a `outlook.office365.com` (CORS bloqueado), usamos proxies CORS públicos.

## URL del calendario ICS

```
https://outlook.office365.com/owa/calendar/71a3663561a843bca1cccea1dffd9fa6@usfq.edu.ec/7755590ea9af4dcb84b49fb91d1b2a0f13028026135859137563/calendar.ics
```

Esta URL es pública (no requiere autenticación). Si se necesita otro calendario, solo cambiar la URL.

## Cascada de proxies CORS

Los proxies se intentan **en orden**. El primero que devuelva contenido válido gana.

```javascript
const CORS_PROXIES = [
  url => `https://api.codetabs.com/v1/proxy/?quest=${encodeURIComponent(url)}`,
  url => `https://api.allorigins.win/raw?url=${encodeURIComponent(url)}`,
  url => `https://corsproxy.io/?${encodeURIComponent(url)}`,
  url => url,  // directo (funciona si hay servidor local o sin CORS)
];
```

### Orden de preferencia y razón

1. **codetabs** — Más confiable en pruebas. Devuelve `access-control-allow-origin: *`.
2. **allorigins** — Buen fallback. A veces da errores 522.
3. **corsproxy.io** — Tercer fallback.
4. **directo** — Solo funciona si la página se sirve desde un contexto sin restricciones CORS.

## Reglas críticas para fetch con CORS proxies

### 1. NO usar headers personalizados

```javascript
// ❌ MALO — causa preflight OPTIONS que el proxy no maneja
fetch(url, { headers: { 'Pragma': 'no-cache', 'Cache-Control': 'no-cache' } })

// ✅ BUENO — cache: 'no-store' es opción de fetch, NO header HTTP
fetch(url, { cache: 'no-store' })
```

Cualquier header personalizado (Pragma, Cache-Control, Authorization, etc.) dispara una petición OPTIONS de preflight. Los proxies CORS gratuitos NO manejan preflight → la petición falla silenciosamente.

### 2. Cache busting con query param

```javascript
function bustCache(url) {
  const sep = url.includes('?') ? '&' : '?';
  return url + sep + '_t=' + Date.now();
}
```

Esto evita que el navegador o el proxy sirvan una versión cacheada del ICS. Se aplica a CADA petición.

### 3. Validar por CONTENIDO, no por HTTP status

```javascript
// ❌ MALO — codetabs devuelve HTTP 400 con datos válidos
if (!resp.ok) continue;

// ✅ BUENO — validar que el contenido sea un calendario ICS real
const text = await resp.text();
if (!text.includes('BEGIN:VCALENDAR')) continue;
```

Algunos proxies (codetabs) devuelven HTTP 400 pero incluyen el body completo y válido. Si se valida por `resp.ok`, se descarta datos perfectamente buenos.

### 4. Anti-cache meta tags en el HTML

```html
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
```

Estas meta tags ayudan a evitar que el navegador cachee el HTML en sí (especialmente en modo kiosko).

## Parseo del ICS

### Estructura básica de un VEVENT

```
BEGIN:VEVENT
DTSTART;TZID="Central America Standard Time":20260401T090000
DTEND;TZID="Central America Standard Time":20260401T100000
SUMMARY:🎓 Clase de Biología Marina
LOCATION:Aula 201
DESCRIPTION:Profesor: Dr. García
RRULE:FREQ=WEEKLY;BYDAY=MO,WE,FR;UNTIL=20260630T235959Z
END:VEVENT
```

### Algoritmo de parseo

1. Dividir el texto por líneas
2. Desplegar líneas continuadas (las que empiezan con espacio/tab se concatenan a la anterior)
3. Iterar líneas, acumulando propiedades entre `BEGIN:VEVENT` y `END:VEVENT`
4. Para cada evento: parsear DTSTART, DTEND, SUMMARY, LOCATION, DESCRIPTION, RRULE
5. Si tiene RRULE → expandir recurrencias (ver sección siguiente)
6. Filtrar: mostrar solo eventos de HOY y MAÑANA (en timezone Galápagos)

### Expansión de recurrencias (RRULE)

Se soportan tres tipos:

- **FREQ=DAILY** — incrementar día a día
- **FREQ=WEEKLY;BYDAY=MO,WE,FR** — incrementar semana a semana, solo en los días indicados
- **FREQ=MONTHLY** — incrementar mes a mes

Cada recurrencia se limita a un máximo de 365 expansiones y respeta `UNTIL` si está presente.

```javascript
// Mapeo de días RRULE a números JS (0=domingo)
const dayMap = { SU:0, MO:1, TU:2, WE:3, TH:4, FR:5, SA:6 };
```

### Parseo de fechas ICS con TZID

Ver `TZID-REFERENCE.md` para el manejo completo de zonas horarias de Microsoft.

```javascript
function parseICSDate(value, keyPart) {
  // 1. Detectar si es fecha-solo (VALUE=DATE o 8 dígitos sin T)
  // 2. Si termina en Z → UTC
  // 3. Si tiene TZID → resolver offset con resolveMicrosoftTZID()
  // 4. Default → -06:00 (Galápagos)
}
```

## Datos de respaldo (fallback)

Si TODOS los proxies fallan, se muestra un set de eventos hardcodeados en `FALLBACK_EVENTS`. Estos son solo para que la pantalla no quede vacía. Se debe verificar periódicamente que al menos un proxy funcione.

## Frecuencia de actualización

- **Calendario**: cada 2 minutos (`CALENDAR_REFRESH_MS: 120_000`)
- **Mareas**: cada 1 hora (`TIDES_REFRESH_MS: 3_600_000`)

## Agregar un nuevo calendario ICS

1. Obtener la URL pública del calendario ICS (Outlook 365 → Configuración → Calendario → Calendarios compartidos → Publicar)
2. Actualizar `ICS_URL` en `config.js` (o hardcoded en el HTML)
3. El parseo funciona con cualquier calendario ICS estándar de Outlook/Google/Apple
4. Si el nuevo calendario usa TZIDs diferentes, agregar mapeos en `resolveMicrosoftTZID()`
