# CyberPass Frontend — Registro diferido con 2FA obligatorio

**Fecha:** 2026-05-14  
**Motivo del cambio:** el backend ya no crea el usuario al enviar el formulario de registro. La cuenta solo se crea cuando el usuario completa correctamente el primer codigo OTP de Google Authenticator.

---

## Resumen ejecutivo

Antes:

```text
POST /auth/register
  -> backend creaba usuario
  -> backend devolvia token
  -> frontend navegaba a activar 2FA con cuenta ya existente
```

Ahora:

```text
POST /auth/register
  -> backend NO crea usuario
  -> backend devuelve registration_token y estado pendiente
  -> frontend navega a activar 2FA
  -> POST /auth/2fa/setup
  -> POST /auth/2fa/enable con OTP valido
  -> backend recien ahi crea usuario y devuelve token final
```

---

## Que debe cambiar en frontend

### 1. Ya no considerar el registro como alta exitosa

El submit de `/auth/register` ya no significa "cuenta creada".

El frontend debe tratarlo como:

```text
registro pendiente de verificacion 2FA
```

Acciones obligatorias:

- No mostrar mensaje final de exito tipo "cuenta creada" en el submit del formulario.
- No guardar sesion de usuario en ese paso.
- No llamar `/api/auth/me` despues de `/api/auth/register`.
- No asumir que ya existe un `user.id` en base de datos.

### 2. Guardar el `registration_token` de forma temporal

El backend devuelve `registration_token` en el body y tambien lo envía en cookie `HttpOnly`.

Recomendacion frontend:

- Guardarlo en un servicio en memoria mientras dure el flujo.
- No guardarlo en `localStorage` ni `sessionStorage`.
- En todas las llamadas de este flujo usar `withCredentials: true` si el frontend y backend estan en distinto origin.

### 3. La ruta `/auth/activar-2fa` ahora forma parte del registro

La pantalla de activacion 2FA ya no debe asumir que el usuario existe.

Debe soportar el modo:

```text
registro pendiente + QR + OTP inicial + creacion final de cuenta
```

Si se entra a `/auth/activar-2fa` sin `registration_token` valido:

- redirigir a `/auth/register`
- o mostrar estado de error con CTA para reiniciar registro

---

## Rutas frontend afectadas

## 1. `/auth/register`

Formulario actual:

- `name`
- `email`
- `password`
- `password_confirmation`

Nuevo comportamiento:

```text
submit register
  -> 202 Accepted
  -> guardar registration_token
  -> navegar a /auth/activar-2fa
```

## 2. `/auth/activar-2fa`

Nuevo comportamiento:

```text
onInit
  -> validar que exista registration_token pendiente
  -> pedir QR al backend
  -> mostrar QR
  -> recibir OTP del usuario
  -> enviar OTP al backend
  -> solo si responde 201: cuenta creada + sesion valida
```

---

## Flujo completo esperado

```text
Usuario llena form /auth/register
        |
        v
POST /api/auth/register
        |
        +--> 202 Accepted
              requires_2fa_setup = true
              registration_token = ...
              user = perfil pendiente
        |
        v
Frontend navega a /auth/activar-2fa
        |
        v
POST /api/auth/2fa/setup
Body: { registration_token }
        |
        +--> 200 OK
              secret
              qr_data_url
              qr_uri
              user
        |
        v
Usuario escanea QR y escribe OTP
        |
        v
POST /api/auth/2fa/enable
Body: { registration_token, otp }
        |
        +--> 201 Created
              user
              token
        |
        v
Ahora si existe el usuario en DB y la sesion es valida
```

---

## Endpoints y contratos JSON

## 1. Iniciar registro pendiente

### Request

```http
POST /api/auth/register
Content-Type: application/json
```

```json
{
  "name": "Juan Garcia",
  "email": "juan@empresa.com",
  "password": "secreto123",
  "password_confirmation": "secreto123"
}
```

### Response exitosa

```http
202 Accepted
```

```json
{
  "requires_2fa_setup": true,
  "registration_token": "f3b14f4f1c6f...",
  "user": {
    "name": "Juan Garcia",
    "email": "juan@empresa.com",
    "role": "org_user",
    "account_type": "enterprise",
    "organization": {
      "id": 8
    }
  }
}
```

Notas:

- No viene `token` de acceso.
- No viene `id` de usuario.
- No existe todavia fila en `users`.
- `role`, `account_type` y `organization` dependen del dominio del email.

### Que debe hacer frontend con esta respuesta

```text
1. leer registration_token
2. guardarlo de forma temporal
3. navegar a /auth/activar-2fa
4. no intentar login todavia
```

### Errores esperados

```http
422 Unprocessable Entity
```

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": [
      "The email has already been taken."
    ]
  }
}
```

---

## 2. Generar QR para el registro pendiente

### Request

```http
POST /api/auth/2fa/setup
Content-Type: application/json
```

```json
{
  "registration_token": "f3b14f4f1c6f..."
}
```

> Si el frontend usa cookie `HttpOnly` y `withCredentials: true`, el backend tambien puede resolver el token desde cookie. Aun asi, se recomienda enviar el `registration_token` en el body para que el flujo sea explicito.

### Response exitosa

```http
200 OK
```

```json
{
  "secret": "BASE32SECRETKEY",
  "qr_data_url": "data:image/png;base64,iVBORw0KGgoAAA...",
  "qr_uri": "otpauth://totp/CyberPass:juan@empresa.com?secret=BASE32SECRETKEY&issuer=CyberPass",
  "user": {
    "name": "Juan Garcia",
    "email": "juan@empresa.com",
    "role": "org_user",
    "account_type": "enterprise",
    "organization": {
      "id": 8
    }
  }
}
```

### Que debe hacer frontend con esta respuesta

- Renderizar el QR usando `qr_data_url`.
- Mostrar el email pendiente en pantalla.
- Mostrar campo OTP de 6 digitos.
- No marcar la cuenta como creada.

### Errores esperados

```http
422 Unprocessable Entity
```

```json
{
  "message": "Registro pendiente invalido o expirado.",
  "errors": {
    "registration_token": [
      "Registro pendiente invalido o expirado."
    ]
  }
}
```

---

## 3. Confirmar OTP y crear la cuenta

### Request

```http
POST /api/auth/2fa/enable
Content-Type: application/json
```

```json
{
  "registration_token": "f3b14f4f1c6f...",
  "otp": "123456"
}
```

### Response exitosa

```http
201 Created
```

```json
{
  "user": {
    "id": 14,
    "name": "Juan Garcia",
    "email": "juan@empresa.com",
    "role": "org_user",
    "account_type": "enterprise",
    "two_factor_enabled": true,
    "is_active": true,
    "last_login_at": "2026-05-14T22:15:00.000000Z",
    "organization": {
      "id": 8,
      "name": "Acme Corp",
      "slug": "acme-corp"
    },
    "vault_enabled": true
  },
  "token": "3|pQrStU..."
}
```

### Que debe hacer frontend con esta respuesta

```text
1. ahora si considerar registro exitoso
2. guardar token o continuar con cookie de sesion segun estrategia actual
3. limpiar registration_token de memoria
4. navegar al destino post-login de la app
```

### Error OTP invalido

```http
422 Unprocessable Entity
```

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "otp": [
      "Codigo OTP incorrecto."
    ]
  }
}
```

Comportamiento frontend:

- Mantener al usuario en la pantalla de OTP.
- Mostrar error inline.
- No crear estado de sesion.
- No redirigir.

### Error setup expirado o no iniciado

```http
422 Unprocessable Entity
```

```json
{
  "message": "El setup de 2FA expiro o no fue iniciado. Vuelve a ejecutar el setup."
}
```

Comportamiento frontend:

- Reintentar `POST /api/auth/2fa/setup` si todavia existe `registration_token`.
- Si no hay token o tambien expiro, reiniciar registro.

### Error email ya tomado al momento de confirmar

```http
422 Unprocessable Entity
```

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": [
      "Ya existe una cuenta registrada con este correo."
    ]
  }
}
```

Comportamiento frontend:

- Mostrar error global.
- Redirigir a login o a register segun UX definida.

---

## Estados UI recomendados

## Pantalla `/auth/register`

```text
idle -> submitting -> pending_2fa_redirect
             |
             +-> validation_error
```

Reglas:

- El boton no debe quedar con label final tipo "Cuenta creada".
- Tras `202`, cambiar inmediatamente al paso 2FA.

## Pantalla `/auth/activar-2fa`

```text
loading_token -> requesting_qr -> qr_ready -> verifying_otp -> success
       |               |               |             |
       |               |               |             +-> otp_error
       |               |               +-> expired
       |               +-> expired
       +-> missing_token
```

### Estado `missing_token`

- No hay `registration_token` en memoria ni cookie utilizable.
- Redirigir a `/auth/register`.

### Estado `expired`

- Mostrar mensaje: el registro pendiente expiro.
- CTA: volver a `/auth/register`.

### Estado `success`

- Solo aqui mostrar mensaje de cuenta activada.
- Solo aqui navegar a dashboard o flujo onboarding.

---

## Reglas de negocio que el frontend debe respetar

1. `POST /api/auth/register` ya no crea usuario.
2. El usuario solo existe cuando `POST /api/auth/2fa/enable` responde `201`.
3. Antes de ese `201`, no hay `user.id`, no hay sesion valida y no debe intentarse entrar a la app.
4. El `registration_token` es temporal y expira.
5. Si el usuario abandona el flujo antes del OTP valido, no debe quedar cuenta creada.
6. Un OTP invalido no debe crear cuenta.
7. El frontend no debe persistir `registration_token` en storage local.
8. El frontend no debe mostrar exito de registro antes de la confirmacion 2FA.
9. La pantalla de activar 2FA debe soportar tanto:
   - registro pendiente sin usuario persistido;
   - usuario autenticado que activa 2FA luego.
10. El flujo de login normal no cambia.
11. El flujo Google OAuth no forma parte de este cambio; sigue su contrato actual.

---

## Pseudocodigo sugerido

```ts
async function submitRegister(form: RegisterPayload) {
  const response = await api.post('/api/auth/register', form, { withCredentials: true });

  pendingRegistrationService.set({
    token: response.data.registration_token,
    user: response.data.user,
  });

  router.navigate(['/auth/activar-2fa']);
}

async function initActivate2fa() {
  const pending = pendingRegistrationService.get();

  if (!pending?.token) {
    router.navigate(['/auth/register']);
    return;
  }

  const response = await api.post('/api/auth/2fa/setup', {
    registration_token: pending.token,
  }, { withCredentials: true });

  state.qrDataUrl = response.data.qr_data_url;
  state.secret = response.data.secret;
  state.user = response.data.user;
}

async function confirmOtp(otp: string) {
  const pending = pendingRegistrationService.get();

  const response = await api.post('/api/auth/2fa/enable', {
    registration_token: pending.token,
    otp,
  }, { withCredentials: true });

  authSession.start(response.data.user, response.data.token);
  pendingRegistrationService.clear();
  router.navigate(['/dashboard']);
}
```

---

## Checklist para frontend

- Ajustar handler de submit en `/auth/register`
- Dejar de esperar `201` con `token` en registro
- Soportar `202` con `registration_token`
- Crear estado temporal de registro pendiente
- Adaptar `/auth/activar-2fa` para usar `registration_token`
- Crear manejo de `422` para token vencido o OTP invalido
- Mostrar mensaje de exito solo al recibir `201` de `/auth/2fa/enable`
- Limpiar estado temporal cuando el flujo termina o expira
