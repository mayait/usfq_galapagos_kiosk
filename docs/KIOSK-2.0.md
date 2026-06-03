# Kiosko USFQ Galápagos 2.0 — Arquitectura y Operación

> Estado: **EN PRODUCCIÓN** desde junio 2026 · Pantalla "Mosaico" + API PHP unificado.
> Reemplaza el modelo anterior (HTML estático leyendo Outlook por proxies CORS + NOAA + CMS de afiches).
> Documento operativo: cómo funciona, cómo se mantiene, cómo se despliega.

---

## 1. Qué es

Sistema de señalización para los monitores 1920×1080 del campus USFQ San Cristóbal. Una sola
pantalla **Mosaico** (bento de pósters) muestra, con datos en vivo:

- **Clubes** activos (columna izquierda, color por categoría).
- **Agenda semanal** de eventos (grid paginado).
- **Destacado rotativo** que intercala eventos importantes (con su afiche) y **convenios/promos**
  para la comunidad USFQ (ej. Midori 7%).
- **Mareas + estado del mar**: alturas/horas (NOAA) y oleaje, temperatura del agua, viento y
  amanecer/atardecer (Stormglass).
- Reloj y fecha en hora de Galápagos (UTC-6).

El diseño nació de un bundle de Claude Design ("Kiosko 2.0", dirección Mosaico).

---

## 2. Arquitectura

```
Outlook ICS ─┐                         ┌───────────────────────────────────────────┐
NOAA tides  ─┤ (server-side, cron 3h)  │  api.julianmaya.com  (PHP 8 · cPanel)      │
Stormglass ─┘                          │  docroot: /home/humaiyld/api.julianmaya.com │
                                        │                                            │
                                        │  lib/ics.php      parser ICS + TZID + RRULE │
                                        │  lib/marine.php   NOAA + Stormglass + sol   │
                                        │  api/kiosk.php  ← FEED ÚNICO (JSON)         │
                                        │  api/events.php   (compat afiches)          │
                                        │  cron/fetch_marine.php  → data/marine.json  │
                                        │  admin/*          panel de gestión          │
                                        │  data/*.json      "base de datos" plana     │
                                        │  kiosk/           la pantalla (front)        │
                                        └───────────────────────────────────────────┘
                                                         │ (fetch HTTPS, CORS *)
                                                         ▼
                                        Monitor del campus → https://api.julianmaya.com/kiosk/
```

- **Sin base de datos.** Todo en JSON plano en `data/` (suficiente para el volumen, cero deps).
- **El kiosko hace UN fetch** a `api/kiosk.php` y renderiza. Sin proxies CORS (el ICS se ingiere
  en el servidor). Detrás de **Cloudflare** (TLS wildcard `*.julianmaya.com`).

### URLs
| Recurso | URL |
|---|---|
| Pantalla (kiosko) | `https://api.julianmaya.com/kiosk/` |
| Feed del kiosko | `https://api.julianmaya.com/api/kiosk.php` |
| API compat (solo afiches) | `https://api.julianmaya.com/api/events.php` |
| Panel admin | `https://api.julianmaya.com/admin/` |
| Cron (manual, por URL) | `https://api.julianmaya.com/cron/fetch_marine.php?token=…` |

---

## 3. Código fuente (en el repo)

```
cms/                         → backend PHP (se despliega a la RAÍZ del docroot)
├── config.php               ⚙️ claves, rutas, calendarios por defecto
├── .htaccess                índice + bloqueo de .json/.md por URL
├── lib/
│   ├── ics.php              parser ICS + resolveMicrosoftTZID + expandRRule + detectCat
│   └── marine.php           fetchNoaaTides + fetchStormglass + seaStateLabel + sunTimes
├── api/
│   ├── kiosk.php            feed único (clubs, days, pinned, promos, tides, marine, week)
│   └── events.php           endpoint legado (solo afiches)
├── cron/fetch_marine.php    refresca data/marine.json (cada 3 h)
├── admin/
│   ├── index.php            dashboard de eventos (+ tipo evento/importante)
│   ├── upload.php / edit.php formulario de evento (con campos de destacado)
│   ├── clubs.php            CRUD de clubes
│   ├── promos.php           CRUD de convenios/promos (con foto)
│   ├── calendars.php        gestión de URLs ICS ("calendarios")
│   ├── admin.css / _nav.php estilos + navegación compartidos
│   └── login/logout/delete.php
├── data/                    JSON: events, clubs, promos, calendars, marine (+ .htaccess)
└── uploads/                 afiches subidos (2026-MM/) + promos/

kiosk/                       → frontend (se despliega a docroot /kiosk/)
├── index.html               la pantalla Mosaico (markup + CSS del diseño + render JS)
├── data.js                  carga en vivo del feed (con FALLBACK si el API cae)
├── tokens.css               sistema de marca USFQ (colores, tipografías)
├── fonts/                   Jost + University Roman
└── assets/                  logos + fotos por categoría
```

Las "brains" de parseo de calendario (ICS, TZIDs de Microsoft, RRULE, `detectType`, `stripEmojis`)
se **portaron desde** el viejo `html/index.html` a `cms/lib/ics.php` — misma lógica probada, ahora
en el servidor.

---

## 4. Modelo de datos (JSON en `cms/data/`)

- **events.json** — afiches. Campos: `id, title, image, event_date, publish_from, publish_until`
  + opcionales para destacado: `event_type` (`evento`|`importante`), `cat`, `event_time`, `place`,
  `kicker`, `subtitle`, `partners`. Los `importante` activos alimentan el destacado con su afiche.
- **clubs.json** — `name, cat, days, time, place, enabled`. `cat` ∈ {deportes, bienestar, mar, arte, letras}.
- **promos.json** — convenios/promos: `kind` (`convenio`|`promo`), `label, audience, name, tagline,
  discount, discountNote, category, place, terms, image, accent, enabled`.
- **calendars.json** — fuentes ICS: `id, name, url, enabled`.
- **marine.json** — escrito por el cron: `{updated_at, sources, tides:{state,station,points[]},
  marine:{seaState,waveHeight,swellHeight,swellPeriod,waterTemp,airTemp,wind:{speed,dir},sunrise,sunset}}`.

### Forma del feed `api/kiosk.php`
```jsonc
{ "ok": true, "generated_at": "...",
  "week":  { "label": "Semana del 1 al 7 de junio", "count": 27 },
  "clubs": [{ "name","cat","days","time","place" }],
  "pinned":[{ "kicker","title","subtitle","dateNum","month","year","time","place","partners","photo","flyer","accent" }],
  "promos":[{ "kind","label","audience","name","tagline","discount","discountNote","category","place","terms","photo","accent" }],
  "days":  [{ "dow","date","today","items":[{ "type","cat|ref","title","start","end","place","image_url" }] }],
  "tides": { "state","station","points":[{ "kind","time","h","next" }] },
  "marine":{ "seaState","waveHeight","swellPeriod","waterTemp","wind":{"speed","dir"},"sunrise","sunset" } }
```

---

## 5. Operación diaria (para quien administra)

1. **Eventos / afiches** → `admin/` → *Eventos* → *+ Nuevo evento*. Tipo "Importante" para que
   ocupe el espacio destacado grande (rellena kicker, subtítulo, hora, lugar, aliados).
2. **Clubes** → pestaña *Clubes*. La lista permanente de la izquierda.
3. **Convenios/Promos** → pestaña *Convenios*. Rotan en el destacado junto a los eventos importantes.
4. **Calendarios** → pestaña *Calendarios*. Agrega/quita URLs ICS de Outlook; botón **Probar** valida.

Los profesores siguen creando eventos en **Outlook** como siempre: el API los ingiere solo.

---

## 6. Mareas y estado del mar

- `cron/fetch_marine.php` corre **cada 3 h** (cron de cPanel) y escribe `data/marine.json`.
- **NOAA** (estación 9992401, gratis) → 4 mareas del día + estado (subiendo/bajando).
- **Stormglass** (key en `config.php`, plan free 10 req/día → 8/día con 3 h) → oleaje, swell,
  temperatura del agua, viento. **La key nunca llega al navegador.**
- **Amanecer/atardecer** se calculan localmente (`date_sun_info`), sin gastar llamadas.
- **Resiliencia**: si Stormglass falla, conserva el último estado del mar; si el cron no corrió en
  >6 h, `api/kiosk.php` refresca las mareas con NOAA en vivo (gratis).
- Escala de estado del mar (altura de ola): Tranquilo <0.5 · Ligero <1.25 · Moderado <2.5 ·
  Agitado <4 · Fuerte ≥4 (m).

---

## 7. Despliegue

**Dos destinos** (frontend y backend van por separado):

### Frontend → GitHub Pages (lo que ven las pantallas)
- Pages sirve la rama **`main`** (raíz) en `https://mayait.github.io/usfq_galapagos_kiosk/`.
- Las pantallas del campus cargan esa URL. El kiosko vive en **`/kiosk/`**; la raíz `/` y la
  ruta legada `/html/` **redirigen** a `/kiosk/` (así cualquier pantalla sigue funcionando).
- Hay un `.nojekyll` en la raíz para servir los archivos tal cual (sin Jekyll).
- El kiosko lee el API por HTTPS (CORS abierto), así que funciona desde el origen de Pages.
- Publicar = hacer merge a `main`; Pages reconstruye solo.

### Backend (PHP) → Namecheap cPanel, **subido manualmente por SFTP**
- ⚠️ **Los secretos NO están en el repo** (es público). `cms/config.php` versionado lleva
  `__CAMBIAR_EN_SERVIDOR__` como placeholder. Los valores reales (contraseña admin, API key de
  Stormglass, token del cron) viven **solo** en el `config.php` del servidor, que se edita/sube
  por SFTP a mano.

Conexión SFTP (Namecheap, ver [hosting](../README.md) / memoria del proyecto):

```
host server346.web-hosting.com  ·  puerto 21098  ·  user humaiyld
key  ~/.ssh/id_ed25519  (-o IdentitiesOnly=yes -o IdentityAgent=none)
```

- `cms/*`   → `/home/humaiyld/api.julianmaya.com/` (raíz del docroot)
- `kiosk/`  → `/home/humaiyld/api.julianmaya.com/kiosk/`
- Permisos: `data/`, `uploads/`, `cron/` en 755 (PHP corre como `humaiyld`, escribe como dueño).

**Cron** (instalado vía cPanel API2 `Cron::add_line`):
```
7 */3 * * *  curl -ks "https://api.julianmaya.com/cron/fetch_marine.php?token=<CRON_TOKEN>" >/dev/null 2>&1
```

**TLS**: Cloudflare (proxy naranja, Universal SSL wildcard). No se usa AutoSSL del origen.

---

## 8. Seguridad / credenciales

- `admin/` protegido por sesión PHP. Usuario `admin`, contraseña en `config.php` (`ADMIN_PASSWORD`).
- `cron` protegido por `CRON_TOKEN` cuando se llama por URL.
- `data/*.json` bloqueado por `.htaccess` (raíz + `data/`) → 403 directo por URL.
- **Las claves (Stormglass, admin, token) viven en `config.php`**, que se ejecuta como PHP y nunca
  se sirve como texto. **Recomendado**: rotarlas y guardarlas en 1Password.
  `config.php` está versionado con las claves de arranque — considerar moverlas a variables de
  entorno o a un archivo fuera del docroot si el repo se hace público.

---

## 9. Estado del legado y pendientes

- `html/index.html` y variantes (`index_blanco/blue/dark_logo/movil.html`) son el sistema 1.0.
  Siguen como **fallback** hasta migrar todas las pantallas del campus a `…/kiosk/`.
  **Pendiente**: una vez migradas, eliminar las 4 variantes duplicadas (ver `PROJECT-OVERVIEW.md` §6).
- Opcional: subdominio propio `kiosko.julianmaya.com` en lugar de la subcarpeta `/kiosk/`.
- Cargar la lista real de clubes y convenios (hoy: semilla del diseño + Midori 7%).
```
