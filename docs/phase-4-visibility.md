# Fase 4 — Visibilidad, Verificación e Invitaciones

**Estado:** Completada  
**Fecha:** 2026-05-11

---

## Resumen de entregables

| # | Entregable | Endpoints nuevos |
|---|-----------|-----------------|
| 1 | Historial de versiones de credenciales | 3 |
| 2 | Audit logs API | 2 |
| 3 | Búsqueda y filtrado de credenciales | 1 nuevo + 1 mejorado |
| 4 | Verificación DNS de dominios | 2 |
| 5 | Flujo de invitación con email y token | 1 público |

---

## 1. Historial de versiones de credenciales

### Propósito

Cada vez que se edita una credencial, el estado anterior se guarda en `credential_versions`. Esta sección expone esos snapshots para auditoría y permite restaurar versiones anteriores.

### Jerarquía

```
Category → Credential → CredentialVersion  (uno a muchos)
```

### Roles y permisos

| Acción | sysadmin | org_admin | org_user |
|--------|----------|-----------|----------|
| Listar versiones | ✅ | ✅ (su org) | ✅ (su org) |
| Revelar password de versión | ✅ | ✅ (su org) | ❌ |
| Restaurar versión | ✅ | ✅ (su org) | ❌ |

### Recurso de salida: `CredentialVersionResource`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | integer | ID de la versión |
| `username` | string\|null | Nombre de usuario en ese momento |
| `changed_by.id` | integer | ID del usuario que realizó el cambio |
| `changed_by.name` | string | Nombre del usuario que realizó el cambio |
| `created_at` | datetime | Cuándo se guardó la versión |

> Los campos `encrypted_password` e `iv` **nunca se exponen** en este recurso.

---

### `GET /api/categories/{category}/credentials/{credential}/versions`

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
            "id": 5,
            "username": "admin_old",
            "changed_by": { "id": 2, "name": "Juan Martínez" },
            "created_at": "2026-05-10T09:15:00.000000Z"
        }
    ],
    "links": { "first": "...", "last": "...", "prev": null, "next": null },
    "meta": { "current_page": 1, "last_page": 1, "per_page": 20, "total": 1 }
}
```

**Errores**

- `403` — el usuario no pertenece a la org de la credencial.
- `404` — la credencial no pertenece a la categoría indicada.

---

### `GET /api/categories/{category}/credentials/{credential}/versions/{version}/reveal`

**Acceso:** `sysadmin` o `org_admin` de la misma org.

**Sin body.**

**Reglas de negocio**

- Solo devuelve el password de esa versión histórica, desencriptado.
- Genera registro en `audit_logs` con `action = 'reveal_password'`, `entity_type = CredentialVersion`.

**Respuesta `200`**

```json
{
    "password": "s3cr3t_pl4in_t3xt"
}
```

**Errores**

- `403` — `org_user` o usuario de otra org.
- `404` — la versión no pertenece a la credencial indicada.

---

### `POST /api/categories/{category}/credentials/{credential}/versions/{version}/restore`

**Acceso:** `sysadmin` o `org_admin` de la misma org.

**Sin body.**

**Reglas de negocio**

1. El estado actual de la credencial se guarda primero como una nueva versión (la restauración es reversible).
2. Los campos `username`, `encrypted_password` e `iv` se sobreescriben con los de la versión seleccionada.
3. Se genera registro en `audit_logs` con `action = 'update'` y `metadata.restored_version_id`.

**Respuesta `200`**

```json
{
    "message": "Versión restaurada correctamente.",
    "credential": {
        "id": 7,
        "name": "Router Principal",
        "username": "admin_old",
        "type": "password",
        "category_id": 3,
        "created_at": "2026-05-09T10:00:00.000000Z"
    }
}
```

**Errores**

- `403` — `org_user` o usuario de otra org.
- `404` — la versión no pertenece a la credencial o la credencial no pertenece a la categoría.

---

## 2. Audit Logs API

### Propósito

Expone los registros de `audit_logs` que se han ido acumulando desde Fase 1. Permite a sysadmins ver toda la actividad del sistema y a org_admins auditar su propia organización.

### Roles y permisos

| Acción | sysadmin | org_admin | org_user |
|--------|----------|-----------|----------|
| `GET /audit-logs` | ✅ (todos) | ✅ (solo su org) | ❌ 403 |
| `GET /organizations/{org}/audit-logs` | ✅ | ✅ (solo su org) | ❌ 403 |

### Recurso de salida: `AuditLogResource`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | integer | ID del registro |
| `action` | string | Acción ejecutada (ej. `create`, `update`, `delete`, `view`, `reveal_password`, `invite_accepted`) |
| `entity_type` | string\|null | Modelo afectado (ej. `App\Models\Credential`) |
| `entity_id` | integer\|null | ID del modelo afectado |
| `ip_address` | string\|null | IP desde donde se realizó la acción |
| `metadata` | object\|null | Datos adicionales (ej. `restored_version_id`) |
| `created_at` | datetime | Cuándo ocurrió |
| `user.id` | integer | ID del usuario que ejecutó la acción |
| `user.name` | string | Nombre del usuario |
| `user.email` | string | Email del usuario |
| `organization.id` | integer\|null | ID de la organización |
| `organization.name` | string\|null | Nombre de la organización |

---

### `GET /api/audit-logs`

**Acceso:** `sysadmin` (ve todo) o `org_admin` (solo su propia org — scope automático).

**Query params**

| Param | Tipo | Descripción |
|-------|------|-------------|
| `organization_id` | integer | Solo para sysadmin — filtra por org |
| `user_id` | integer | Filtra por usuario |
| `action` | string | Filtra por acción exacta |
| `entity_type` | string | Filtra por tipo de entidad |
| `start_date` | date (`Y-m-d`) | Fecha desde (inclusive) |
| `end_date` | date (`Y-m-d`) | Fecha hasta (inclusive) |
| `page` | integer | Página de resultados |

**Reglas de negocio**

- Si el usuario autenticado es `org_admin`, el parámetro `organization_id` se ignora; el scope siempre es su propia org.
- Orden descendente por `created_at` (más reciente primero).
- Paginación: 20 por página.

**Respuesta `200`**

```json
{
    "data": [
        {
            "id": 42,
            "action": "reveal_password",
            "entity_type": "App\\Models\\Credential",
            "entity_id": 7,
            "ip_address": "192.168.1.10",
            "metadata": null,
            "created_at": "2026-05-11T14:30:00.000000Z",
            "user": { "id": 3, "name": "Juan Martínez", "email": "juan@acme.com" },
            "organization": { "id": 1, "name": "ACME Corp" }
        }
    ],
    "links": { "first": "...", "last": "...", "prev": null, "next": null },
    "meta": { "current_page": 1, "last_page": 5, "per_page": 20, "total": 98 }
}
```

**Errores**

- `403` — usuario `org_user`.

---

### `GET /api/organizations/{organization}/audit-logs`

**Acceso:** `sysadmin` o `org_admin` de esa organización.

**Query params:** mismos que el endpoint global, excepto `organization_id` (ya está en la URL).

**Reglas de negocio**

- Solo devuelve registros donde `audit_logs.organization_id = {organization}`.
- Un `org_admin` de otra org recibe `403`.

**Respuesta `200`:** misma estructura que el endpoint global.

**Errores**

- `403` — sin permiso sobre esa organización.
- `404` — organización no encontrada.

---

## 3. Búsqueda y filtrado de credenciales

### Propósito

Permite buscar credenciales dentro de una organización completa (sin necesitar la categoría) y aplicar filtros de texto en el endpoint por categoría existente.

### Roles y permisos

| Acción | sysadmin | org_admin | org_user |
|--------|----------|-----------|----------|
| Búsqueda org-wide | ✅ | ✅ (su org) | ✅ (su org) |
| Filtros en endpoint por categoría | ✅ | ✅ (su org) | ✅ (su org) |

---

### `GET /api/organizations/{organization}/credentials` _(nuevo)_

**Acceso:** cualquier miembro de la org (sysadmin ve todo).

**Query params**

| Param | Tipo | Descripción |
|-------|------|-------------|
| `q` | string | Búsqueda parcial en `name` o `username` (case-insensitive) |
| `category_id` | integer | Filtra por categoría específica |
| `division_id` | integer | Filtra por división (busca en la categoría de la credencial) |
| `type` | string | Filtra por tipo (`password`, `api_key`, etc.) |
| `page` | integer | Página de resultados |

**Reglas de negocio**

- La búsqueda `q` usa `ILIKE` de PostgreSQL (case-insensitive), aplicado sobre `name` y `username` con `OR`.
- Los campos sensibles (`encrypted_password`, `iv`, `notes_encrypted`, `iv_notes`) nunca se incluyen en la respuesta.
- No genera registros en `audit_logs` (consistente con todos los endpoints `index`).
- Paginación: 20 por página.

**Respuesta `200`**

```json
{
    "data": [
        {
            "id": 7,
            "name": "Router Principal",
            "username": "admin",
            "type": "password",
            "category_id": 3,
            "image": null,
            "created_at": "2026-05-09T10:00:00.000000Z"
        }
    ],
    "links": { "first": "...", "last": "...", "prev": null, "next": null },
    "meta": { "current_page": 1, "last_page": 1, "per_page": 20, "total": 1 }
}
```

**Errores**

- `403` — el usuario no pertenece a la org indicada.
- `404` — organización no encontrada.

---

### `GET /api/categories/{category}/credentials` _(mejorado)_

Igual que antes pero ahora acepta filtros adicionales:

| Param nuevo | Tipo | Descripción |
|-------------|------|-------------|
| `q` | string | Búsqueda parcial en `name` o `username` (ILIKE) |
| `type` | string | Filtra por tipo de credencial |

Los params existentes (`page`) siguen funcionando igual.

---

## 4. Verificación DNS de dominios

### Propósito

Permite a una organización verificar que realmente controla un dominio mediante un registro DNS TXT. El flujo es estándar en la industria (similar a Google Workspace, GitHub Pages, etc.): se genera un token único, se añade como TXT record en el DNS, y se verifica con una consulta directa desde el servidor.

### Roles y permisos

| Acción | sysadmin | org_admin | org_user |
|--------|----------|-----------|----------|
| Iniciar verificación | ✅ | ✅ (su org) | ❌ |
| Confirmar verificación | ✅ | ✅ (su org) | ❌ |

### Flujo completo

```
1. POST .../verify/initiate → recibe el valor TXT a añadir en el DNS
2. Admin añade el TXT record en su proveedor DNS
3. POST .../verify/confirm → el servidor hace dns_get_record() y valida
4. Si coincide → domain.is_verified = true
```

---

### `POST /api/organizations/{organization}/domains/{domain}/verify/initiate`

**Acceso:** `sysadmin` o `org_admin` de la misma org.

**Sin body.**

**Reglas de negocio**

- Genera un token aleatorio de 32 caracteres prefijado con `cyberpass-verify=`.
- Guarda el token en `organization_domains.verification_token` y establece `verification_expires_at = now() + 72 horas`.
- Es **idempotente**: llamarlo de nuevo regenera el token y reinicia el TTL.
- No valida si el dominio ya está verificado (permite re-verificar).

**Respuesta `200`**

```json
{
    "txt_record": "cyberpass-verify=aB3xK9mQwErTyUiOpAs...32chars",
    "instructions": "Add a TXT record to your DNS for 'acme.com' with the value above. Then call the confirm endpoint. The token expires in 72 hours."
}
```

**Errores**

- `403` — sin permiso para gestionar dominios de esta org.
- `404` — el dominio no pertenece a la organización indicada.

---

### `POST /api/organizations/{organization}/domains/{domain}/verify/confirm`

**Acceso:** `sysadmin` o `org_admin` de la misma org.

**Sin body.**

**Reglas de negocio**

1. Si el dominio ya tiene `is_verified = true` → responde `200` sin hacer nada.
2. Si no hay `verification_token` o `verification_expires_at` → responde `422` (debe iniciar primero).
3. Si `verification_expires_at` es pasado → responde `422` con mensaje de token expirado.
4. Realiza `dns_get_record($domain, DNS_TXT)` desde el servidor.
5. Si algún registro TXT coincide exactamente con `cyberpass-verify={token}` → marca `is_verified = true`, borra el token y genera registro en `audit_logs` (`action = 'update'`, `metadata.action = 'domain_verified'`).
6. Si no hay coincidencia → responde `422`.

**Respuesta `200` — verificado**

```json
{
    "message": "Dominio 'acme.com' verificado correctamente."
}
```

**Respuesta `200` — ya estaba verificado**

```json
{
    "message": "El dominio ya está verificado."
}
```

**Respuesta `422` — TXT no encontrado**

```json
{
    "message": "TXT record not found or token has expired. Verify your DNS configuration and try again."
}
```

**Errores**

- `403` — sin permiso para gestionar dominios de esta org.
- `404` — el dominio no pertenece a la organización indicada.

> **Nota:** La propagación DNS puede tardar entre minutos y 48 horas dependiendo del TTL del proveedor. Si `confirm` devuelve `422`, esperar y reintentar.

---

## 5. Flujo de invitación con email y token

### Propósito

Reemplaza el comportamiento anterior donde `POST /organizations/{org}/users` creaba una cuenta activa con contraseña aleatoria sin notificar al usuario. Ahora el invitado recibe un email con un enlace para activar su cuenta y establecer su propia contraseña.

### Flujo completo

```
1. org_admin → POST /organizations/{org}/users
   → crea usuario con is_active=false
   → genera token de 64 chars, almacena hash en password_reset_tokens (TTL 7 días)
   → envía OrganizationInvitationMail al email del invitado

2. Invitado recibe email → hace clic en el enlace del frontend
   → frontend llama POST /auth/invitations/accept con { token, email, password, password_confirmation }

3. API valida el token → activa cuenta → elimina token
```

### Cambio en `POST /api/organizations/{organization}/users`

El contrato de entrada no cambia. El cambio es de comportamiento:

**Antes:** usuario creado con `is_active = true` y contraseña aleatoria (inaccesible).  
**Ahora:** usuario creado con `is_active = false` y email de invitación enviado.

> El usuario no puede autenticarse hasta aceptar la invitación.

---

### `POST /api/auth/invitations/accept` _(público, sin auth)_

**Throttle:** 10 requests/minuto.

**Body**

| Campo | Tipo | Reglas |
|-------|------|--------|
| `token` | string | Requerido. Exactamente 64 caracteres. |
| `email` | string | Requerido. Email válido. |
| `password` | string | Requerido. Mínimo 8 caracteres. Confirmado. |
| `password_confirmation` | string | Requerido. Debe coincidir con `password`. |

**Reglas de negocio**

1. El email debe corresponder a un usuario existente con `is_active = false`. Si el usuario no existe o ya está activo → `422`.
2. El token se valida con `Hash::check()` contra el hash almacenado en `password_reset_tokens`.
3. El token debe tener menos de 7 días de antigüedad. Si expiró → `422`.
4. Si todo es válido:
   - Se establece la nueva contraseña (`is_active = true`).
   - Se elimina el registro de `password_reset_tokens`.
   - Se genera registro en `audit_logs` con `action = 'invite_accepted'`.

**Respuesta `200`**

```json
{
    "message": "Cuenta activada correctamente. Ya puedes iniciar sesión."
}
```

**Respuesta `422` — token inválido**

```json
{
    "message": "Token de invitación inválido."
}
```

**Respuesta `422` — token expirado**

```json
{
    "message": "El token de invitación ha expirado."
}
```

**Respuesta `422` — ya activo o usuario no existe**

```json
{
    "message": "Invitación inválida o ya aceptada."
}
```

**Errores de validación `422`**

```json
{
    "message": "The token field must be 64 characters.",
    "errors": {
        "token": ["The token field must be 64 characters."],
        "password": ["The password field confirmation does not match."]
    }
}
```

---

## Resumen de nuevos endpoints

| Método | Ruta | Auth | Rol mínimo |
|--------|------|------|-----------|
| `GET` | `/api/categories/{cat}/credentials/{cred}/versions` | ✅ | org_user |
| `GET` | `/api/categories/{cat}/credentials/{cred}/versions/{ver}/reveal` | ✅ | org_admin |
| `POST` | `/api/categories/{cat}/credentials/{cred}/versions/{ver}/restore` | ✅ | org_admin |
| `GET` | `/api/audit-logs` | ✅ | org_admin |
| `GET` | `/api/organizations/{org}/audit-logs` | ✅ | org_admin |
| `GET` | `/api/organizations/{org}/credentials` | ✅ | org_user |
| `POST` | `/api/organizations/{org}/domains/{domain}/verify/initiate` | ✅ | org_admin |
| `POST` | `/api/organizations/{org}/domains/{domain}/verify/confirm` | ✅ | org_admin |
| `POST` | `/api/auth/invitations/accept` | ❌ | — |

---

## Archivos relacionados

| Archivo | Rol |
|---------|-----|
| `app/Http/Controllers/CredentialVersionController.php` | Listado, reveal y restore de versiones |
| `app/Http/Resources/CredentialVersionResource.php` | Formato de salida (sin datos sensibles) |
| `app/Policies/CredentialVersionPolicy.php` | `viewAny` (org member), `restore` (org_admin+) |
| `app/Services/CredentialService.php` | `decryptVersion()`, `restoreVersion()` |
| `app/Http/Controllers/AuditLogController.php` | `index()` global, `forOrganization()` scoped |
| `app/Http/Resources/AuditLogResource.php` | Formato con relaciones `user` y `organization` |
| `app/Policies/AuditLogPolicy.php` | `viewAny`, `viewOrganization` |
| `app/Http/Controllers/CredentialSearchController.php` | Búsqueda org-wide |
| `app/Http/Controllers/DomainVerificationController.php` | `initiate()`, `confirm()` |
| `app/Services/DomainVerificationService.php` | Lógica de generación y verificación DNS |
| `app/Models/OrganizationDomain.php` | Campos `verification_token`, `verification_expires_at` |
| `database/migrations/2026_05_11_203230_add_verification_fields_to_organization_domains_table.php` | Migración de campos de verificación |
| `app/Http/Controllers/Auth/InvitationController.php` | `accept()` — activa cuenta con token |
| `app/Http/Requests/Auth/AcceptInvitationRequest.php` | Validación de aceptación |
| `app/Mail/OrganizationInvitationMail.php` | Mailable de invitación |
| `resources/views/emails/invitation.blade.php` | Template HTML del email |
| `app/Services/OrganizationService.php` | `inviteUser()` actualizado — is_active=false + mail |
