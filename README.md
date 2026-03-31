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

**Panel izquierdo**
- Logo / título "¿Qué pasa en la USFQ?" con subtítulo de campus
- Reloj en tiempo real (zona horaria Galápagos — `Pacific/Galapagos`, UTC-6)
- Fecha en español
- **Mareas del día**: pleamares y bajamares de San Cristóbal con hora y altura exacta (NOAA)
- Etiqueta de rango de la semana actual

**Panel derecho**
- Lista de eventos de la semana actual, agrupados por día
- Auto-scroll suave (va y vuelve entre el inicio y el final de la lista)
- Etiqueta "AHORA" con pulso animado en eventos que están ocurriendo en este momento
- Estado vacío elegante si no hay eventos
- Estado de carga mientras se obtienen los datos

### APIs conectadas

#### 1. Calendario ICS (Outlook 365)

```
URL: https://outlook.office365.com/owa/calendar/...@usfq.edu.ec/.../calendar.ics
```

- Se obtiene con CORS proxies en cascada (allorigins → corsproxy.io → directo)
- Se parsea completamente en el cliente sin dependencias externas
- Soporta eventos únicos y recurrentes (RRULE: `DAILY`, `WEEKLY`, `MONTHLY`)
- Solo muestra eventos de la **semana actual** (lunes–domingo)
- Se actualiza automáticamente cada **10 minutos**
- Si falla, carga datos de respaldo hardcodeados (actividades típicas del campus)

#### 2. Mareas — NOAA Tides & Currents

```
Endpoint: https://api.tidesandcurrents.noaa.gov/api/prod/datagetter
Estación: 9992401 (San Cristóbal, Galápagos)
```

- **Gratuita, pública, sin API key**
- Devuelve los extremos del día (pleamar/bajamar) con hora y altura exacta
- Parámetros: `product=predictions`, `datum=MLLW`, `interval=hilo`, `units=metric`
- Se actualiza cada **1 hora**
- Muestra las 4 mareas del día con indicador visual de cuál es la próxima
- Indica si la marea está subiendo o bajando
- Si falla, muestra estado de "Sin datos" sin romper la UI
- Para Santa Cruz/Baltra se puede usar la estación `TWC0291`

### Tipos de eventos y colores

| Tipo        | Color       | Palabras clave detectadas                                           |
|-------------|-------------|---------------------------------------------------------------------|
| `academico` | Azul cyan   | coloquio, taller, charla, seminario                                 |
| `club`      | Verde       | club, training, creative                                            |
| `deporte`   | Naranja     | basket, peloteo, jiu, alfa                                          |
| `social`    | Violeta     | pelis, pizza, celebra, almuerzo                                     |
| `bienestar` | Verde claro | mental, salud, bienestar, yoga                                      |
| `evento`    | Gris        | (cualquier otro)                                                    |

### Sistema de diseño

| Variable         | Valor                          |
|------------------|--------------------------------|
| Fondo            | `#041C32` — azul noche marino  |
| Primario         | `#0F4C81` — azul océano        |
| Acento           | `#00B4D8` — cian galápagos     |
| Verde            | `#2DD4A0`                      |
| Naranja          | `#F4A261`                      |
| Texto            | `#F1F6F9`                      |
| Texto muted      | `#9BA4B5`                      |
| Superficie       | `rgba(4,41,58,0.55)` + blur    |
| Fuente serif     | Playfair Display (títulos)     |
| Fuente sans      | Outfit (cuerpo, datos)         |
| Radio tarjeta    | `24px`                         |

El fondo usa capas de ondas SVG animadas + orbes difuminados + partículas flotantes para evocar el entorno oceánico de Galápagos.

---

## Configuración — `html/config.js`

Todas las constantes configurables del sistema están en este archivo. Las pantallas nuevas deben importarlo:

```html
<script src="config.js"></script>
```

Variables disponibles via `KIOSK_CONFIG`:

| Clave                  | Descripción                                      |
|------------------------|--------------------------------------------------|
| `ICS_URL`              | URL del calendario ICS de Outlook                |
| `CALENDAR_REFRESH_MS`  | Intervalo de refresco del calendario (ms)        |
| `NOAA_ENDPOINT`        | URL base de la API de mareas NOAA                |
| `NOAA_STATION`         | ID de estación NOAA (`9992401` = San Cristóbal)  |
| `TIDES_REFRESH_MS`     | Intervalo de refresco de mareas (ms)             |
| `LAT` / `LNG`          | Coordenadas de San Cristóbal                     |
| `TIMEZONE`             | `Pacific/Galapagos` (UTC-6)                      |
| `AUTO_SCROLL_SPEED`    | Velocidad de auto-scroll en px/frame             |
| `AUTO_SCROLL_PAUSE_MS` | Pausa al llegar al límite del scroll             |
| `CORS_PROXIES`         | Array de funciones proxy para el ICS             |
| `SCREENS`              | Lista de pantallas con URL y duración para ciclado |

---

## Ciclado de pantallas — `html/kiosko.html`

Lanzador que muestra las pantallas dentro de un iframe y cicla automáticamente entre ellas. Ideal para configurar en el navegador en modo kiosko.

Las pantallas y sus duraciones se configuran en `KIOSK_CONFIG.SCREENS`.

**Cómo abrir en modo kiosko (Chrome/Chromium):**

```bash
chromium-browser --kiosk --noerrdialogs --disable-infobars \
  --no-first-run file:///ruta/al/proyecto/html/kiosko.html
```

---

## Cómo añadir eventos al calendario

Los eventos se gestionan desde el calendario de Outlook configurado en `KIOSK_CONFIG.ICS_URL`. Cualquier evento creado en ese calendario aparecerá automáticamente en el kiosko en los próximos 10 minutos.

Para cambiar el calendario, actualizar la URL en `config.js`.

---

## Pantallas en desarrollo (`screens/`)

| Archivo               | Estado       | Descripción                                |
|-----------------------|--------------|--------------------------------------------|
| `anuncios.html`       | Prototipo    | Información estática del campus (horarios, servicios, contactos) |

---

## Despliegue

El proyecto no requiere servidor ni build. Es HTML puro que puede abrirse directamente desde el sistema de archivos o servirse con cualquier servidor estático:

```bash
# Servidor rápido de desarrollo
cd html/
python3 -m http.server 8080
# Abrir en: http://localhost:8080/kiosko.html
```

Para producción en pantallas de la universidad, se recomienda servir desde un servidor local en la red de la USFQ para evitar dependencia de CORS proxies externos.
