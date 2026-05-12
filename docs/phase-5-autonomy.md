# CyberPass API — Fase 5: Autonomía del Usuario

## Contexto

La Fase 5 agrega capacidades de autoservicio para el usuario final: recuperación de contraseña, gestión de perfil propio, vault personal de credenciales y exportación de credenciales.

---

## 1. Recuperación de Contraseña

### Endpoints

#### `POST /auth/forgot-password` — Solicitar reset
**Sin autenticación. Throttle: 5 req/min.**

**Body:**
```json
{ "email": "usuario@empresa.com" }
```

**Response `200`** (siempre — no revela si el email existe):
```json
{ "message": "Si el correo está registrado, recibirás un enlace para restablecer tu contraseña." }
```

**Comportamiento:**
- Si el email existe → genera token de 64 chars, guarda hash en `password_reset_tokens` (upsert idempotente), despacha `PasswordResetMail`
- Si el email no existe → misma respuesta 200, sin efecto
- Volver a llamar regenera el token y reenvía el email

**Email enviado:**
- Asunto: `Recuperación de contraseña — CyberPass`
- Link: `{FRONTEND_URL}/auth/reset-password?token={token}&email={email}`
- TTL: **60 minutos**

---

#### `POST /auth/reset-password` — Ejecutar reset
**Sin autenticación. Throttle: 5 req/min.**

**Body:**
```json
{
  "token": "abc123...",
  "email": "usuario@empresa.com",
  "password": "nuevaContraseña123",
  "password_confirmation": "nuevaContraseña123"
}
```

**Response `200`:**
```json
{ "message": "Contraseña restablecida correctamente. Ya puedes iniciar sesión." }
```

**Response `422`** (token inválido, expirado, o email no encontrado):
```json
{ "message": "Token inválido o expirado." }
```

**Reglas de negocio:**
- Valida token con `Hash::check()` contra el hash en `password_reset_tokens`
- TTL de 60 minutos desde `created_at`
- Al resetear: invalida **todos** los tokens Sanctum del usuario (`$user->tokens()->delete()`)
- Elimina el registro en `password_reset_tokens`
- Registra en `audit_logs` con action `password_reset`

---

## 2. Gestión de Perfil

### Endpoints

#### `PUT /auth/profile` — Actualizar nombre y/o email
**Requiere autenticación.**

**Body (todos opcionales):**
```json
{
  "name": "Nuevo Nombre",
  "email": "nuevo@email.com"
}
```

**Response `200`:**
```json
{
  "message": "Perfil actualizado correctamente.",
  "user": {
    "id": 1,
    "name": "Nuevo Nombre",
    "email": "nuevo@email.com"
  }
}
```

**Response `422`** (cambio de email bloqueado por dominio verificado):
```json
{ "message": "No puedes cambiar tu email en una organización con dominio verificado." }
```

**Reglas de negocio:**
- El email debe ser único en la tabla `users`
- Si la organización del usuario tiene al menos un dominio con `is_verified = true`, el email **no puede cambiarse**
- Registra en `audit_logs` con action `update`, entity `User`, metadata `{ fields: ["name", "email"] }`

---

#### `POST /auth/change-password` — Cambiar contraseña
**Requiere autenticación.**

**Body:**
```json
{
  "current_password": "contraseñaActual",
  "password": "nuevaContraseña123",
  "password_confirmation": "nuevaContraseña123"
}
```

**Response `200`:**
```json
{ "message": "Contraseña actualizada correctamente." }
```

**Response `422`** (contraseña actual incorrecta):
```json
{ "message": "La contraseña actual es incorrecta." }
```

**Reglas de negocio:**
- Valida `current_password` con `Hash::check()` antes de actualizar
- Revoca **todos los otros tokens Sanctum** activos (`WHERE id != currentTokenId`) — el token actual sigue válido
- No usa `password_reset_tokens` — flujo independiente al reset
- Registra en `audit_logs` con action `password_changed`

---

## 3. Personal Vault

El vault personal permite a cualquier usuario autenticado crear categorías y credenciales privadas, sin necesidad de pertenecer a una organización. Los usuarios enterprise también pueden tener vault personal independiente de su org.

### Cambio de schema

```
categories.user_id     BIGINT NULL FK → users(id) CASCADE
categories.organization_id  pasa a NULLABLE

credentials.user_id    BIGINT NULL FK → users(id) CASCADE
credentials.organization_id pasa a NULLABLE
```

**Invariante:** exactamente uno de `organization_id` o `user_id` es NOT NULL (CHECK constraint en ambas tablas).

### Categorías del Vault

#### `GET /vault/categories` — Listar categorías personales
**Requiere autenticación.**

**Response `200`:** paginado estándar `{ data, links, meta }`

---

#### `POST /vault/categories` — Crear categoría personal
**Requiere autenticación.**

**Body:**
```json
{
  "name": "Trabajo Personal",
  "description": "Mis cuentas de uso personal",
  "image_id": 3
}
```

**Response `201`:** `CategoryResource`

---

#### `GET /vault/categories/{category}` — Ver categoría
**Requiere autenticación. Solo el dueño.**

**Response `403`** si la categoría pertenece a otro usuario.

---

#### `PUT /vault/categories/{category}` — Editar categoría
**Requiere autenticación. Solo el dueño.**

**Body (todos opcionales):** `name`, `description`, `image_id`

---

#### `DELETE /vault/categories/{category}` — Eliminar categoría
**Requiere autenticación. Solo el dueño.**

**Response `200`:**
```json
{ "message": "Categoría eliminada." }
```

---

### Credenciales del Vault

#### `GET /vault/categories/{category}/credentials`
**Requiere autenticación. Solo el dueño de la categoría.**

**Query params:** `q` (busca en nombre/username), `type`

**Response `200`:** paginado estándar

---

#### `POST /vault/categories/{category}/credentials`
**Requiere autenticación. Solo el dueño de la categoría.**

**Body:**
```json
{
  "name": "Netflix",
  "username": "mi@email.com",
  "password": "secreto123",
  "notes": "Cuenta familiar",
  "type": "password",
  "image_id": 12
}
```

**Response `201`:** `CredentialResource`

---

#### `GET /vault/categories/{category}/credentials/{credential}`
**Requiere autenticación. Solo el dueño.**

Registra en `audit_logs` action `view`.

---

#### `PUT /vault/categories/{category}/credentials/{credential}`
**Requiere autenticación. Solo el dueño.**

**Body (todos opcionales):** `name`, `username`, `password`, `notes`, `type`, `image_id`

Registra en `audit_logs` action `update`.

---

#### `DELETE /vault/categories/{category}/credentials/{credential}`
**Requiere autenticación. Solo el dueño.**

**Response `200`:**
```json
{ "message": "Credencial eliminada." }
```

---

#### `GET /vault/categories/{category}/credentials/{credential}/reveal`
**Requiere autenticación. Solo el dueño.**

**Response `200`:**
```json
{
  "password": "secreto123",
  "notes": "Cuenta familiar"
}
```

Registra en `audit_logs` action `reveal_password`.

---

### Reglas de acceso del Vault

| Actor | Acceso |
|---|---|
| Dueño (`user_id = auth()->id()`) | CRUD completo |
| `org_admin` de cualquier org | ❌ Sin acceso |
| `sysadmin` | ❌ Sin acceso |
| Otro usuario autenticado | ❌ `403` |

---

## 4. Exportación de Credenciales

#### `GET /organizations/{organization}/credentials/export`
**Requiere autenticación. Throttle: 3 req/hora.**

**Query params:**
| Param | Valores | Default |
|---|---|---|
| `format` | `csv` | `csv` |

**Response `200`:** streaming de archivo CSV con header `Content-Disposition: attachment`.

**Columnas del CSV:**
```
id, name, username, password, type, category, division, notes
```

**Nota:** `password` está **descifrado** en el export.

**Reglas de negocio:**
- Solo `org_admin` (de esa organización) y `sysadmin` pueden exportar
- `org_user` recibe `403`
- `org_admin` de otra org recibe `403`
- Passwords descifrados en memoria durante el streaming — no se guarda ningún archivo en disco
- Registra en `audit_logs` action `export`, entity `Organization`, metadata `{ format: "csv" }`

**Response `403`:**
```json
{ "message": "No tienes permiso para exportar credenciales." }
```

---

## Acciones de audit_logs agregadas en Fase 5

| Action | Cuándo |
|---|---|
| `password_reset` | Usuario completa el flujo de reset de contraseña |
| `password_changed` | Usuario cambia su contraseña desde el perfil |

---

## Migraciones

| Archivo | Cambio |
|---|---|
| `2026_05_12_115739_add_password_reset_to_audit_logs_table` | Agrega `password_reset` al CHECK constraint |
| `2026_05_12_115740_add_profile_actions_to_audit_logs_table` | Agrega `password_changed` al CHECK constraint |
| `2026_05_12_115741_add_user_id_to_categories_table` | `user_id` nullable FK + `organization_id` nullable + CHECK scope |
| `2026_05_12_115742_add_user_id_to_credentials_table` | `user_id` nullable FK + `organization_id` nullable + CHECK scope |

---

## Archivos creados

| Archivo | Descripción |
|---|---|
| `app/Http/Controllers/Auth/PasswordResetController.php` | forgot + reset |
| `app/Http/Controllers/Auth/ProfileController.php` | update + changePassword |
| `app/Http/Controllers/VaultCategoryController.php` | CRUD categorías personales |
| `app/Http/Controllers/VaultCredentialController.php` | CRUD + reveal credenciales personales |
| `app/Http/Controllers/CredentialExportController.php` | Export CSV |
| `app/Http/Requests/Auth/ForgotPasswordRequest.php` | Validación forgot |
| `app/Http/Requests/Auth/ResetPasswordRequest.php` | Validación reset |
| `app/Http/Requests/Auth/UpdateProfileRequest.php` | Validación update profile |
| `app/Http/Requests/Auth/ChangePasswordRequest.php` | Validación change password |
| `app/Mail/PasswordResetMail.php` | Mailable reset |
| `app/Policies/VaultPolicy.php` | Policy vault (owner-only) |
| `app/Services/CredentialExportService.php` | Generador CSV streaming |
| `resources/views/emails/password-reset.blade.php` | Template email reset |

## Archivos modificados

| Archivo | Cambio |
|---|---|
| `app/Models/Category.php` | `user_id` en fillable + relación `user()` |
| `app/Models/Credential.php` | `user_id` en fillable + relación `user()` |
| `app/Services/CredentialService.php` | Método `createPersonal()` |
| `routes/api.php` | Rutas forgot/reset, profile, change-password, vault, export |
