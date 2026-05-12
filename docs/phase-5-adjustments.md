# CyberPass API — Fase 5: Ajustes post-implementación

## Ajuste 1 — `POST /auth/change-password`: OTP reemplaza contraseña actual cuando 2FA está activo

### Endpoint afectado

```
POST /auth/change-password
```

### Motivación

Cuando el usuario tiene 2FA activo, ya demostró que controla el dispositivo físico vinculado a su cuenta. Pedir también la contraseña actual en ese contexto es redundante. En cambio, el OTP de 6 dígitos sirve como segundo factor de verificación de identidad antes de cambiar la credencial más sensible de la cuenta.

---

### Comportamiento por caso

#### Caso A — Usuario **con** 2FA activo (`two_factor_enabled = true`)

**Body requerido:**
```json
{
  "otp": "123456",
  "password": "nuevaContraseña123",
  "password_confirmation": "nuevaContraseña123"
}
```

| Campo | Tipo | Reglas |
|---|---|---|
| `otp` | string | requerido, exactamente 6 dígitos numéricos |
| `password` | string | requerido, mín. 8 caracteres |
| `password_confirmation` | string | requerido, debe coincidir con `password` |

**Validación del OTP:** `Google2FA::verifyKey($user->two_factor_secret, $otp)` — ventana de tiempo estándar TOTP (±30s).

**Response `422`** (OTP incorrecto):
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "otp": ["Código OTP incorrecto."]
  }
}
```

---

#### Caso B — Usuario **sin** 2FA activo (`two_factor_enabled = false`)

**Body requerido:** (sin cambio respecto al diseño original)
```json
{
  "current_password": "contraseñaActual",
  "password": "nuevaContraseña123",
  "password_confirmation": "nuevaContraseña123"
}
```

| Campo | Tipo | Reglas |
|---|---|---|
| `current_password` | string | requerido |
| `password` | string | requerido, mín. 8 caracteres |
| `password_confirmation` | string | requerido, debe coincidir con `password` |

**Response `422`** (contraseña actual incorrecta):
```json
{ "message": "La contraseña actual es incorrecta." }
```

---

### Response `200` (ambos casos)

```json
{ "message": "Contraseña actualizada correctamente." }
```

---

### Lógica de negocio completa

1. **Determinar modo de verificación** según `user.two_factor_enabled`
2. **Si 2FA activo:** validar OTP con `Google2FA::verifyKey()` — si falla → `ValidationException` con campo `otp`
3. **Si 2FA inactivo:** validar `current_password` con `Hash::check()` — si falla → `422`
4. Actualizar password con `Hash::make($data['password'])`
5. Revocar todos los otros tokens Sanctum del usuario (`WHERE id != currentTokenId`) — el token de la sesión actual **sigue válido**
6. Registrar en `audit_logs`: action `password_changed`, entity `User`

---

### Archivos modificados

| Archivo | Cambio |
|---|---|
| `app/Http/Requests/Auth/ChangePasswordRequest.php` | `rules()` dinámico: si `two_factor_enabled` → exige `otp`; si no → exige `current_password` |
| `app/Http/Controllers/Auth/ProfileController.php` | Inyecta `Google2FA`; `changePassword()` bifurca verificación según estado 2FA |

---

## Ajuste 2 — Token de reset de un solo uso: pre-validación al cargar la ruta

### Problema

El backend elimina correctamente el token de `password_reset_tokens` después de un reset exitoso. Sin embargo, el frontend mostraba el formulario sin verificar el token primero — solo lo validaba al hacer submit. Esto permitía abrir la ruta con un token ya usado y ver el formulario (aunque el submit fallara).

### Solución

Nuevo endpoint de verificación liviana + flujo de pre-validación en el frontend al cargar la ruta.

### Endpoint nuevo

```
GET /auth/verify-reset-token?token={token}&email={email}
```

**Sin autenticación. Throttle: 10 req/min.**

**Response `200`** (token válido y no expirado):
```json
{ "valid": true }
```

**Response `422`** (token inexistente, hash no coincide, o TTL expirado):
```json
{ "valid": false }
```

```json
{ "valid": false, "reason": "expired" }
```

> Este endpoint **no consume** el token — solo lo verifica. El token se elimina únicamente en `POST /auth/reset-password`.

### Flujo actualizado del frontend (`/auth/reset-password`)

```
URL cargada
    ↓
Leer ?token y ?email — si faltan → redirect /auth/login
    ↓
GET /auth/verify-reset-token?token=...&email=...
    ↓
200 valid:true  →  mostrar formulario
422 valid:false →  mostrar estado "error" directamente (sin formulario)
```

El usuario con un link ya usado ve el estado `error` inmediatamente, sin poder ingresar una nueva contraseña.

### Archivos modificados

| Archivo | Cambio |
|---|---|
| `app/Http/Controllers/Auth/PasswordResetController.php` | Método `verify()` agregado |
| `routes/api.php` | `GET auth/verify-reset-token` registrado |

---

## Ajuste 3 — Botón del email no funciona en Gmail/Outlook

### Problema

La plantilla Blade usaba `<style>` en el `<head>` con una clase CSS `.btn`. Gmail elimina todos los bloques `<style>` del `<head>` al procesar emails entrantes, dejando el botón sin estilos y en algunos clientes sin ser clickeable correctamente.

### Solución

Reescritura del template con:
- **Todos los estilos inline** — ninguna regla en `<style>` o clases CSS
- **Estructura de tabla** para el botón CTA — único método compatible con Outlook (que usa Word para renderizar HTML)
- `target="_blank"` en el enlace
- URL del reset también expuesta como texto plano debajo del botón como fallback

### Archivo modificado

| Archivo | Cambio |
|---|---|
| `resources/views/emails/password-reset.blade.php` | Reescritura completa con inline styles y botón table-based |
