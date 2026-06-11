# Galápagos Mundialista — Cromos del staff + Álbum PALINI 26

Sistema de reconocimiento al staff por el Mundial 2026: **cromos estilo Panini**
que rotan en las pantallas del campus, más un **álbum colaborativo multijugador**
donde el equipo se escribe mensajes.

---

## 1. Las piezas

| Pieza | Archivo | URL |
|---|---|---|
| Kiosko (pantallas) | `kiosk/index.html` | GitHub Pages + `api.julianmaya.com/kiosk/` (espejo manual SFTP) |
| Álbum PALINI 26 | `cms/cromos/index.html` | `https://api.julianmaya.com/cromos/` |
| Endpoint del álbum | `cms/api/cromos.php` | `https://api.julianmaya.com/api/cromos.php` |
| Feed del kiosko | `cms/api/kiosk.php` | `https://api.julianmaya.com/api/kiosk.php` (campo `staff`) |
| Admin de cromos | `cms/admin/staff.php` | `https://api.julianmaya.com/admin/staff.php` |
| Datos del staff | `cms/data/staff.json` | versionado en el repo |
| Mensajes | `cms/data/cromos.json` | **NO versionado** (privado) — solo en el servidor |
| Fotos | `uploads/staff/cromo_NNN.jpg` | subidas por SFTP o desde el admin |

## 2. Flujo de datos

```
cromos.csv + fotos/  ──(sync manual, ver §4)──►  staff.json + uploads/staff/
                                                        │
                          ┌─────────────────────────────┤
                          ▼                             ▼
                 api/kiosk.php (campo staff)    api/cromos.php (álbum)
                          │                             │
                          ▼                             ▼
                 Pantallas del campus            PALINI 26 (móvil/web)
                 (slides con muro anónimo)       (escribir mensajes, muros,
                                                  ranking, orden aleatorio)
                                                        │
                                                        ▼
                                                 data/cromos.json
                                                 (fuente de verdad de mensajes)
```

## 3. Reglas del juego (álbum PALINI 26)

- Al entrar eliges **quién eres** (lista abierta del staff, sin clave).
- Desbloqueas el cromo de un compañero **escribiéndole un mensaje** (≥12 chars,
  máx. 600). Un mensaje por par `from→to`: reescribir **actualiza**, no duplica.
- Tu propio cromo es tu **muro**: la carta arriba y abajo todos los mensajes
  que te escribieron — **anónimos y en desorden estable** (sin autor ni hora).
- **Ranking doble** (modal 🏆):
  1. **💌 Cromos más queridos** — quién ha *recibido* más mensajes (top 10).
  2. **🏆 Álbum completo primero** — quién ha *pegado* más cromos; meta = N−1
     (escribirle a todos menos a ti). Completados ordenados por fecha de término.
- Los cromos del álbum salen en **orden aleatorio por visita** (seed estable
  por sesión: no se reordena con el refresco del feed cada 45 s).

## 4. Cómo agregar / quitar cromos (sync desde el CSV)

Fuente: `/Users/julian/Downloads Chats/Cromos Mundial/` → `cromos.csv`
(columnas `nombre,puesto,archivo`) + carpeta `fotos/`.

El CSV es la **fuente de verdad**: el sync reconstruye `staff.json` en el orden
del CSV, conservando `enabled`/`created_at` de los existentes. Pasos (Claude lo
hace con "hay nuevos cromos"):

1. Diff CSV ↔ `cms/data/staff.json` (clave = nombre de archivo).
2. Regenerar `staff.json` (id = `cromoNNN`, `image = staff/cromo_NNN.jpg`).
3. Subir fotos nuevas a `uploads/staff/` y el `staff.json` a `data/` por SFTP.
4. Verificar `api/kiosk.php` (conteo) y `api/cromos.php` (total/meta).
5. Commit de `cms/data/staff.json`.

Exclusiones vigentes: `cromo_016` (MIKA PLU, eliminado a pedido, sin foto).
Huecos en la numeración (009/011/013/015/038/045 sin entrada en CSV o sin uso)
son normales — manda el CSV. *Ojo*: si una foto aparece en la carpeta pero no
está en el CSV, NO entra.

También se puede gestionar individualmente desde `admin/staff.php`
(agregar con foto, pausar/activar, eliminar).

## 5. Integración con el kiosko (pantallas)

- `api/kiosk.php` expone `staff[]`: `{name, role, photo, msgs[]}` — `msgs` son
  los **6 mensajes más recientes** del muro, **sin autor** (anonimato real: el
  `from` nunca sale del servidor) y **escapados server-side** (contenido
  escrito por el staff → sin riesgo de inyección en pantalla).
- El hero del kiosko intercala **2 cromos entre cada evento/promo** (~75% de
  los slides). Los cromos salen de una **cola persistente barajada**: tandas de
  8 sin repetir a nadie hasta agotar la lista; con 45 cromos, todos aparecen en
  ~12 min y el reparto por hora es parejo.
- Slide del cromo: carta SIEMPRE completa al 100% del alto (posicionamiento
  absoluto — `height:%` en grid recortaba en algunos motores), kicker
  "Mundial 2026 · Galápagos Mundialista", nombre, puesto, hasta **2 citas**
  del muro (la más reciente + una al azar) en burbujas doradas, y sello
  TEAM USFQ GALÁPAGOS.

## 6. Backend: api/cromos.php

- `GET` → `{staff, total, target, ranking, popular, players}`.
  - `?me=<id>` añade `sent` (mis mensajes por destinatario), `sent_count`, `wall`.
  - `?wall=<id>` → muro público de esa persona (con autor — lo usa el álbum
    internamente; el anonimato se aplica en la UI y en el feed del kiosko).
- `POST {from, to, msg}` → valida ids contra staff activo, longitud 12–600,
  upsert por par, **flock + escritura atómica** (tmp+rename) para escrituras
  concurrentes.
- CORS abierto (el álbum y el kiosko consumen cross-origin).

## 7. Privacidad y seguridad

- `data/cromos.json` **no se versiona** (repo público): contiene quién escribió
  a quién. Gitignore: `cms/data/cromos.json`, `cms/data/*.lock`.
- El `.htaccess` raíz bloquea el acceso directo a cualquier `.json` (403).
- En pantallas y muros los mensajes son **anónimos**; el autor solo existe en
  el JSON del servidor (necesario para el upsert y el ranking).
- Texto de mensajes: `strip_tags` al guardar + `htmlspecialchars` al servir
  al kiosko.

## 8. Operaciones útiles

```bash
# Borrar TODOS los mensajes (reset del juego)
printf '{\n    "messages": []\n}\n' > /tmp/ce.json
sftp <opciones> humaiyld@server346.web-hosting.com <<'EOF'
cd api.julianmaya.com
put /tmp/ce.json data/cromos.json
EOF

# Ver estado del juego
curl -s https://api.julianmaya.com/api/cromos.php | jq '{total, target, players, popular}'

# Ver muros que llegan al kiosko
curl -s https://api.julianmaya.com/api/kiosk.php | jq '[.staff[] | select(.msgs|length>0) | {name, msgs}]'
```

Conexión SFTP: ver `docs/DEPLOY-CMS-handoff.md` §3.

## 9. Historial de decisiones

- **2026-06-10** — Cromos en el hero del kiosko (no sección aparte); rotación
  aleatoria intercalada con eventos/promos. Identidad en el álbum por lista
  abierta sin clave (confianza interna).
- **2026-06-10** — Mensajes **anónimos** en pantalla y muros (pedido de Julian).
- **2026-06-10** — Muro: el cromo arriba + comentarios abajo (sin botón "Ver cromo").
- **2026-06-11** — Ranking doble (queridos + carrera) y orden aleatorio del álbum.
- **2026-06-11** — Prioridad del staff en pantallas: 2 cromos por cada evento/promo,
  cola persistente que garantiza cobertura total.
- Eliminado: cromo_016 (MIKA PLU). Excluidas: fotos sin entrada en el CSV.
