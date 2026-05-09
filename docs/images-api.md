# Imágenes — API Reference

**Recurso:** `Image`  
**Tabla:** `images`  
**Fecha:** 2026-05-09

---

## Propósito

Catálogo global de imágenes del sistema. Permite asociar un ícono o logo (mediante URL) a divisiones, categorías y credenciales para mejorar la presentación visual en el frontend. Las imágenes se gestionan centralmente y cualquier rol puede consultarlas; solo `sysadmin` puede crearlas, editarlas o eliminarlas.

---

## Jerarquía de uso

```
Image
  ├── Division    (image_id nullable)
  ├── Category    (image_id nullable)
  └── Credential  (image_id nullable)
```

---

## Roles y permisos

| Acción | sysadmin | org_admin | org_user |
|--------|----------|-----------|----------|
| Listar imágenes | ✅ | ✅ | ✅ |
| Ver detalle | ✅ | ✅ | ✅ |
| Crear imagen | ✅ | ❌ | ❌ |
| Editar imagen | ✅ | ❌ | ❌ |
| Eliminar imagen | ✅ | ❌ | ❌ |

---

## Contrato común

- Todos los endpoints requieren autenticación con **Bearer token** vía `auth:sanctum`.
- Las listas usan paginación fija de **50 elementos por página**, ordenadas por `name` ascendente.
- Las respuestas unitarias (`show`, `store`, `update`) salen como objeto JSON plano sin envoltura `data`.
- Los errores siguen el formato estándar de Laravel.

### Códigos de estado

| Código | Cuándo |
|--------|--------|
| `200` | Lectura o actualización exitosa |
| `201` | Creación exitosa |
| `401` | Token ausente, inválido o expirado |
| `403` | El usuario no tiene permiso (no es `sysadmin`) |
| `404` | Imagen inexistente |
| `422` | Validación fallida |

---

## Recurso de salida: `ImageResource`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | integer | ID de la imagen |
| `name` | string | Nombre descriptivo, indexado para búsqueda |
| `url` | string | URL pública de la imagen |
| `created_at` | datetime | Fecha de creación |

---

## Endpoints

### `GET /api/images`

**Acceso:** todos los roles autenticados.

**Query params**

| Param | Tipo | Descripción |
|-------|------|-------------|
| `name` | string | Filtro parcial por nombre (`LIKE %name%`) |
| `page` | integer | Página de resultados |

**Ejemplo de request**

```
GET /api/images?name=cisco&page=1
Authorization: Bearer {token}
```

**Respuesta `200`**

```json
{
    "data": [
        {
            "id": 1,
            "name": "Cisco Router",
            "url": "https://cdn.example.com/images/cisco-router.png",
            "created_at": "2026-05-09T14:43:30.000000Z"
        },
        {
            "id": 2,
            "name": "Cisco Switch",
            "url": "https://cdn.example.com/images/cisco-switch.png",
            "created_at": "2026-05-09T14:50:00.000000Z"
        }
    ],
    "links": {
        "first": "http://api.example.com/api/images?page=1",
        "last": "http://api.example.com/api/images?page=1",
        "prev": null,
        "next": null
    },
    "meta": {
        "current_page": 1,
        "last_page": 1,
        "per_page": 50,
        "total": 2
    }
}
```

---

### `POST /api/images`

**Acceso:** solo `sysadmin`.

**Body**

| Campo | Tipo | Requerido | Reglas |
|-------|------|-----------|--------|
| `name` | string | ✅ | `max:255` |
| `url` | string (URL) | ✅ | URL válida, `max:500` |

**Ejemplo de request**

```json
{
    "name": "Cisco Router",
    "url": "https://cdn.example.com/images/cisco-router.png"
}
```

**Reglas de negocio**

- `name` se indexa en base de datos para búsquedas rápidas por nombre.
- `url` debe ser una URL válida; el sistema no sube ni valida que el archivo exista.
- No hay unicidad forzada en `name` — pueden existir varias imágenes con nombres similares.

**Respuesta `201`**

```json
{
    "id": 1,
    "name": "Cisco Router",
    "url": "https://cdn.example.com/images/cisco-router.png",
    "created_at": "2026-05-09T14:43:30.000000Z"
}
```

**Errores**

- `403` — el usuario no es `sysadmin`.
- `422` — `name` ausente, `url` ausente o con formato inválido.

---

### `GET /api/images/{image}`

**Acceso:** todos los roles autenticados.

**Path params**

| Param | Descripción |
|-------|-------------|
| `image` | ID de la imagen |

**Ejemplo de request**

```
GET /api/images/1
Authorization: Bearer {token}
```

**Respuesta `200`**

```json
{
    "id": 1,
    "name": "Cisco Router",
    "url": "https://cdn.example.com/images/cisco-router.png",
    "created_at": "2026-05-09T14:43:30.000000Z"
}
```

**Errores**

- `404` — imagen no encontrada.

---

### `PUT /api/images/{image}`

**Acceso:** solo `sysadmin`.

**Path params**

| Param | Descripción |
|-------|-------------|
| `image` | ID de la imagen |

**Body** (todos los campos son opcionales)

| Campo | Tipo | Reglas |
|-------|------|--------|
| `name` | string | `max:255` |
| `url` | string (URL) | URL válida, `max:500` |

**Ejemplo de request**

```json
{
    "name": "Cisco Router ISR",
    "url": "https://cdn.example.com/images/cisco-router-isr.png"
}
```

**Reglas de negocio**

- Solo se actualizan los campos enviados.
- Actualizar `url` o `name` no afecta los registros de divisiones, categorías o credenciales que ya referencian esta imagen — solo cambia el valor centralizado.

**Respuesta `200`**

```json
{
    "id": 1,
    "name": "Cisco Router ISR",
    "url": "https://cdn.example.com/images/cisco-router-isr.png",
    "created_at": "2026-05-09T14:43:30.000000Z"
}
```

**Errores**

- `403` — el usuario no es `sysadmin`.
- `404` — imagen no encontrada.
- `422` — `url` con formato inválido.

---

### `DELETE /api/images/{image}`

**Acceso:** solo `sysadmin`.

**Path params**

| Param | Descripción |
|-------|-------------|
| `image` | ID de la imagen |

**Reglas de negocio**

- La imagen se elimina físicamente (`hard delete` — no usa soft delete).
- Los registros que la referenciaban (divisiones, categorías, credenciales) pasan automáticamente a `image_id = null` gracias a la restricción `nullOnDelete` en la FK.

**Respuesta `200`**

```json
{
    "message": "Imagen eliminada."
}
```

**Errores**

- `403` — el usuario no es `sysadmin`.
- `404` — imagen no encontrada.

---

## Uso de imágenes en otros recursos

Al crear o editar una división, categoría o credencial se puede asociar una imagen enviando `image_id`.

### Ejemplo — Crear categoría con imagen

```json
POST /api/organizations/1/categories

{
    "name": "Redes",
    "description": "Equipos de red",
    "division_id": 2,
    "image_id": 1
}
```

### Respuesta con imagen cargada

Cuando la relación `image` está disponible, el recurso incluye el objeto `image`:

```json
{
    "id": 5,
    "name": "Redes",
    "description": "Equipos de red",
    "division_id": 2,
    "image": {
        "id": 1,
        "name": "Cisco Router",
        "url": "https://cdn.example.com/images/cisco-router.png"
    },
    "created_at": "2026-05-09T15:00:00.000000Z"
}
```

> `image` solo aparece cuando `image_id` no es `null`. Si no hay imagen, el campo no se incluye en la respuesta.

### Quitar imagen de un recurso

Enviar `"image_id": null` en el body del `PUT`:

```json
PUT /api/organizations/1/categories/5

{
    "image_id": null
}
```

---

## Archivos relacionados

| Archivo | Rol |
|---------|-----|
| `database/migrations/2026_05_09_144330_create_images_table.php` | Tabla `images` con índice en `name` |
| `database/migrations/2026_05_09_144335_add_image_id_to_divisions_table.php` | FK nullable en `divisions` |
| `database/migrations/2026_05_09_144335_add_image_id_to_categories_table.php` | FK nullable en `categories` |
| `database/migrations/2026_05_09_144336_add_image_id_to_credentials_table.php` | FK nullable en `credentials` |
| `app/Models/Image.php` | Modelo con relaciones a Division, Category, Credential |
| `app/Policies/ImagePolicy.php` | Solo sysadmin puede escribir; todos pueden leer |
| `app/Http/Requests/CreateImageRequest.php` | Validación de creación |
| `app/Http/Requests/UpdateImageRequest.php` | Validación de actualización |
| `app/Http/Resources/ImageResource.php` | Formato de salida |
| `app/Http/Controllers/ImageController.php` | CRUD completo |
