# Fase 2 — Gestión de Organizaciones

**Estado:** Completada  
**Fecha:** 2026-05-08

---

## Roles y permisos

| Rol | Crear org | Ver org | Editar org | Eliminar org | Gestionar usuarios | Gestionar dominios |
|-----|-----------|---------|------------|--------------|--------------------|--------------------|
| `sysadmin` | ✅ | ✅ Todas | ✅ | ✅ | ✅ | ✅ |
| `org_admin` | ❌ | ✅ La suya | ✅ La suya | ❌ | ✅ La suya | ✅ La suya |
| `org_user` | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |

Implementado con **Laravel Policies** nativas — sin paquetes externos.

---

## Endpoints

### Organizaciones

| Método | Ruta | Rol mínimo | Descripción |
|--------|------|-----------|-------------|
| GET | `/api/organizations` | sysadmin | Lista paginada con `users_count` y dominios |
| POST | `/api/organizations` | sysadmin | Crea organización (slug auto si no se envía) |
| GET | `/api/organizations/{org}` | sysadmin / org_admin | Detalle de organización |
| PUT | `/api/organizations/{org}` | sysadmin / org_admin | Actualiza nombre, slug, logo, is_active |
| DELETE | `/api/organizations/{org}` | sysadmin | Soft delete |

### Dominios

| Método | Ruta | Rol mínimo | Descripción |
|--------|------|-----------|-------------|
| GET | `/api/organizations/{org}/domains` | sysadmin / org_admin | Lista dominios |
| POST | `/api/organizations/{org}/domains` | sysadmin / org_admin | Añade dominio |
| DELETE | `/api/organizations/{org}/domains/{domain}` | sysadmin / org_admin | Elimina dominio |

> Los dominios vinculan automáticamente nuevos usuarios al login si su email coincide.

### Usuarios de organización

| Método | Ruta | Rol mínimo | Descripción |
|--------|------|-----------|-------------|
| GET | `/api/organizations/{org}/users` | sysadmin / org_admin | Lista paginada de usuarios |
| POST | `/api/organizations/{org}/users` | sysadmin / org_admin | Invita nuevo usuario enterprise |
| PUT | `/api/organizations/{org}/users/{user}` | sysadmin / org_admin | Cambia role o is_active |
| DELETE | `/api/organizations/{org}/users/{user}` | sysadmin / org_admin | Desactiva usuario (soft) |

---

## Detalle de endpoints

Todos los endpoints requieren autenticación via **Bearer token** (Sanctum).  
Las respuestas de error siguen el formato estándar de Laravel:

```json
{ "message": "Descripción del error.", "errors": { "campo": ["regla rota"] } }
```

---

### `GET /api/organizations`

**Rol requerido:** `sysadmin`

Lista paginada de todas las organizaciones con conteo de usuarios y dominios.

**Query params:** ninguno (paginación fija: 20 por página)

**Respuesta 200**
```json
{
    "data": [
        {
            "id": 1,
            "name": "ACME Corp",
            "slug": "acme-corp",
            "logo": "https://cdn.acme.com/logo.png",
            "is_active": true,
            "users_count": 12,
            "domains": [
                { "id": 1, "domain": "acme.com", "is_verified": false, "created_at": "2026-05-08T10:00:00.000000Z" }
            ],
            "created_at": "2026-05-08T10:00:00.000000Z"
        }
    ],
    "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
    "meta": { "current_page": 1, "last_page": 3, "per_page": 20, "total": 50 }
}
```

---

### `POST /api/organizations`

**Rol requerido:** `sysadmin`

Crea una nueva organización. El `slug` se genera automáticamente a partir del `name` si no se envía.

**Body**
| Campo | Tipo | Requerido | Reglas |
|-------|------|-----------|--------|
| `name` | string | ✅ | max:255 |
| `slug` | string | ❌ | max:100, único, solo `[a-z0-9-]` |
| `logo` | string (URL) | ❌ | URL válida, max:500 |

```json
{
    "name": "ACME Corp",
    "logo": "https://cdn.acme.com/logo.png"
}
```

**Respuesta 201**
```json
{
    "data": {
        "id": 1,
        "name": "ACME Corp",
        "slug": "acme-corp",
        "logo": "https://cdn.acme.com/logo.png",
        "is_active": true,
        "users_count": null,
        "domains": [],
        "created_at": "2026-05-08T10:00:00.000000Z"
    }
}
```

---

### `GET /api/organizations/{organization}`

**Rol requerido:** `sysadmin` (cualquier org) o `org_admin` (solo la suya)

Devuelve el detalle de una organización con sus dominios y conteo de usuarios.

**Respuesta 200**
```json
{
    "data": {
        "id": 1,
        "name": "ACME Corp",
        "slug": "acme-corp",
        "logo": "https://cdn.acme.com/logo.png",
        "is_active": true,
        "users_count": 12,
        "domains": [
            { "id": 1, "domain": "acme.com", "is_verified": false, "created_at": "2026-05-08T10:00:00.000000Z" }
        ],
        "created_at": "2026-05-08T10:00:00.000000Z"
    }
}
```

**Errores**
- `403` — el usuario no tiene permiso sobre esta organización
- `404` — organización no encontrada

---

### `PUT /api/organizations/{organization}`

**Rol requerido:** `sysadmin` o `org_admin` (solo la suya)

Actualiza uno o más campos de la organización. Todos los campos son opcionales (`sometimes`).

**Body**
| Campo | Tipo | Requerido | Reglas |
|-------|------|-----------|--------|
| `name` | string | ❌ | max:255 |
| `slug` | string | ❌ | max:100, único (ignora el propio), solo `[a-z0-9-]` |
| `logo` | string (URL) | ❌ | URL válida, max:500, acepta `null` |
| `is_active` | boolean | ❌ | `true` / `false` |

```json
{
    "name": "ACME Corp International",
    "is_active": false
}
```

**Respuesta 200**
```json
{
    "data": {
        "id": 1,
        "name": "ACME Corp International",
        "slug": "acme-corp",
        "logo": "https://cdn.acme.com/logo.png",
        "is_active": false,
        "users_count": null,
        "domains": [ ... ],
        "created_at": "2026-05-08T10:00:00.000000Z"
    }
}
```

---

### `DELETE /api/organizations/{organization}`

**Rol requerido:** `sysadmin`

Soft delete de la organización (no elimina físicamente los registros).

**Respuesta 200**
```json
{ "message": "Organización eliminada." }
```

**Errores**
- `403` — solo sysadmin puede eliminar organizaciones

---

### `GET /api/organizations/{organization}/domains`

**Rol requerido:** `sysadmin` o `org_admin`

Lista todos los dominios asociados a la organización (sin paginación).

**Respuesta 200**
```json
{
    "data": [
        { "id": 1, "domain": "acme.com", "is_verified": false, "created_at": "2026-05-08T10:00:00.000000Z" },
        { "id": 2, "domain": "acme.io",  "is_verified": false, "created_at": "2026-05-08T11:00:00.000000Z" }
    ]
}
```

---

### `POST /api/organizations/{organization}/domains`

**Rol requerido:** `sysadmin` o `org_admin`

Añade un dominio a la organización. Los nuevos usuarios que se registren con un email de ese dominio quedarán vinculados automáticamente.

**Body**
| Campo | Tipo | Requerido | Reglas |
|-------|------|-----------|--------|
| `domain` | string | ✅ | max:255, único globalmente, formato válido de dominio (`[a-z0-9.-]+\.[a-z]{2,}`) |

```json
{ "domain": "acme.com" }
```

**Respuesta 201**
```json
{
    "data": {
        "id": 1,
        "domain": "acme.com",
        "is_verified": false,
        "created_at": "2026-05-08T10:00:00.000000Z"
    }
}
```

**Errores**
- `422` — dominio ya registrado en otra organización o formato inválido

---

### `DELETE /api/organizations/{organization}/domains/{domain}`

**Rol requerido:** `sysadmin` o `org_admin`

Elimina un dominio de la organización. No afecta a usuarios ya vinculados.

**Respuesta 200**
```json
{ "message": "Dominio eliminado." }
```

**Errores**
- `404` — el dominio no pertenece a esta organización

---

### `GET /api/organizations/{organization}/users`

**Rol requerido:** `sysadmin` o `org_admin`

Lista paginada de usuarios de la organización (20 por página), ordenados por más recientes.

**Respuesta 200**
```json
{
    "data": [
        {
            "id": 5,
            "name": "Juan Pérez",
            "email": "juan@acme.com",
            "role": "org_admin",
            "account_type": "enterprise",
            "two_factor_enabled": false,
            "is_active": true,
            "last_login_at": null,
            "created_at": "2026-05-08T10:00:00.000000Z"
        }
    ],
    "links": { ... },
    "meta": { "current_page": 1, "last_page": 1, "per_page": 20, "total": 1 }
}
```

---

### `POST /api/organizations/{organization}/users`

**Rol requerido:** `sysadmin` o `org_admin`

Invita un nuevo usuario a la organización. Se crea con `account_type=enterprise` y contraseña aleatoria. El usuario deberá configurar su contraseña y 2FA en el primer login.

**Body**
| Campo | Tipo | Requerido | Reglas |
|-------|------|-----------|--------|
| `name` | string | ✅ | max:255 |
| `email` | string | ✅ | email válido, único en `users` |
| `role` | string | ✅ | `org_admin` o `org_user` |

```json
{
    "name": "Juan Pérez",
    "email": "juan@acme.com",
    "role": "org_admin"
}
```

**Respuesta 201**
```json
{
    "data": {
        "id": 5,
        "name": "Juan Pérez",
        "email": "juan@acme.com",
        "role": "org_admin",
        "account_type": "enterprise",
        "two_factor_enabled": false,
        "is_active": true,
        "last_login_at": null,
        "created_at": "2026-05-08T10:00:00.000000Z"
    }
}
```

**Errores**
- `422` — email ya en uso, rol inválido

---

### `PUT /api/organizations/{organization}/users/{user}`

**Rol requerido:** `sysadmin` o `org_admin`

Actualiza el rol o estado activo de un usuario. Todos los campos son opcionales (`sometimes`). El usuario debe pertenecer a la organización indicada.

**Body**
| Campo | Tipo | Requerido | Reglas |
|-------|------|-----------|--------|
| `role` | string | ❌ | `org_admin` o `org_user` |
| `is_active` | boolean | ❌ | `true` / `false` |

```json
{
    "role": "org_user",
    "is_active": true
}
```

**Respuesta 200**
```json
{
    "data": {
        "id": 5,
        "name": "Juan Pérez",
        "email": "juan@acme.com",
        "role": "org_user",
        "account_type": "enterprise",
        "two_factor_enabled": false,
        "is_active": true,
        "last_login_at": null,
        "created_at": "2026-05-08T10:00:00.000000Z"
    }
}
```

**Errores**
- `404` — el usuario no pertenece a esta organización

---

### `DELETE /api/organizations/{organization}/users/{user}`

**Rol requerido:** `sysadmin` o `org_admin`

Desactiva al usuario (soft): pone `is_active = false`. No elimina el registro. El usuario debe pertenecer a la organización indicada.

**Respuesta 200**
```json
{ "message": "Usuario desactivado." }
```

**Errores**
- `403` — sin permiso para modificar este usuario
- `404` — el usuario no pertenece a esta organización

---

## Archivos creados

```
app/
  Policies/
    OrganizationPolicy.php
    UserPolicy.php
  Http/
    Requests/Organization/
      CreateOrganizationRequest.php
      UpdateOrganizationRequest.php
      CreateDomainRequest.php
      InviteUserRequest.php
      UpdateUserRoleRequest.php
    Resources/
      OrganizationResource.php
      OrganizationDomainResource.php
      OrganizationUserResource.php
    Controllers/
      OrganizationController.php
      OrganizationDomainController.php
      OrganizationUserController.php
  Services/
    OrganizationService.php
  Providers/
    AppServiceProvider.php   (policies registradas)
routes/api.php               (12 rutas nuevas)
```

---

### Divisiones

| Método | Ruta | Rol mínimo | Descripción |
|--------|------|-----------|-------------|
| GET | `/api/organizations/{org}/divisions` | sysadmin / org_admin | Lista divisiones |
| POST | `/api/organizations/{org}/divisions` | sysadmin / org_admin | Crea división |
| GET | `/api/organizations/{org}/divisions/{division}` | sysadmin / org_admin | Detalle de división |
| PUT | `/api/organizations/{org}/divisions/{division}` | sysadmin / org_admin | Actualiza división |
| DELETE | `/api/organizations/{org}/divisions/{division}` | sysadmin / org_admin | Soft delete división |

> Las divisiones agrupan categorías dentro de una organización (ej: Equipos, Redes, Plataforma, Seguridad Física, Periféricos).

---

## Jerarquía de recursos

```
Organization
  └── Division          (equipos, redes, plataforma, seguridad física, periféricos…)
        └── Category
              └── Subcategory
                    └── Asset
                          └── Credential
```

---

## Archivos creados (divisiones)

```
app/
  Models/Division.php
  Http/
    Requests/Organization/
      CreateDivisionRequest.php
      UpdateDivisionRequest.php
    Resources/
      DivisionResource.php
    Controllers/
      OrganizationDivisionController.php
database/migrations/
  2026_05_08_213054_create_divisions_table.php
  2026_05_08_213059_add_division_id_to_categories_table.php
```

---

## Siguiente fase

**Fase 3 — Jerarquía de Assets**
- CRUD de categorías (por división)
- CRUD de subcategorías
- CRUD de assets con metadata JSONB
- Políticas de acceso por organización
