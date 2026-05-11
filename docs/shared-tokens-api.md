# Tokens de Acceso Compartido — API Reference

**Recurso:** `SharedAccessToken`  
**Tabla:** `shared_access_tokens`  
**Fecha:** 2026-05-11 (revisado — flujo con PIN y link público)

---

## Propósito

Permite compartir una credencial de forma segura y controlada sin exponer el vault completo. Se genera un token único de 64 caracteres que produce un **link público temporal** apuntando al frontend de la app.

El receptor del link ve primero solo el nombre de la credencial. Para obtener los datos de acceso debe ingresar un **PIN** (definido por el creador) si el token lo requiere. El PIN viaja por un canal separado (fuera de banda), de modo que el link solo no es suficiente para acceder.

El token es efímero: admite expiración por fecha y/o límite de usos. Al agotarse cualquiera de estas condiciones, el link deja de funcionar.

---

## Jerarquía

```
Credential
  └── SharedAccessToken  (uno a muchos)
```

---

## Roles y permisos (endpoints autenticados)

| Acción | sysadmin | org_admin | org_user |
|--------|----------|-----------|----------|
| Listar tokens de una credencial | ✅ | ✅ (su org) | ✅ (su org) |
| Generar token | ✅ | ✅ (su org) | ✅ (su org) |
| Revocar token | ✅ | ✅ (su org) | ✅ (solo el propio) |

> Los endpoints de consulta pública (`GET /api/shared/{token}` y `POST /api/shared/{token}/claim`) no requieren autenticación.

---

## Flujo completo

```
1. Miembro de la org → POST /api/credentials/{cred}/tokens
   → define PIN (opcional), expiración y/o max_uses
   → obtiene el token de 64 chars

2. Comparte por separado:
   - Link:  https://app.cyberpass.com/shared/{token}  (frontend)
   - PIN:   canal seguro aparte (WhatsApp, email cifrado, etc.)

3. Receptor abre el link → frontend llama GET /api/shared/{token}
   → ve nombre de la credencial y si requiere PIN

4. Receptor ingresa el PIN → frontend llama POST /api/shared/{token}/claim { pin }
   → obtiene usuario y contraseña desencriptados
   → el uso se incrementa en 1
```

---

## Contrato común (endpoints autenticados)

- Requieren **Bearer token** vía `auth:sanctum`.
- Listados paginados a **50 por página**, orden descendente por `created_at`.
- Respuestas unitarias (`store`) salen como objeto JSON sin envoltura `data`.

### Códigos de estado (endpoints autenticados)

| Código | Cuándo |
|--------|--------|
| `200` | Listado, revocación exitosa |
| `201` | Token generado |
| `401` | Sanctum token ausente o inválido |
| `403` | Sin permiso |
| `404` | Credencial o token no encontrado |
| `422` | Validación fallida |

### Códigos de estado (endpoints públicos)

| Código | Cuándo |
|--------|--------|
| `200` | Token válido |
| `403` | PIN incorrecto |
| `404` | Token inexistente, revocado, expirado o sin usos |

---

## Recurso de salida: `SharedAccessTokenResource`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | integer | ID del token |
| `token` | string | Cadena aleatoria de 64 caracteres |
| `share_url` | string | Link completo listo para compartir (`{FRONTEND_URL}/shared/{token}`) |
| `requires_pin` | boolean | Si el token exige PIN para ser reclamado |
| `expires_at` | datetime\|null | Fecha de expiración |
| `max_uses` | integer\|null | Máximo número de usos permitidos |
| `use_count` | integer | Número de veces reclamado exitosamente |
| `is_active` | boolean | `false` si fue revocado manualmente |
| `is_valid` | boolean | `true` si activo + no expirado + no agotado |
| `created_by` | integer | ID del usuario que generó el token |
| `created_at` | datetime | Fecha de creación |

> `pin_hash` nunca se incluye en ninguna respuesta.  
> `share_url` se construye con la variable de entorno `FRONTEND_URL`. Si no está definida, se usa `APP_URL`.

---

## Endpoints autenticados

### `GET /api/credentials/{credential}/tokens`

**Acceso:** cualquier miembro de la org (sysadmin ve todo).

**Query params**

| Param | Tipo | Descripción |
|-------|------|-------------|
| `page` | integer | Página de resultados |

**Respuesta `200`**

```json
{
    "data": [
        {
            "id": 3,
            "token": "aB3xK...64chars",
            "share_url": "https://app.cyberpass.com/shared/aB3xK...64chars",
            "requires_pin": true,
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
| `pin` | string | Mínimo 4, máximo 16 caracteres. Si se omite, el link es accesible sin PIN. |
| `expires_at` | datetime | Fecha futura válida. |
| `max_uses` | integer | `min:1`, `max:1000`. |

**Reglas de negocio**

- Si `pin` se provee, se almacena hasheado (bcrypt). El PIN en texto plano **nunca se guarda ni se devuelve**.
- Si `expires_at` y `max_uses` se omiten, el token no expira y no tiene límite de usos.
- El token (64 chars) se genera automáticamente; es el identificador del link público.
- `is_active` arranca en `true`; solo se desactiva por revocación explícita.

**Ejemplo de request**

```json
{
    "pin": "7294",
    "expires_at": "2026-05-20T23:59:59",
    "max_uses": 3
}
```

**Respuesta `201`**

```json
{
    "id": 4,
    "token": "aB3xKz9mQwErTyUiOpAsDF...64chars",
    "share_url": "https://app.cyberpass.com/shared/aB3xKz9mQwErTyUiOpAsDF...64chars",
    "requires_pin": true,
    "expires_at": "2026-05-20T23:59:59.000000Z",
    "max_uses": 3,
    "use_count": 0,
    "is_active": true,
    "is_valid": true,
    "created_by": 7,
    "created_at": "2026-05-11T15:30:00.000000Z"
}
```

> El campo `share_url` es el link listo para copiar y enviar. El creador debe compartir el PIN por un canal separado. Si lo pierde, deberá revocar el token y generar uno nuevo.

**Errores**

- `403` — el usuario no pertenece a la org de la credencial.
- `422` — `expires_at` en el pasado, `max_uses` fuera de rango, PIN demasiado corto.

---

### `PATCH /api/credentials/{credential}/tokens/{token}/revoke`

**Acceso:** sysadmin, org_admin (su org) o el org_user que creó el token.

**Sin body.**

**Reglas de negocio**

- Cambia `is_active` a `false`.
- El link revocado devuelve `404` en los endpoints públicos.
- La revocación es irreversible desde la API.

**Respuesta `200`**

```json
{
    "message": "Token revocado."
}
```

**Errores**

- `403` — sin permiso para revocar.
- `404` — token no pertenece a la credencial indicada.

---

## Endpoints públicos

### `GET /api/shared/{token}`

**Sin autenticación.** Throttle: 20 requests/minuto.

**Propósito:** permite al frontend mostrar la página del link antes de pedir el PIN. No revela datos de acceso ni consume un uso.

**Reglas de negocio**

1. Si el token no existe o no es válido (revocado, expirado, agotado) → `404`.
2. Si es válido → devuelve metadata de la credencial e indica si requiere PIN.

**Respuesta `200`**

```json
{
    "credential": {
        "name": "Router Principal",
        "type": "password"
    },
    "requires_pin": true,
    "expires_at": "2026-05-20T23:59:59.000000Z",
    "is_valid": true
}
```

**Errores**

- `404` — token inválido, expirado, revocado o agotado.

---

### `POST /api/shared/{token}/claim`

**Sin autenticación.** Throttle: 10 requests/minuto.

**Propósito:** reclama el link. Valida el PIN (si aplica), devuelve los datos de acceso desencriptados e incrementa el contador de usos.

**Body**

| Campo | Tipo | Reglas |
|-------|------|--------|
| `pin` | string | Requerido si el token tiene PIN. Ignorado si no lo tiene. |

**Reglas de negocio**

1. Si el token no existe o no es válido → `404` (igual que `info`, por seguridad).
2. Si el token requiere PIN y no se envía `pin` o no coincide → `403`.
3. Si el PIN es correcto (o no se requiere) → devuelve credencial desencriptada e incrementa `use_count`.
4. Si después de incrementar `use_count >= max_uses`, el siguiente intento devolverá `404`.

**Ejemplo de request (con PIN)**

```json
{
    "pin": "7294"
}
```

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
        "expires_at": "2026-05-20T23:59:59.000000Z",
        "use_count": 1,
        "max_uses": 3
    }
}
```

> `notes` es `null` si la credencial no tiene notas.

**Errores**

- `403` — PIN incorrecto.
- `404` — token inválido, expirado, revocado o agotado.

---

## Ciclo de vida de un token

```
Generado (is_active=true, use_count=0)
    │
    ├─► GET /shared/{token} → muestra metadata (no consume uso)
    │
    ├─► POST /shared/{token}/claim → valida PIN → use_count++
    │       └─► use_count >= max_uses → próximo claim devuelve 404
    │
    ├─► expires_at llega → 404 en info y claim
    │
    └─► PATCH /revoke → is_active=false → 404 en info y claim
```

---

## Archivos relacionados

| Archivo | Rol |
|---------|-----|
| `database/migrations/2026_05_07_221328_create_shared_access_tokens_table.php` | Tabla base con FK a credentials y users |
| `database/migrations/2026_05_11_210016_add_pin_hash_to_shared_access_tokens_table.php` | Agrega columna `pin_hash` |
| `app/Models/SharedAccessToken.php` | Modelo con `isValid()`, `requiresPin()` |
| `app/Models/Credential.php` | Relación `hasMany(SharedAccessToken::class)` |
| `app/Policies/SharedAccessTokenPolicy.php` | `viewAny`, `create`, `revoke` |
| `app/Http/Requests/CreateSharedTokenRequest.php` | Validación de generación (incluye `pin`) |
| `app/Http/Resources/SharedAccessTokenResource.php` | Formato de salida (sin `pin_hash`) |
| `app/Services/SharedAccessTokenService.php` | `create()`, `revoke()`, `info()`, `claim()` |
| `app/Http/Controllers/SharedAccessTokenController.php` | CRUD protegido (index, store, revoke) |
| `app/Http/Controllers/PublicTokenController.php` | `info()` (GET público) y `claim()` (POST público) |
