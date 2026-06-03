# Handoff — Despliegue del Events CMS (PHP) en `api.julianmaya.com`

> Documento auto-contenido para que otro agente ejecute el despliegue del CMS de eventos
> a la raíz del subdominio `api.julianmaya.com` (hosting cPanel Namecheap).
> Estado al redactar: **DNS + TLS ya configurados**; falta subir archivos y configurar permisos/cron.

---

## 0. Contexto y estado actual (YA HECHO — no repetir)

| Pieza | Estado |
|---|---|
| **Cloudflare DNS** | `A api.julianmaya.com → 66.29.146.108`, **proxied (naranja)**, zona `julianmaya.com` (zone_id `737b4e4b5370d8dc3b85d346b6030044`) |
| **TLS** | Terminado por **Cloudflare** (Universal SSL / Let's Encrypt, SAN `*.julianmaya.com`). Modo de zona = **Full**. Namecheap NO emite AutoSSL on-demand: no se usa cert del origen. |
| **cPanel** | Subdominio `api.julianmaya.com` creado. Docroot: `/home/humaiyld/api.julianmaya.com` (vacío salvo `cgi-bin`). |
| **`cms/config.php`** | `SITE_URL` ya está en `https://api.julianmaya.com` ✓ |

Verificación rápida de que el edge sirve bien (no depende de DNS local):
```bash
curl -s -o /dev/null -w "%{http_code}\n" --resolve api.julianmaya.com:443:172.67.204.168 https://api.julianmaya.com/
# espera 200 (sirve cert Let's Encrypt de Cloudflare, NO *.web-hosting.com)
```

---

## 1. Fuente y destino

- **Origen local:** `/Users/julian/dev/usfq_galapagos_kiosk/cms/`
- **Destino remoto:** `/home/humaiyld/api.julianmaya.com/` (= docroot del subdominio; la raíz del API/admin)
- Tras subir: API en `https://api.julianmaya.com/api/events.php`, admin en `https://api.julianmaya.com/admin/login.php`.

Estructura local que se debe subir:
```
cms/
├── .htaccess            # DirectoryIndex admin/index.php + deny .json/.md
├── config.php           # secretos: ADMIN_PASSWORD, CRON_TOKEN, STORMGLASS_KEY
├── admin/               # login, dashboard, upload, edit, delete, clubs, promos, calendars
├── api/                 # events.php (público), kiosk.php
├── cron/fetch_marine.php  # job mareas (NOAA + Stormglass) cada 3 h
├── lib/                 # ics.php, marine.php
├── data/                # *.json (events, clubs, promos, calendars, marine), ics_cache.txt, htaccess.txt
└── (uploads/ NO existe local — crear en el server)
```

---

## 2. ⚠️ Restricciones del hosting (leer antes de actuar)

1. **Shell SSH DESHABILITADO** en la cuenta (`"Shell access is not enabled"`). Implica:
   - ❌ NO `rsync` over ssh, NO `ssh humaiyld@... 'comando'`, NO ejecutar `php` por CLI a mano.
   - ✅ Operaciones de archivo SOLO por **SFTP** (que sí soporta `put -r`, `chmod`, `rename`, `mkdir`).
   - ✅ Tareas administrativas (cron, permisos masivos, etc.) por **cPanel UAPI** (HTTPS) o File Manager.
   - ✅ Ejecución de PHP SOLO vía HTTP(S).
2. **DNS local puede estar cacheado** (TTL 300). Para verificar usa `--resolve ...:172.67.204.168` contra el edge de Cloudflare.

---

## 3. Credenciales (vía 1Password CLI `op`, ya autenticado en esta máquina)

```bash
# Usuario/clave del sistema cPanel (sirve para SFTP y para UAPI Basic Auth)
CPUSER=humaiyld
CPPASS="$(op item get fxdpe7f4z3dchtwsmh7weyvtgm --fields label=password --reveal)"   # = YURVQjC2Zx3P
# Token Cloudflare (sólo si hay que tocar DNS; normalmente NO hace falta ya)
CFTOKEN="$(op item get rl3mymealnojc75c6f2xn3cpjm --fields label=credential --reveal)"
```

**Conexión SFTP** (la llave por defecto va por el agente de 1Password; hay que forzar la llave directa):
```bash
SFTP_OPTS="-i ~/.ssh/id_ed25519 -o IdentitiesOnly=yes -o IdentityAgent=none -P 21098 -o StrictHostKeyChecking=accept-new"
SFTP_HOST="humaiyld@server346.web-hosting.com"     # = 66.29.146.108, puerto 21098 (NO 22)
```

**Base UAPI cPanel:** `https://server346.web-hosting.com:2083` (Basic Auth `$CPUSER:$CPPASS`).
Nota: el puerto 2083 (UAPI) es distinto al 21098 (SFTP).

---

## 4. Pre-flight: editar `config.php` antes de subir

`SITE_URL` ya está correcto. **Cambiar estos dos secretos por defecto** en `/Users/julian/dev/usfq_galapagos_kiosk/cms/config.php`:

```php
define('ADMIN_PASSWORD', 'usfq2026');             // ← línea 11: poner una clave fuerte real
define('CRON_TOKEN',     'galapagos-marea-2026');  // ← línea 49: poner un token aleatorio
```
Sugerencia: generar con `openssl rand -hex 16`. **Confirmar con Julian** qué password de admin quiere, o generar uno y reportárselo.
(`STORMGLASS_KEY` ya viene puesta en línea 45 — dejarla salvo que Julian indique otra.)

---

## 5. Crear carpeta `uploads/` local (para subirla con permisos y un index vacío)

```bash
cd /Users/julian/dev/usfq_galapagos_kiosk/cms
mkdir -p uploads
# placeholder para que git/sftp la creen aunque esté vacía:
:> uploads/.keep
```

---

## 6. Subir el árbol por SFTP (recursivo)

`put -r` sube el contenido. Subimos *dentro* de `api.julianmaya.com/` en el server.

```bash
cd /Users/julian/dev/usfq_galapagos_kiosk/cms
sftp $SFTP_OPTS $SFTP_HOST <<'EOF'
cd api.julianmaya.com
put -r admin
put -r api
put -r cron
put -r lib
put -r data
put -r uploads
put .htaccess
put config.php
put README.md
ls -la
bye
EOF
```
> Si algún `put -r` falla a medias por archivos sueltos, repetir el comando (sftp sobrescribe).
> Verifica al final que existan `admin/ api/ cron/ lib/ data/ uploads/ config.php .htaccess`.

---

## 7. Permisos (vía SFTP — el protocolo soporta `chmod`)

```bash
sftp $SFTP_OPTS $SFTP_HOST <<'EOF'
cd api.julianmaya.com
chmod 755 uploads
chmod 755 data
chmod 644 data/events.json
chmod 644 data/clubs.json
chmod 644 data/promos.json
chmod 644 data/calendars.json
chmod 644 data/marine.json
chmod 644 config.php
bye
EOF
```
> En Namecheap LiteSpeed el PHP corre como el usuario `humaiyld`, así que 644 en `config.php` es suficiente
> (el `.htaccess` raíz ya bloquea `.json` y `.md` por URL, así que los JSON no son accesibles públicamente).

## 7b. (Opcional) Activar la protección extra de `data/`

El repo trae `data/htaccess.txt` (deny a `events.json`). El `.htaccess` raíz ya niega TODO `.json`, así que es redundante. Si lo quieres igual:
```bash
sftp $SFTP_OPTS $SFTP_HOST <<'EOF'
cd api.julianmaya.com/data
rename htaccess.txt .htaccess
bye
EOF
```

---

## 8. Cron del módulo marino (mareas + estado del mar, cada 3 h)

`cron/fetch_marine.php` refresca `data/marine.json`. Como no hay shell, crear el cron por **UAPI** (corre el binario PHP del sistema por CLI):

```bash
curl -sk -u "$CPUSER:$CPPASS" \
  "https://server346.web-hosting.com:2083/execute/Cron/add_line" \
  --data-urlencode "command=/usr/local/bin/php /home/humaiyld/api.julianmaya.com/cron/fetch_marine.php >/dev/null 2>&1" \
  --data-urlencode "minute=15" \
  --data-urlencode "hour=*/3" \
  --data-urlencode "day=*" \
  --data-urlencode "month=*" \
  --data-urlencode "weekday=*"
# Verifica con: curl -sk -u "$CPUSER:$CPPASS" "https://server346.web-hosting.com:2083/execute/Cron/list_cron"
```
> Si la ruta de `php` falla, prueba `/usr/bin/php` o consulta la activa por UAPI.
> Alternativa sin cron: dispararlo por URL con token (ver §9), pero el cron es lo correcto para producción.

**Primer llenado de `marine.json` inmediatamente** (vía URL + token; usa el CRON_TOKEN que pusiste en §4):
```bash
curl -s "https://api.julianmaya.com/cron/fetch_marine.php?token=EL_TOKEN_QUE_PUSISTE"
```

---

## 9. Verificación final

```bash
# API pública de eventos (debe responder JSON {"ok":true,...})
curl -s https://api.julianmaya.com/api/events.php | head -c 400; echo

# API del kiosko (agregada)
curl -s -o /dev/null -w "kiosk.php -> %{http_code}\n" https://api.julianmaya.com/api/kiosk.php

# Admin login accesible (200)
curl -s -o /dev/null -w "login -> %{http_code}\n" https://api.julianmaya.com/admin/login.php

# Los JSON NO deben ser accesibles por URL (espera 403)
curl -s -o /dev/null -w "data/events.json -> %{http_code} (esperado 403)\n" https://api.julianmaya.com/data/events.json

# Si el DNS local sigue cacheado, fuerza contra el edge:
#   curl ... --resolve api.julianmaya.com:443:172.67.204.168 ...
```

**Checklist de éxito:**
- [ ] `api/events.php` devuelve `{"ok":true, ...}` con los eventos de `data/events.json`
- [ ] `admin/login.php` carga (200) y el login funciona con `ADMIN_PASSWORD`
- [ ] `data/*.json` da 403 por URL
- [ ] `uploads/` con permiso 755 (probar subir un afiche desde el admin)
- [ ] cron listado en `Cron/list_cron`; `marine.json` con datos frescos
- [ ] Reportar a Julian el `ADMIN_PASSWORD` y `CRON_TOKEN` finales

---

## 10. Notas / gotchas

- **No tocar DNS ni SSL**: ya quedó. Si por error el cert da `*.web-hosting.com`, es caché DNS local, no un problema real (verifica con `--resolve` al edge).
- **Subidas de imágenes**: el admin guarda en `uploads/AAAA-MM/`. Asegúrate de que `uploads/` sea 755 o las subidas fallarán.
- **`config.php` tiene secretos** (Stormglass key, admin pass, cron token). No commitear cambios con secretos reales al repo; si se versiona, usar valores placeholder.
- **Frontend del kiosko**: tras desplegar, actualizar en `html/config.js` la URL del API a `https://api.julianmaya.com/api/...` (fuera del alcance de este handoff, pero es el siguiente paso lógico).
