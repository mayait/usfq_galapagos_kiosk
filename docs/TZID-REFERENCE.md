# Referencia de TZIDs de Microsoft (Outlook 365)

## El problema

Los archivos ICS de Outlook 365 usan nombres de zona horaria **propietarios de Microsoft** en lugar de nombres IANA estándar. El navegador no puede resolver estos nombres directamente.

Ejemplos encontrados en el calendario real de USFQ:

```
DTSTART;TZID="tzone://Microsoft/Utc":20260315T140000
DTSTART;TZID="Central America Standard Time":20260401T090000
DTSTART;TZID="Central Standard Time":20260401T090000
DTSTART;TZID="Greenwich Standard Time":20260401T090000
DTSTART;TZID="Canada Central Standard Time":20260401T090000
```

## Función resolveMicrosoftTZID

Esta función toma el `keyPart` completo de una línea ICS (ej: `DTSTART;TZID="Central America Standard Time"`) y devuelve un offset como string (ej: `-06:00`).

```javascript
function resolveMicrosoftTZID(keyPart) {
  // Extraer valor del TZID — dos regex para manejar quoted y unquoted
  const tzMatch = keyPart.match(/TZID="([^"]+)"/) || keyPart.match(/TZID=([^;:]+)/);
  if (!tzMatch) return '-06:00'; // default Galápagos

  const tz = tzMatch[1].toLowerCase();

  // UTC / GMT
  if (tz.includes('utc') || tz.includes('greenwich') || tz.includes('gmt standard'))
    return '+00:00';

  // UTC-6 (Galápagos, Centroamérica, Saskatchewan)
  if (tz.includes('central america') || tz.includes('canada central'))
    return '-06:00';
  if (tz.includes('central standard') || tz.includes('central time'))
    return '-06:00';

  // UTC-5 (Ecuador continental, Colombia, Perú, Eastern US)
  if (tz.includes('sa pacific') || tz.includes('colombia') ||
      tz.includes('lima') || tz.includes('bogota') || tz.includes('guayaquil'))
    return '-05:00';
  if (tz.includes('eastern'))
    return '-05:00';

  // Default: Galápagos
  return '-06:00';
}
```

## Mapeo completo de TZIDs encontrados

| TZID de Microsoft | Offset | Equivalente IANA |
|---|---|---|
| `tzone://Microsoft/Utc` | +00:00 | UTC |
| `Greenwich Standard Time` | +00:00 | Europe/London (sin DST) |
| `Central America Standard Time` | -06:00 | America/Costa_Rica |
| `Central Standard Time` | -06:00 | America/Chicago (sin DST*) |
| `Canada Central Standard Time` | -06:00 | America/Regina |
| `SA Pacific Standard Time` | -05:00 | America/Bogota |
| `Eastern Standard Time` | -05:00 | America/New_York (sin DST*) |

*Nota: Simplificamos ignorando DST porque Galápagos no tiene DST y los eventos son locales.

## Regex para extraer TZID

Se necesitan DOS regex porque Outlook a veces usa comillas y a veces no:

```javascript
// Con comillas: TZID="tzone://Microsoft/Utc"
keyPart.match(/TZID="([^"]+)"/)

// Sin comillas: TZID=Central America Standard Time
keyPart.match(/TZID=([^;:]+)/)
```

**No combinar en una sola regex** — el formato `tzone://Microsoft/Utc` contiene `://` que rompe regex combinadas con delimitadores como `:`.

## Agregar nuevos TZIDs

Si aparecen eventos con horas incorrectas:

1. Inspeccionar el ICS crudo (abrir la URL del ICS en el navegador o descargar el archivo)
2. Buscar el TZID en las líneas DTSTART/DTEND
3. Buscar el offset correcto en [TimeZone Converter de Microsoft](https://docs.microsoft.com/en-us/windows-hardware/manufacture/desktop/default-time-zones)
4. Agregar un nuevo `if` en `resolveMicrosoftTZID()` con el pattern y offset
