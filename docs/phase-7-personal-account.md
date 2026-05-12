# CyberPass API — Fase 7: Cuenta Personal

## Contexto

Un usuario con correo no corporativo puede registrarse directamente sin invitación, usar el vault personal (Fase 5) y, si posteriormente recibe una invitación de org, unirse a ella sin perder su cuenta ni sus credenciales personales. La Fase 7 también cubre la eliminación voluntaria de la cuenta personal.

---

## 1. Registro Personal

### `POST /auth/register`
**Sin autenticación. Ya existía — confirmado funcional.**

**Body:**
```json
{
  "name": "Juan García",
  "email": "juan@gmail.com",
  "password": "miPassword123",
  "password_confirmation": "miPassword123"
}
```

**Response `201`:**
```json
{
  "user": {
    "id": 1,
    "name": "Juan García",
    "email": "juan@gmail.com",
    "account_type": "personal",
    "role": "org_user",
    "two_factor_enabled": false,
    "is_active": true,
    "organization": null,
    "vault_enabled": true
  },
  "token": "1|abc..."
}
```

`AuthService::register()` ya asigna `account_type = 'personal'` explícitamente.

---

## 2. Tabla `organization_invitations`

Se reemplazó el reuso de `password_reset_tokens` para invitaciones de org por una tabla dedicada que permite guardar `role` y `organization_id` junto con el token.

### Schema

```sql
CREATE TABLE organization_invitations (
  id              BIGSERIAL PRIMARY KEY,
  email           VARCHAR(255) NOT NULL,
  organization_id BIGINT NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
  role            VARCHAR(50) NOT NULL DEFAULT 'org_user',
  token_hash      VARCHAR(255) NOT NULL,
  expires_at      TIMESTAMP NOT NULL,
  created_at      TIMESTAMP DEFAULT NOW()
);
```

**TTL del token:** 7 días (igual que antes).

`OrganizationService::inviteUser()` ahora usa `organization_invitations` en lugar de `password_reset_tokens`.

---

## 3. Aceptar Invitación — Flujo Bifurcado

### `POST /auth/invitations/accept`
**Sin autenticación. Throttle: 10 req/min.**

#### Caso A — Usuario nuevo (nunca se registró)

**Body:**
```json
{
  "token": "abc123...",
  "email": "nuevo@empresa.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

**Comportamiento:**
1. Valida token contra `organization_invitations`
2. Verifica TTL (7 días)
3. Actualiza usuario: `is_active = true`, `password` hasheado, `organization_id`, `role` de la invitación
4. Elimina la invitación
5. Audit log: `invite_accepted`

**Response `200`:**
```json
{ "message": "Cuenta activada correctamente. Ya puedes iniciar sesión." }
```

---

#### Caso B — Usuario personal ya registrado y activo

**Body (sin password):**
```json
{
  "token": "abc123...",
  "email": "juan@gmail.com"
}
```

**Comportamiento:**
1. Valida token contra `organization_invitations`
2. Detecta que el usuario existe, `is_active = true` y `account_type = 'personal'`
3. Actualiza: `organization_id`, `account_type = 'enterprise'`, `role` de la invitación
4. **No cambia la contraseña** — el usuario ya tiene una
5. **El vault personal se mantiene** (las categorías/credenciales tienen `user_id` y no dependen de `organization_id`)
6. Elimina la invitación
7. Audit log: `invite_accepted`

**Response `200`:**
```json
{ "message": "Te has unido a la organización correctamente." }
```

---

#### Caso C — Usuario ya enterprise

**Response `422`:**
```json
{ "message": "Ya perteneces a una organización." }
```

---

#### Errores comunes

| Código | Motivo |
|---|---|
| `422` | Token inválido o expirado |
| `422` | Email no tiene invitación pendiente |
| `422` | Ya pertenece a una organización |

---

## 4. `/auth/me` — Respuesta actualizada

```json
{
  "id": 1,
  "name": "Juan García",
  "email": "juan@gmail.com",
  "account_type": "personal",
  "role": "org_user",
  "two_factor_enabled": false,
  "is_active": true,
  "last_login_at": "2026-05-12T10:00:00Z",
  "organization": null,
  "vault_enabled": true
}
```

Para usuario enterprise:
```json
{
  "id": 2,
  "name": "Ana Pérez",
  "email": "ana@empresa.com",
  "account_type": "enterprise",
  "role": "org_admin",
  "two_factor_enabled": true,
  "is_active": true,
  "last_login_at": "2026-05-12T09:00:00Z",
  "organization": {
    "id": 5,
    "name": "Empresa SA",
    "slug": "empresa-sa"
  },
  "vault_enabled": true
}
```

`vault_enabled: true` siempre — todos los usuarios autenticados tienen acceso al vault personal independientemente de su org.

---

## 5. Eliminar Cuenta Personal

### `DELETE /auth/account`
**Requiere autenticación. Solo `account_type = 'personal'`.**

**Response `200`:**
```json
{ "message": "Cuenta eliminada correctamente." }
```

**Response `403`** (usuario enterprise):
```json
{ "message": "Solo las cuentas personales pueden eliminarse desde aquí." }
```

**Comportamiento:**
- Revoca todos los tokens Sanctum (`$user->tokens()->delete()`)
- Soft delete del usuario (`$user->delete()`)
- Las vault categories/credentials se eliminan en cascade por la FK `user_id → users(id) ON DELETE CASCADE`
- Registra en `audit_logs` action `delete`, entity `User`

**Nota:** usuarios enterprise deben ser gestionados por su `org_admin` o `sysadmin`.

---

## Migraciones

| Archivo | Cambio |
|---|---|
| `2026_05_12_120001_create_organization_invitations_table` | Nueva tabla de invitaciones con `role` y `organization_id` |

---

## Archivos creados

| Archivo | Descripción |
|---|---|
| `app/Models/OrganizationInvitation.php` | Modelo de invitación de org |

## Archivos modificados

| Archivo | Cambio |
|---|---|
| `app/Services/OrganizationService.php` | `inviteUser()` usa `organization_invitations`; maneja usuarios personales existentes |
| `app/Http/Controllers/Auth/InvitationController.php` | Flujo bifurcado: nuevo usuario vs personal ya registrado vs enterprise |
| `app/Http/Requests/Auth/AcceptInvitationRequest.php` | `password` opcional si el usuario ya existe como personal activo |
| `app/Http/Controllers/Auth/ProfileController.php` | Método `deleteAccount()` |
| `app/Http/Resources/UserResource.php` | `vault_enabled: true`, `organization: null` explícito para personales |
| `routes/api.php` | `DELETE /auth/account` |
