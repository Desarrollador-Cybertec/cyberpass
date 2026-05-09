# Tokens de Acceso Compartido — API Reference

**Recurso:** `SharedAccessToken`  
**Tabla:** `shared_access_tokens`  
**Fecha:** 2026-05-09

---

## Propósito

Permite compartir una credencial de forma segura y controlada sin exponer el vault completo. Se genera un token único de 64 caracteres que un tercero puede consumir desde un endpoint público (sin autenticación) para obtener el usuario y contraseña desencriptados.

El token es efímero: admite expiración por fecha y/o límite de usos. Al agotarse cualquiera de estas condiciones, el token deja de funcionar.

---
                                   
## Jerarquía

```
Credential
  └── SharedAccessToken  (uno a muchos)
```

---

## Roles y permisos

| Acción | sysadmin | org_admin | org_user |
|--------|----------|-----------|----------|
| Listar tokens de una credencial | ✅ | ✅ (su org) | ✅ (su org) |
| Generar token | ✅ | ✅ (su org) | ✅ (su org) |
| Revocar token | ✅ | ✅ (su org) | ✅ (solo el propio) |
| Consumir token (público) | — | — | — |

> El endpoint de consumo (`GET /api/shared/{token}`) no requiere autenticación.

---

## Contrato común (endpoints protegidos)

- Requieren **Bearer token** vía `auth:sanctum`.
- Listados paginados a **50 por página**, orden descendente por `created_at`.
- Respuestas unitarias (`store`) salen como objeto JSON sin envoltura `data`.

### Códigos de estado (endpoints autenticados)

| Código | Cuándo |
|--------|--------|
| `200` | Listado, revocación exitosa |
| `201` | Token generado |
| `401` | Token ausente, inválido o expirado |
| `403` | Sin permiso |
| `404` | Credencial o token no encontrado |
| `422` | Validación fallida |

### Códigos de estado (endpoint público)

| Código | Cuándo |
|--------|--------|
| `200` | Token válido, credencial devuelta |
| `404` | Token inexistente, revocado, expirado o sin usos |

---

## Recurso de salida: `SharedAccessTokenResource`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | integer | ID del token |
| `token` | string | Cadena aleatoria de 64 caracteres |
| `expires_at` | datetime\|null | Fecha de expiración |
| `max_uses` | integer\|null | Máximo número de usos permitidos |
| `use_count` | integer | Número de veces consumido |
| `is_active` | boolean | Si fue revocado manualmente |
| `is_valid` | boolean | `true` si activo + no expirado + no agotado |
| `created_by` | integer | ID del usuario que generó el token |
| `created_at` | datetime | Fecha de creación |

---

## Endpoints autenticados

### `GET /api/credentials/{credential}/tokens`

**Acceso:** cualquier miembro de la org (sysadmin ve todo).

**Query params**

| Param | Tipo | Descripción |
|-------|------|-------------|
| `page` | integer | Página de resultados |

**Ejemplo de request**

```
GET /api/credentials/12/tokens?page=1
Authorization: Bearer {token}
```

**Respuesta `200`**

```json
{
    "data": [
        {
            "id": 3,
            "token": "aB3xK...64chars",
            "expires_at": "2026-05-16T14:00:00.000000Z",
            "max_uses": 5,
            "use_count": 2,
            "is_active": true,
            "is_valid": true,
            "created_by": 7,
            "created_at": "2026-05-09T15:00:00.000000Z"
        }
    ],
    "links": { "first": "...", "last": "...", "prev": null, "next": null },
    "meta": { "current_page": 1, "last_page": 1, "per_page": 50, "total": 1 }
}
```

---

### `POST /api/credentials/{credential}/tokens`

**Acceso:** sysadmin, org_admin o org_user de la misma org.

**Body** (todos opcionales)

| Campo | Tipo | Reglas |
|-------|------|--------|
| `expires_at` | datetime | Fecha futura válida |
| `max_uses` | integer | `min:1`, `max:1000` |

**Reglas de negocio**

- Si `expires_at` y `max_uses` se omiten, el token no expira y no tiene límite de usos.
- El token se genera automáticamente como cadena aleatoria de 64 caracteres.
- El campo `is_active` arranca en `true`; solo se desactiva por revocación explícita.

**Ejemplo de request**

```json
{
    "expires_at": "2026-05-16T23:59:59",
    "max_uses": 3
}
```

**Respuesta `201`**

```json
{
    "id": 4,
    "token": "aB3xKz9mQwErTyUiOpAsDF...64chars",
    "expires_at": "2026-05-16T23:59:59.000000Z",
    "max_uses": 3,
    "use_count": 0,
    "is_active": true,
    "is_valid": true,
    "created_by": 7,
    "created_at": "2026-05-09T15:30:00.000000Z"
}
```

**Errores**

- `403` — el usuario no pertenece a la org de la credencial.
- `422` — `expires_at` en el pasado, `max_uses` fuera de rango.

---

### `PATCH /api/credentials/{credential}/tokens/{token}/revoke`

**Acceso:** sysadmin, org_admin (su org) o el org_user que creó el token.

**Sin body.**

**Reglas de negocio**

- Cambia `is_active` a `false`.
- El token revocado devuelve `404` en el endpoint público.
- La revocación es irreversible desde la API (no hay endpoint para reactivar).

**Respuesta `200`**

```json
{
    "message": "Token revocado."
}
```

**Errores**

- `403` — sin permiso para revocar (ej. org_user intentando revocar token ajeno).
- `404` — token no pertenece a la credencial indicada.

---

## Endpoint público

### `GET /api/shared/{token}`

**Sin autenticación.** Throttle: 20 requests/minuto.

**Path params**

| Param | Descripción |
|-------|-------------|
| `token` | Cadena de 64 caracteres generada al crear el token |

**Reglas de negocio**

1. Si el token no existe → `404`.
2. Si `is_active = false` (revocado) → `404`.
3. Si `expires_at` es pasado → `404`.
4. Si `use_count >= max_uses` (cuando `max_uses` no es null) → `404`.
5. Si es válido → incrementa `use_count` en 1 y devuelve la credencial desencriptada.

> El servidor **siempre** responde `404` para tokens inválidos, independientemente del motivo. Esto evita filtrar información sobre el estado del token.

**Respuesta `200`**

```json
{
    "credential": {
        "id": 12,
        "name": "Router Principal",
        "username": "admin",
        "type": "password"
    },
    "password": "s3cr3t_pl4in_t3xt",
    "notes": "Cambiar contraseña antes del 2026-06-01",
    "token": {
        "expires_at": "2026-05-16T23:59:59.000000Z",
        "use_count": 3,
        "max_uses": 5
    }
}
```

> `notes` es `null` si la credencial no tiene notas.

**Errores**

- `404` — token inválido, expirado, revocado o agotado.

---

## Ciclo de vida de un token

```
Generado (is_active=true, use_count=0)
    │
    ├─► Consumido N veces → use_count++
    │       └─► use_count >= max_uses → inválido (404 en consumo)
    │
    ├─► expires_at llega → inválido (404 en consumo)
    │
    └─► PATCH /revoke → is_active=false → inválido (404 en consumo)
```

---

## Archivos relacionados

| Archivo | Rol |
|---------|-----|
| `database/migrations/2026_05_07_221328_create_shared_access_tokens_table.php` | Tabla con FK a credentials y users |
| `app/Models/SharedAccessToken.php` | Modelo con `isValid()` |
| `app/Models/Credential.php` | Relación `hasMany(SharedAccessToken::class)` |
| `app/Policies/SharedAccessTokenPolicy.php` | `viewAny`, `create`, `revoke` |
| `app/Http/Requests/CreateSharedTokenRequest.php` | Validación de generación |
| `app/Http/Resources/SharedAccessTokenResource.php` | Formato de salida |
| `app/Services/SharedAccessTokenService.php` | `create`, `revoke`, `consume` |
| `app/Http/Controllers/SharedAccessTokenController.php` | CRUD protegido (index, store, revoke) |
| `app/Http/Controllers/PublicTokenController.php` | Consumo público sin auth |
