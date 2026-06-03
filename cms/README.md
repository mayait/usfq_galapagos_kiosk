# CMS / API — USFQ Galápagos (Kiosko 2.0)

Sistema ligero para gestión y publicación de eventos en pantallas del campus.
Sin base de datos. PHP puro. Listo para hosting compartido Namecheap.

> **Nota 2.0 (jun 2026):** este CMS se amplió a un **API unificado** que también gestiona clubes,
> convenios/promos, calendarios ICS y mareas+estado del mar, y alimenta la pantalla **Mosaico**.
> Documentación de arquitectura y operación: **[`docs/KIOSK-2.0.md`](../docs/KIOSK-2.0.md)**.
> Feed del kiosko: `api/kiosk.php`. Este README cubre el endpoint legado `api/events.php`.

---

## Estructura del proyecto

```
events-cms/
├── config.php              ← ⚙️  CONFIGURACIÓN PRINCIPAL (edita esto primero)
├── .htaccess
│
├── admin/
│   ├── login.php           ← Pantalla de acceso
│   ├── index.php           ← Dashboard con lista de eventos
│   ├── upload.php          ← Subir nuevo evento
│   ├── edit.php            ← Editar título y fechas
│   ├── delete.php          ← Eliminar evento + imagen
│   └── logout.php
│
├── api/
│   └── events.php          ← 🌐 API pública (consume desde el frontend)
│
├── data/
│   ├── events.json         ← Base de datos (JSON plano)
│   └── .htaccess           ← Protege el JSON de acceso externo
│
└── uploads/
    └── 2026-04/            ← Imágenes organizadas por mes
        └── abc123_nombre.jpg
```

---

## Instalación en Namecheap (cPanel)

### 1. Subir archivos

Via File Manager o FTP, sube toda la carpeta `events-cms/` a:

```
public_html/events-cms/
```

O a una subcarpeta que prefieras.

### 2. Editar config.php

Abre `config.php` y cambia estas dos líneas:

```php
define('ADMIN_USER',     'admin');
define('ADMIN_PASSWORD', 'usfq2026');        // ← pon tu contraseña real
define('SITE_URL',       'https://tudominio.com/events-cms'); // ← tu URL real
```

### 3. Permisos de carpetas

En File Manager o por SSH:

```bash
chmod 755 uploads/
chmod 755 data/
chmod 644 data/events.json
```

En cPanel > File Manager: selecciona la carpeta `uploads/`, clic derecho > Change Permissions > 755.

### 4. Verificar PHP

El sistema requiere PHP 8.0+. En Namecheap cPanel puedes elegir la versión en:
**Software > Select PHP Version**

Extensiones necesarias (vienen activadas por defecto en Namecheap):
- `fileinfo`
- `json`
- `session`

---

## Uso del admin

1. Abre `https://tudominio.com/events-cms/admin/login.php`
2. Ingresa con el usuario y contraseña de `config.php`
3. Haz clic en **"+ Nuevo evento"** para subir
4. Desde el dashboard puedes **Editar** (título y fechas) o **Borrar** cada evento

---

## API — Referencia completa

### Endpoint

```
GET https://tudominio.com/events-cms/api/events.php
```

### Parámetros (todos opcionales)

| Parámetro | Tipo       | Descripción                              | Default   |
|-----------|------------|------------------------------------------|-----------|
| `from`    | YYYY-MM-DD | Publicación activa desde esta fecha      | ayer      |
| `until`   | YYYY-MM-DD | Publicación activa hasta esta fecha      | sin límite|

### Ejemplos

```
# Eventos activos (ayer en adelante — uso estándar para pantallas)
GET /api/events.php

# Eventos de mayo 2026
GET /api/events.php?from=2026-05-01&until=2026-05-31

# Todo desde una fecha específica
GET /api/events.php?from=2026-04-01
```

### Respuesta exitosa (200)

```json
{
  "ok": true,
  "generated_at": "2026-04-16T14:00:00-05:00",
  "filter": {
    "from": "2026-04-15",
    "until": null
  },
  "count": 2,
  "events": [
    {
      "id": "a3f9b2c1d4e5",
      "title": "Seminario de Biología Marina",
      "image_url": "https://tudominio.com/events-cms/uploads/2026-04/a3f9b2_banner.jpg",
      "event_date": "2026-04-22",
      "publish_from": "2026-04-16",
      "publish_until": "2026-04-22",
      "days_until_event": 6,
      "is_active": true
    }
  ]
}
```

### Respuesta de error (400)

```json
{
  "ok": false,
  "error": "Formato de \"from\" inválido. Usa YYYY-MM-DD."
}
```

### Campos de respuesta

| Campo               | Tipo    | Descripción                                        |
|---------------------|---------|----------------------------------------------------|
| `id`                | string  | ID único del evento (12 chars hex)                 |
| `title`             | string  | Título del evento                                  |
| `image_url`         | string  | URL absoluta de la imagen — úsala en `<img src>`   |
| `event_date`        | string  | Fecha del evento (YYYY-MM-DD)                      |
| `publish_from`      | string  | Inicio del rango de publicación                    |
| `publish_until`     | string  | Fin del rango de publicación                       |
| `days_until_event`  | integer | Días hasta el evento (0 = hoy, negativo = pasado)  |
| `is_active`         | boolean | `true` si el evento está en rango de publicación hoy |

---

## Ejemplo de consumo en JavaScript (frontend)

```javascript
async function loadEvents() {
  const res  = await fetch('https://tudominio.com/events-cms/api/events.php');
  const data = await res.json();

  if (!data.ok) {
    console.error('API error:', data.error);
    return;
  }

  data.events.forEach(event => {
    console.log(event.title, event.image_url, event.days_until_event);
  });
}
```

---

## Seguridad

- El archivo `data/events.json` está protegido por `.htaccess` (no accesible por URL).
- Las imágenes se validan por MIME type real (no solo extensión).
- El admin usa sesiones PHP nativas con nombre de sesión personalizado.
- CORS está habilitado en el API (`Access-Control-Allow-Origin: *`) para que el frontend pueda consumirlo libremente.

---

## Limitaciones conocidas

- Un solo usuario admin (compartido). No hay roles ni auditoría.
- Sin paginación en el API (adecuado para volúmenes < 200 eventos).
- La imagen no se puede cambiar al editar — hay que borrar y crear un nuevo evento.
- Sin redimensionado automático de imágenes (sube imágenes ya optimizadas).

---

## Recomendaciones de imagen para pantallas

| Aspecto       | Recomendación              |
|---------------|----------------------------|
| Resolución    | 1920×1080 px (Full HD)     |
| Formato       | JPG (fotos) / PNG (diseños)|
| Tamaño        | < 2 MB por imagen          |
| Proporción    | 16:9 para pantallas HD     |
