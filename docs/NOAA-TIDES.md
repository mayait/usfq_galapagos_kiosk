# API de Mareas NOAA — Referencia

## Por qué NOAA

Se reemplazó Stormglass porque:
- Stormglass requiere API key (expuesta en código público del kiosko)
- Stormglass tiene límite de 10 llamadas/día (gratuito)
- NOAA es **gratuita, sin API key, sin límites prácticos**

## Estación

- **ID**: `9992401`
- **Nombre**: San Cristóbal, Galápagos
- **Coordenadas**: -0.9024, -89.6105

## Endpoint

```
https://api.tidesandcurrents.noaa.gov/api/prod/datagetter
```

## Parámetros de la petición

```javascript
const params = {
  begin_date: '20260331',     // Formato YYYYMMDD
  end_date:   '20260331',     // Mismo día para predicciones del día
  station:    '9992401',
  product:    'predictions',   // Predicciones (no observaciones reales)
  datum:      'MLLW',         // Mean Lower Low Water (referencia estándar)
  time_zone:  'lst',          // Local Standard Time (UTC-6 para esta estación)
  interval:   'hilo',         // Solo high/low (no cada 6 min)
  units:      'metric',       // Metros
  format:     'json',
};
```

## URL completa ejemplo

```
https://api.tidesandcurrents.noaa.gov/api/prod/datagetter?begin_date=20260331&end_date=20260331&station=9992401&product=predictions&datum=MLLW&time_zone=lst&interval=hilo&units=metric&format=json
```

## Respuesta JSON

```json
{
  "predictions": [
    { "t": "2026-03-31 01:34", "v": "1.716", "type": "H" },
    { "t": "2026-03-31 07:42", "v": "0.293", "type": "L" },
    { "t": "2026-03-31 13:58", "v": "1.541", "type": "H" },
    { "t": "2026-03-31 20:11", "v": "0.412", "type": "L" }
  ]
}
```

- `t` — timestamp en hora local de la estación (UTC-6)
- `v` — altura en metros (referencia MLLW)
- `type` — `"H"` = pleamar (high), `"L"` = bajamar (low)

## Parseo del timestamp

```javascript
function parseTideTime(tStr) {
  const [datePart, timePart] = tStr.split(' ');
  return new Date(`${datePart}T${timePart}:00-06:00`);
}
```

**Importante**: Agregar `-06:00` al final porque `time_zone=lst` devuelve hora local de la estación (UTC-6) pero sin indicador de zona. Sin el offset, `new Date()` interpreta como hora del navegador.

## Generación de la fecha para la petición

```javascript
const now = new Date();
const dateStr = now.toLocaleDateString('en-CA', { timeZone: 'America/Costa_Rica' })
                   .replace(/-/g, '');
// Resultado: '20260331'
```

Usar `en-CA` da formato `YYYY-MM-DD` que al quitar guiones queda `YYYYMMDD` (el formato que NOAA espera).

## Presentación en el kiosko

Se muestran como íconos de olas con hora y altura:

- 🌊 **Pleamar** (H): `⬆ 1.72m — 01:34`
- 🌊 **Bajamar** (L): `⬇ 0.29m — 07:42`

La próxima marea se resalta visualmente. Las mareas pasadas se atenúan.

## Frecuencia de refresco

Cada 1 hora (`TIDES_REFRESH_MS: 3_600_000`). Las predicciones no cambian intradía, pero refrescamos para actualizar qué marea es "la próxima".

## Sin CORS

La API de NOAA **sí permite CORS** (`access-control-allow-origin: *`). No necesita proxy. Se hace fetch directo.

## Otros productos NOAA disponibles

Si en el futuro se quiere agregar más datos:

| Producto | Descripción |
|---|---|
| `water_level` | Nivel de agua observado (tiempo real) |
| `water_temperature` | Temperatura del agua |
| `air_temperature` | Temperatura del aire |
| `wind` | Velocidad y dirección del viento |
| `air_pressure` | Presión barométrica |
| `currents` | Corrientes (requiere estación diferente) |

Documentación completa: https://api.tidesandcurrents.noaa.gov/api/prod/
