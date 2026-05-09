# Fase 3 — Jerarquía de Assets y Credenciales

**Estado:** Completada  
**Fecha:** 2026-05-09

---

## Jerarquía

```
Organization → Division → Category → Subcategory → Asset → Credential
```

---

## Roles y alcance

| Rol | Categorías / Subcategorías / Assets | Credenciales |
|-----|-------------------------------------|--------------|
| `sysadmin` | CRUD completo en cualquier organización | CRUD completo en cualquier organización |
| `org_admin` | CRUD completo dentro de su organización | CRUD completo dentro de su organización |
| `org_user` | Solo lectura dentro de su organización | Puede listar y ver credenciales de su organización, crear nuevas y editar solo las que creó. No puede eliminarlas |

Implementado con **Laravel Policies** (`CategoryPolicy`, `CredentialPolicy`).

---

## Contrato común de la API

- Todos los endpoints de esta fase requieren autenticación con **Bearer token** vía `auth:sanctum`.
- La jerarquía se valida por ruta. Si el recurso hijo no pertenece al padre indicado en la URL, la API responde `404`.
- Todas las listas usan paginación fija de **20 elementos por página**, ordenadas por creación descendente (`latest()`).
- Las respuestas de lista usan el formato paginado de Laravel:

```json
{
    "data": [],
    "links": {
        "first": "...",
        "last": "...",
        "prev": null,
        "next": "..."
    },
    "meta": {
        "current_page": 1,
        "last_page": 1,
        "per_page": 20,
        "total": 0
    }
}
```

- Las respuestas unitarias (`show`, `store`, `update`) salen como **objeto JSON plano**, sin envoltura `data`.
- Los recursos de esta fase usan **soft delete**. Un registro eliminado deja de aparecer en listados y el route-model binding lo trata como `404`.
- Los errores siguen el formato estándar de Laravel:

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "field": [
            "Mensaje de validación"
        ]
    }
}
```

### Códigos de estado habituales

| Código | Cuándo aparece |
|--------|----------------|
| `200` | Lectura exitosa, actualización exitosa o soft delete exitoso |
| `201` | Creación exitosa |
| `401` | Token ausente, inválido o expirado |
| `403` | El usuario autenticado no tiene permiso sobre ese recurso |
| `404` | Recurso inexistente, eliminado o fuera del scope del padre en la ruta |
| `422` | Validación fallida |

### Auditoría

- Categorías, subcategorías y assets registran eventos `create`, `update` y `delete`.
- Credenciales registran `create`, `update`, `delete` y también `view` en el endpoint de detalle.

---

## Mapa rápido de endpoints

### Categorías

| Método | Ruta | Acceso mínimo | Resumen |
|--------|------|---------------|---------|
| GET | `/api/organizations/{organization}/categories` | Cualquier miembro de la organización | Lista paginada, filtrable por `division_id` |
| POST | `/api/organizations/{organization}/categories` | `org_admin` | Crea categoría dentro de la organización de la ruta |
| GET | `/api/organizations/{organization}/categories/{category}` | Cualquier miembro de la organización | Detalle |
| PUT | `/api/organizations/{organization}/categories/{category}` | `org_admin` | Actualización parcial |
| DELETE | `/api/organizations/{organization}/categories/{category}` | `org_admin` | Soft delete |

### Subcategorías

| Método | Ruta | Acceso mínimo | Resumen |
|--------|------|---------------|---------|
| GET | `/api/categories/{category}/subcategories` | Cualquier miembro de la organización dueña de la categoría | Lista paginada |
| POST | `/api/categories/{category}/subcategories` | `org_admin` | Crea subcategoría dentro de la categoría |
| GET | `/api/categories/{category}/subcategories/{subcategory}` | Cualquier miembro de la organización dueña de la categoría | Detalle |
| PUT | `/api/categories/{category}/subcategories/{subcategory}` | `org_admin` | Actualización parcial |
| DELETE | `/api/categories/{category}/subcategories/{subcategory}` | `org_admin` | Soft delete |

### Assets

| Método | Ruta | Acceso mínimo | Resumen |
|--------|------|---------------|---------|
| GET | `/api/subcategories/{subcategory}/assets` | Cualquier miembro de la organización dueña de la subcategoría | Lista paginada |
| POST | `/api/subcategories/{subcategory}/assets` | `org_admin` | Crea asset con `metadata` JSON opcional |
| GET | `/api/subcategories/{subcategory}/assets/{asset}` | Cualquier miembro de la organización dueña de la subcategoría | Detalle |
| PUT | `/api/subcategories/{subcategory}/assets/{asset}` | `org_admin` | Actualización parcial |
| DELETE | `/api/subcategories/{subcategory}/assets/{asset}` | `org_admin` | Soft delete |

### Credenciales

| Método | Ruta | Acceso mínimo | Resumen |
|--------|------|---------------|---------|
| GET | `/api/assets/{asset}/credentials` | Cualquier miembro de la organización dueña del asset | Lista paginada sin secretos descifrados |
| POST | `/api/assets/{asset}/credentials` | Cualquier miembro de la organización dueña del asset | Crea y cifra credencial |
| GET | `/api/assets/{asset}/credentials/{credential}` | Cualquier miembro de la organización dueña del asset | Detalle con password descifrado y audit log |
| PUT | `/api/assets/{asset}/credentials/{credential}` | `org_admin` o creador | Actualiza y guarda snapshot previo |
| DELETE | `/api/assets/{asset}/credentials/{credential}` | `org_admin` | Soft delete |

---

## Categorías

### Payload de escritura

| Campo | Tipo | Requerido en `POST` | Reglas |
|-------|------|---------------------|--------|
| `name` | string | Sí | `required`, `max:255` |
| `description` | string \| null | No | `nullable`, `max:1000` |
| `division_id` | integer \| null | No | `nullable`, `exists:divisions,id` |

**Notas de negocio**

- `organization_id` no se recibe en el body: lo impone la organización de la ruta.
- `division_id` es opcional y solo valida existencia del ID.
- En `PUT` todos los campos son parciales (`sometimes`).

### Recurso de salida: `CategoryResource`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | integer | ID de la categoría |
| `name` | string | Nombre |
| `description` | string \| null | Descripción libre |
| `division_id` | integer \| null | División asociada |
| `created_at` | datetime | Fecha de creación |

### `GET /api/organizations/{organization}/categories`

**Acceso por rol**

- `sysadmin`: cualquier organización.
- `org_admin`: solo su propia organización.
- `org_user`: solo su propia organización.

**Entrada esperada**

- Path params:
  - `organization`: ID de la organización.
- Query params:
  - `division_id`: opcional, entero existente para filtrar categorías por división.
  - `page`: opcional, paginación estándar de Laravel.

**Reglas de negocio**

- Verifica permiso `viewAny` sobre la organización de la ruta.
- Devuelve solo categorías activas para esa organización.
- Si se envía `division_id`, aplica filtro exacto por ese valor.
- Ordena por más recientes primero y pagina de 20 en 20.

**Salida `200`**

- Colección paginada de `CategoryResource` dentro de `data`.
- Incluye `links` y `meta` de paginación.

### `POST /api/organizations/{organization}/categories`

**Acceso por rol**

- `sysadmin`: puede crear en cualquier organización.
- `org_admin`: puede crear solo en su organización.
- `org_user`: sin acceso.

**Entrada esperada**

- Path params:
  - `organization`: ID de la organización destino.
- Body JSON: `name`, `description`, `division_id` según la tabla de escritura.

```json
{
    "name": "Network",
    "description": "Activos de red",
    "division_id": 3
}
```

**Reglas de negocio**

- La categoría queda ligada a la organización de la URL, no a un dato enviado por cliente.
- Si `division_id` no se envía, la categoría queda sin división asociada.
- Registra evento de auditoría `create` con `organization_id`.

**Salida `201`**

- Objeto `CategoryResource`.

### `GET /api/organizations/{organization}/categories/{category}`

**Acceso por rol**

- `sysadmin`: cualquier organización.
- `org_admin`: solo su propia organización.
- `org_user`: solo su propia organización.

**Entrada esperada**

- Path params:
  - `organization`: ID de la organización contexto.
  - `category`: ID de la categoría.

**Reglas de negocio**

- Requiere permiso `view` sobre la categoría.
- Si la categoría no pertenece a la organización indicada en la ruta, responde `404`.

**Salida `200`**

- Objeto `CategoryResource`.

### `PUT /api/organizations/{organization}/categories/{category}`

**Acceso por rol**

- `sysadmin`: cualquier organización.
- `org_admin`: solo su propia organización.
- `org_user`: sin acceso.

**Entrada esperada**

- Path params:
  - `organization`: ID de la organización contexto.
  - `category`: ID de la categoría.
- Body JSON parcial con cualquiera de los campos de escritura.

```json
{
    "description": "Activos de red y conectividad",
    "division_id": null
}
```

**Reglas de negocio**

- La autorización ocurre en el `FormRequest` usando la policy de categoría.
- Solo actualiza los campos enviados.
- Si la categoría no pertenece a la organización de la ruta, responde `404`.
- Registra evento de auditoría `update`.

**Salida `200`**

- Objeto `CategoryResource` actualizado.

### `DELETE /api/organizations/{organization}/categories/{category}`

**Acceso por rol**

- `sysadmin`: cualquier organización.
- `org_admin`: solo su propia organización.
- `org_user`: sin acceso.

**Entrada esperada**

- Path params:
  - `organization`: ID de la organización contexto.
  - `category`: ID de la categoría.

**Reglas de negocio**

- Ejecuta soft delete.
- Si la categoría no pertenece a la organización de la ruta, responde `404`.
- Registra evento de auditoría `delete`.

**Salida `200`**

```json
{
    "message": "Categoría eliminada."
}
```

---

## Subcategorías

### Payload de escritura

| Campo | Tipo | Requerido en `POST` | Reglas |
|-------|------|---------------------|--------|
| `name` | string | Sí | `required`, `max:255` |
| `description` | string \| null | No | `nullable`, `max:1000` |

**Notas de negocio**

- `category_id` no se recibe en el body: lo define la categoría de la ruta.
- En `PUT` todos los campos son parciales (`sometimes`).

### Recurso de salida: `SubcategoryResource`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | integer | ID de la subcategoría |
| `name` | string | Nombre |
| `description` | string \| null | Descripción libre |
| `category_id` | integer | Categoría padre |
| `created_at` | datetime | Fecha de creación |

### `GET /api/categories/{category}/subcategories`

**Acceso por rol**

- `sysadmin`: cualquier organización.
- `org_admin`: solo si pertenece a la organización de la categoría.
- `org_user`: solo si pertenece a la organización de la categoría.

**Entrada esperada**

- Path params:
  - `category`: ID de la categoría.
- Query params:
  - `page`: opcional.

**Reglas de negocio**

- La policy se evalúa contra la categoría padre.
- Devuelve solo subcategorías activas de esa categoría.
- Ordena por más recientes primero y pagina de 20 en 20.

**Salida `200`**

- Colección paginada de `SubcategoryResource`.

### `POST /api/categories/{category}/subcategories`

**Acceso por rol**

- `sysadmin`: puede crear en cualquier categoría.
- `org_admin`: puede crear solo en categorías de su organización.
- `org_user`: sin acceso.

**Entrada esperada**

- Path params:
  - `category`: ID de la categoría padre.
- Body JSON: `name` y `description`.

```json
{
    "name": "Routers",
    "description": "Equipos de borde y core"
}
```

**Reglas de negocio**

- La subcategoría queda ligada a la categoría de la ruta.
- La autorización usa permiso `update` sobre la categoría.
- Registra evento de auditoría `create` con `category_id`.

**Salida `201`**

- Objeto `SubcategoryResource`.

### `GET /api/categories/{category}/subcategories/{subcategory}`

**Acceso por rol**

- `sysadmin`: cualquier organización.
- `org_admin`: solo si pertenece a la organización de la categoría.
- `org_user`: solo si pertenece a la organización de la categoría.

**Entrada esperada**

- Path params:
  - `category`: ID de la categoría padre.
  - `subcategory`: ID de la subcategoría.

**Reglas de negocio**

- La autorización se resuelve usando la categoría padre.
- Si la subcategoría no pertenece a la categoría de la ruta, responde `404`.

**Salida `200`**

- Objeto `SubcategoryResource`.

### `PUT /api/categories/{category}/subcategories/{subcategory}`

**Acceso por rol**

- `sysadmin`: cualquier organización.
- `org_admin`: solo si pertenece a la organización de la categoría.
- `org_user`: sin acceso.

**Entrada esperada**

- Path params:
  - `category`: ID de la categoría padre.
  - `subcategory`: ID de la subcategoría.
- Body JSON parcial con `name` y/o `description`.

```json
{
    "name": "Routers y firewalls"
}
```

**Reglas de negocio**

- La autorización ocurre en el `FormRequest` usando la categoría asociada a la subcategoría.
- Si la subcategoría no pertenece a la categoría de la ruta, responde `404`.
- Registra evento de auditoría `update`.

**Salida `200`**

- Objeto `SubcategoryResource` actualizado.

### `DELETE /api/categories/{category}/subcategories/{subcategory}`

**Acceso por rol**

- `sysadmin`: cualquier organización.
- `org_admin`: solo si pertenece a la organización de la categoría.
- `org_user`: sin acceso.

**Entrada esperada**

- Path params:
  - `category`: ID de la categoría padre.
  - `subcategory`: ID de la subcategoría.

**Reglas de negocio**

- Ejecuta soft delete.
- Si la subcategoría no pertenece a la categoría de la ruta, responde `404`.
- Registra evento de auditoría `delete`.

**Salida `200`**

```json
{
    "message": "Subcategoría eliminada."
}
```

---

## Assets

### Payload de escritura

| Campo | Tipo | Requerido en `POST` | Reglas |
|-------|------|---------------------|--------|
| `name` | string | Sí | `required`, `max:255` |
| `description` | string \| null | No | `nullable`, `max:1000` |
| `metadata` | object \| array \| null | No | `nullable`, `array` |

**Notas de negocio**

- `subcategory_id` y `organization_id` no se reciben en el body.
- `organization_id` se toma automáticamente desde la organización de la categoría/subcategoría padre.
- `metadata` acepta una estructura JSON libre mientras llegue como array/objeto válido.
- En `PUT` todos los campos son parciales (`sometimes`).

### Recurso de salida: `AssetResource`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | integer | ID del asset |
| `name` | string | Nombre |
| `description` | string \| null | Descripción libre |
| `metadata` | object \| array \| null | Metadata estructurada |
| `subcategory_id` | integer | Subcategoría padre |
| `created_at` | datetime | Fecha de creación |

### `GET /api/subcategories/{subcategory}/assets`

**Acceso por rol**

- `sysadmin`: cualquier organización.
- `org_admin`: solo si pertenece a la organización de la categoría asociada.
- `org_user`: solo si pertenece a la organización de la categoría asociada.

**Entrada esperada**

- Path params:
  - `subcategory`: ID de la subcategoría.
- Query params:
  - `page`: opcional.

**Reglas de negocio**

- La autorización se evalúa contra la categoría de la subcategoría.
- Devuelve solo assets activos de esa subcategoría.
- Ordena por más recientes primero y pagina de 20 en 20.

**Salida `200`**

- Colección paginada de `AssetResource`.

### `POST /api/subcategories/{subcategory}/assets`

**Acceso por rol**

- `sysadmin`: puede crear en cualquier subcategoría.
- `org_admin`: puede crear solo dentro de subcategorías de su organización.
- `org_user`: sin acceso.

**Entrada esperada**

- Path params:
  - `subcategory`: ID de la subcategoría padre.
- Body JSON: `name`, `description`, `metadata`.

```json
{
    "name": "Router Principal",
    "description": "Router core de la sede Bogotá",
    "metadata": {
        "ip": "192.168.1.1",
        "marca": "Cisco",
        "modelo": "ISR 4321",
        "ubicacion": "Rack A - Piso 3"
    }
}
```

**Reglas de negocio**

- El asset queda ligado a la subcategoría de la ruta.
- La organización se resuelve automáticamente desde el árbol padre y se persiste en `organization_id`.
- Registra evento de auditoría `create` con `subcategory_id`.

**Salida `201`**

- Objeto `AssetResource`.

### `GET /api/subcategories/{subcategory}/assets/{asset}`

**Acceso por rol**

- `sysadmin`: cualquier organización.
- `org_admin`: solo si pertenece a la organización de la categoría asociada.
- `org_user`: solo si pertenece a la organización de la categoría asociada.

**Entrada esperada**

- Path params:
  - `subcategory`: ID de la subcategoría padre.
  - `asset`: ID del asset.

**Reglas de negocio**

- La autorización se evalúa contra la categoría asociada a la subcategoría.
- Si el asset no pertenece a la subcategoría de la ruta, responde `404`.

**Salida `200`**

- Objeto `AssetResource`.

### `PUT /api/subcategories/{subcategory}/assets/{asset}`

**Acceso por rol**

- `sysadmin`: cualquier organización.
- `org_admin`: solo si pertenece a la organización de la categoría asociada.
- `org_user`: sin acceso.

**Entrada esperada**

- Path params:
  - `subcategory`: ID de la subcategoría padre.
  - `asset`: ID del asset.
- Body JSON parcial con `name`, `description` y/o `metadata`.

```json
{
    "metadata": {
        "ip": "192.168.1.10",
        "estado": "activo"
    }
}
```

**Reglas de negocio**

- La autorización ocurre en el `FormRequest` usando la categoría del asset.
- Si el asset no pertenece a la subcategoría de la ruta, responde `404`.
- Registra evento de auditoría `update`.

**Salida `200`**

- Objeto `AssetResource` actualizado.

### `DELETE /api/subcategories/{subcategory}/assets/{asset}`

**Acceso por rol**

- `sysadmin`: cualquier organización.
- `org_admin`: solo si pertenece a la organización de la categoría asociada.
- `org_user`: sin acceso.

**Entrada esperada**

- Path params:
  - `subcategory`: ID de la subcategoría padre.
  - `asset`: ID del asset.

**Reglas de negocio**

- Ejecuta soft delete.
- Si el asset no pertenece a la subcategoría de la ruta, responde `404`.
- Registra evento de auditoría `delete`.

**Salida `200`**

```json
{
    "message": "Asset eliminado."
}
```

---

## Credenciales

### Seguridad de credenciales

- `password` siempre se recibe en plano y se cifra con **AES-256-GCM** usando `EncryptionService`.
- Cada cifrado genera un IV único (`iv`).
- `notes` también se cifra cuando se envía; si no se envía o se limpia, no se persiste texto plano.
- Los campos internos `encrypted_password`, `iv`, `notes_encrypted` e `iv_notes` nunca salen en la API.
- `GET /api/assets/{asset}/credentials/{credential}` es el único endpoint que expone `password` descifrado.
- Cada `PUT` crea un snapshot previo en `credential_versions` antes de aplicar cambios.
- Cada `GET` individual registra un evento `view` en `audit_logs`.

### Payload de creación

| Campo | Tipo | Requerido en `POST` | Reglas |
|-------|------|---------------------|--------|
| `name` | string | Sí | `required`, `max:255` |
| `username` | string \| null | No | `nullable`, `max:255` |
| `password` | string | Sí | `required`, `max:5000` |
| `notes` | string \| null | No | `nullable`, `max:5000` |
| `type` | string | No | `password`, `api_key`, `ssh`, `certificate`, `other` |

**Notas de negocio**

- Si `type` no se envía, la API usa `password` por defecto.
- `asset_id`, `organization_id` y `created_by` se resuelven del asset de la ruta y del usuario autenticado.

### Payload de actualización

| Campo | Tipo | Requerido en `PUT` | Reglas |
|-------|------|--------------------|--------|
| `name` | string | No | `sometimes`, `max:255` |
| `username` | string \| null | No | `sometimes`, `nullable`, `max:255` |
| `password` | string | No | `sometimes`, `max:5000` |
| `notes` | string \| null | No | `sometimes`, `nullable`, `max:5000` |
| `type` | string | No | `sometimes`, uno de los tipos válidos |

**Notas de negocio**

- Todo `PUT` es parcial: un campo ausente conserva su valor actual.
- Si `password` viene presente, la credencial se vuelve a cifrar con un nuevo IV.
- Si `notes` viene en `null` o vacío, la implementación limpia las notas cifradas.
- Aunque `username` valida `nullable`, la implementación actual solo lo cambia cuando llega un valor no nulo; enviar `null` no lo borra.

### Recurso de salida: `CredentialResource`

| Campo | Tipo | Sale en | Descripción |
|-------|------|---------|-------------|
| `id` | integer | Todos | ID de la credencial |
| `name` | string | Todos | Nombre |
| `username` | string \| null | Todos | Usuario asociado |
| `type` | string | Todos | Tipo de credencial |
| `asset_id` | integer | Todos | Asset padre |
| `password` | string | Solo `GET` detalle | Password descifrado |
| `notes` | string \| null | Solo `GET` detalle y solo si existen notas | Notas descifradas |
| `created_at` | datetime | Todos | Fecha de creación |

### `GET /api/assets/{asset}/credentials`

**Acceso por rol**

- `sysadmin`: cualquier organización.
- `org_admin`: solo si pertenece a la organización del asset.
- `org_user`: solo si pertenece a la organización del asset.

**Entrada esperada**

- Path params:
  - `asset`: ID del asset.
- Query params:
  - `page`: opcional.

**Reglas de negocio**

- La policy se evalúa con `viewAny` sobre el asset.
- Devuelve solo credenciales activas del asset.
- Ordena por más recientes primero y pagina de 20 en 20.
- Nunca devuelve `password` ni `notes` descifradas en este listado.

**Salida `200`**

- Colección paginada de `CredentialResource` sin campos `password` ni `notes`.

### `POST /api/assets/{asset}/credentials`

**Acceso por rol**

- `sysadmin`: puede crear en cualquier asset.
- `org_admin`: puede crear en assets de su organización.
- `org_user`: puede crear en assets de su organización.

**Entrada esperada**

- Path params:
  - `asset`: ID del asset destino.
- Body JSON según payload de creación.

```json
{
    "name": "Router Admin",
    "username": "admin",
    "password": "s3cret!",
    "notes": "Cambiar cada 90 días",
    "type": "password"
}
```

**Reglas de negocio**

- El asset y la organización se toman de la ruta; el creador se toma del token autenticado.
- El password se cifra antes de persistir.
- Si se envían notas, también se cifran.
- Registra evento de auditoría `create` con `asset_id`.

**Salida `201`**

- Objeto `CredentialResource` sin `password` ni `notes` descifradas.
- La API confirma la creación, pero no hace eco del secreto en claro.

### `GET /api/assets/{asset}/credentials/{credential}`

**Acceso por rol**

- `sysadmin`: cualquier organización.
- `org_admin`: solo si pertenece a la organización de la credencial.
- `org_user`: solo si pertenece a la organización de la credencial.

**Entrada esperada**

- Path params:
  - `asset`: ID del asset padre.
  - `credential`: ID de la credencial.

**Reglas de negocio**

- Requiere permiso `view` sobre la credencial.
- Si la credencial no pertenece al asset de la ruta, responde `404`.
- Descifra `password` y `notes` antes de serializar la respuesta.
- Registra evento de auditoría `view`.

**Salida `200`**

- Objeto `CredentialResource` con `password` descifrado.
- `notes` solo aparece si existen notas almacenadas.

### `PUT /api/assets/{asset}/credentials/{credential}`

**Acceso por rol**

- `sysadmin`: puede actualizar cualquier credencial.
- `org_admin`: puede actualizar credenciales de su organización.
- `org_user`: solo puede actualizar credenciales creadas por él mismo.

**Entrada esperada**

- Path params:
  - `asset`: ID del asset padre.
  - `credential`: ID de la credencial.
- Body JSON parcial según payload de actualización.

```json
{
    "password": "nueva-clave-segura",
    "notes": null,
    "type": "password"
}
```

**Reglas de negocio**

- La autorización ocurre en el `FormRequest` usando la `CredentialPolicy`.
- Si la credencial no pertenece al asset de la ruta, responde `404`.
- Antes de modificar, guarda una versión previa en `credential_versions`.
- Si llega un nuevo `password`, se cifra y reemplaza el secreto actual.
- Si `notes` no llega, se conserva el valor actual; si llega vacío o `null`, se elimina.
- Registra evento de auditoría `update`.

**Salida `200`**

- Objeto `CredentialResource` actualizado.
- Igual que en `POST`, la respuesta no incluye `password` ni `notes` descifradas porque esos campos solo se cargan en el endpoint `show`.

### `DELETE /api/assets/{asset}/credentials/{credential}`

**Acceso por rol**

- `sysadmin`: puede eliminar cualquier credencial.
- `org_admin`: puede eliminar credenciales de su organización.
- `org_user`: sin acceso.

**Entrada esperada**

- Path params:
  - `asset`: ID del asset padre.
  - `credential`: ID de la credencial.

**Reglas de negocio**

- Ejecuta soft delete.
- Si la credencial no pertenece al asset de la ruta, responde `404`.
- Registra evento de auditoría `delete`.

**Salida `200`**

```json
{
    "message": "Credencial eliminada."
}
```

---

## Archivos creados

```
app/
  Policies/
    CategoryPolicy.php
    CredentialPolicy.php
  Http/
    Requests/Asset/
      CreateCategoryRequest.php
      UpdateCategoryRequest.php
      CreateSubcategoryRequest.php
      UpdateSubcategoryRequest.php
      CreateAssetRequest.php
      UpdateAssetRequest.php
      CreateCredentialRequest.php
      UpdateCredentialRequest.php
    Resources/
      CategoryResource.php
      SubcategoryResource.php
      AssetResource.php
      CredentialResource.php
    Controllers/
      CategoryController.php
      SubcategoryController.php
      AssetController.php
      CredentialController.php
  Services/
    AssetService.php
    CredentialService.php
  Models/
    Credential.php   (añadidos $password_plain y $notes_plain)
  Providers/
    AppServiceProvider.php   (CategoryPolicy y CredentialPolicy registradas)
routes/api.php               (20 rutas nuevas)
```

---

## Siguiente fase

**Fase 4 — Tokens de acceso compartido**
- Generar tokens efímeros (`shared_access_tokens`) para compartir una credencial sin revelar el vault
- Endpoint público para consumir token (sin autenticación)
- Control de expiración y límite de usos
