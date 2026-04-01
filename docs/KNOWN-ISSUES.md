# Problemas Conocidos y Soluciones

## Problemas resueltos

### 1. Calendario muestra datos de respaldo en vez de datos reales

**Síntoma**: La pantalla muestra siempre los mismos eventos genéricos (los de `FALLBACK_EVENTS`).

**Causas posibles y soluciones**:

| Causa | Diagnóstico | Solución |
|---|---|---|
| Headers CORS en fetch | Abrir DevTools → Network → buscar petición OPTIONS | Quitar TODOS los headers del fetch. Usar solo `{ cache: 'no-store' }` |
| Proxy caído | DevTools → Network → ver respuesta del proxy | Verificar que al menos un proxy funcione (probar URLs en el navegador) |
| Validación por resp.ok | DevTools → Network → status 400 pero body válido | Validar por contenido (`text.includes('BEGIN:VCALENDAR')`) no por status |
| Caché del navegador | Mismo ICS viejo después de actualizar | Agregar `_t=Date.now()` a la URL (cache busting) |
| Caché del HTML | Cambios en JS no se reflejan | Ctrl+Shift+R o agregar meta tags anti-cache |

**La solución que arregló todo**: Quitar headers personalizados del `fetch()`. El `{ cache: 'no-store' }` es suficiente y no dispara preflight.

### 2. Emojis no aparecen en títulos de eventos

**Síntoma**: Los eventos creados con emojis en Outlook (ej: "🎓 Clase") aparecen sin emoji.

**Causa**: La función `cleanTitle()` tenía un regex que eliminaba emojis Unicode del inicio del string.

**Solución**: Simplificar `cleanTitle` a solo `return (s || 'Evento').trim();`

**Nota**: Si después de arreglar el código los emojis siguen sin aparecer, es caché del navegador. Hacer hard refresh (Ctrl+Shift+R).

### 3. Horas de eventos incorrectas (desfase de 1-6 horas)

**Síntoma**: Un evento a las 9:00 AM aparece a las 3:00 PM o 2:00 AM.

**Causa**: Microsoft Outlook usa TZIDs propietarios (`Central America Standard Time`, `tzone://Microsoft/Utc`) que el parser no reconocía y defaulteaba a un offset incorrecto.

**Solución**: Implementar `resolveMicrosoftTZID()` que mapea cada TZID de Microsoft a su offset correcto. Ver `TZID-REFERENCE.md`.

### 4. Eventos all-day aparecen en hora incorrecta

**Síntoma**: Un evento de día completo (`VALUE=DATE:20260401`) aparece con hora del timezone del navegador en vez de medianoche Galápagos.

**Causa**: `new Date('2026-04-01T00:00:00')` sin offset usa el timezone del navegador.

**Solución**: Anclar al offset de Galápagos: `new Date('2026-04-01T00:00:00-06:00')`

### 5. Proxy allorigins devuelve error 522

**Síntoma**: `allorigins.win` falla intermitentemente con HTTP 522 (Connection timed out).

**Solución**: Usar cascada de proxies con codetabs como primero. allorigins es fallback.

### 6. codetabs redirect 301

**Síntoma**: La URL `api.codetabs.com/v1/proxy?quest=...` (sin trailing slash) devuelve 301 redirect que falla CORS.

**Solución**: Usar `/v1/proxy/?quest=` (CON trailing slash antes del `?`).

## Problemas potenciales futuros

### Todos los proxies CORS dejan de funcionar

Los proxies CORS gratuitos pueden desaparecer en cualquier momento. Opciones:

1. **Servidor propio**: Configurar un proxy CORS simple en Node.js/nginx en un servidor de la USFQ
2. **Cloudflare Worker**: Proxy CORS gratuito con 100k requests/día
3. **Google Apps Script**: Se puede crear un proxy con Google Apps Script (gratuito)

### El calendario ICS cambia de URL

Si se reconfigura el calendario en Outlook, la URL cambia. Actualizar `ICS_URL` en el código/config.

### Nuevo TZID de Microsoft no reconocido

Si aparecen eventos con horas incorrectas, inspeccionar el ICS crudo y agregar el nuevo TZID a `resolveMicrosoftTZID()`. Ver `TZID-REFERENCE.md`.

### Estación NOAA cambia o se desactiva

Si la estación 9992401 deja de responder, buscar estaciones alternativas en: https://tidesandcurrents.noaa.gov/map/

## Checklist de diagnóstico rápido

Cuando algo no funciona:

1. **Abrir DevTools** (F12) → pestaña Console → buscar errores rojos
2. **Pestaña Network** → filtrar por "proxy" o "codetabs" → ver status y response body
3. **¿Hay petición OPTIONS?** → Si sí, hay headers que no deberían estar
4. **¿El body contiene BEGIN:VCALENDAR?** → Si sí, el proxy funciona; el problema es de parseo
5. **¿Los eventos tienen hora correcta?** → Si no, revisar TZIDs en el ICS crudo
6. **¿La pantalla dice "Actualizando..."?** → Significa que el fetch está en progreso
7. **¿La pantalla dice "Sin eventos"?** → El fetch funcionó pero no hay eventos para hoy/mañana
