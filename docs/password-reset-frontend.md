# CyberPass Frontend — Ruta `/auth/reset-password`

## Contexto

El backend envía un email con un link de la forma:
```
https://secure.cyberteconline.com/auth/reset-password?token={token}&email={email}
```

El frontend debe manejar esta ruta, presentar el formulario de nueva contraseña y llamar al endpoint de reset.

---

## Ruta Angular

```
/auth/reset-password
```

- **Guard:** ninguno — ruta pública
- **Módulo:** `AuthModule` (mismo que login/register)
- **Query params requeridos:** `token`, `email`
- Si alguno de los dos falta al cargar → redirigir a `/auth/login` inmediatamente

## Flujo de carga obligatorio

Al entrar a la ruta, **antes de mostrar el formulario**, el frontend debe pre-validar el token:

```
1. Leer ?token y ?email  →  si faltan: redirect /auth/login
2. GET /api/auth/verify-reset-token?token=...&email=...
   - 200 { valid: true }  →  mostrar formulario
   - 422 { valid: false } →  mostrar estado "error" directamente
```

Esto garantiza que un link ya usado muestra el estado de error de inmediato, sin dar acceso al formulario.

---

## Estados del componente

```
reading-params → validating-token → form → submitting → success
                        ↓                        ↓
                      error                    error
```

| Estado | Descripción |
|---|---|
| `reading-params` | Leyendo `?token` y `?email` de la URL |
| `validating-token` | `GET /auth/verify-reset-token` en curso — mostrar spinner |
| `form` | Token válido — formulario de nueva contraseña visible |
| `submitting` | Submit en curso — spinner, botón deshabilitado |
| `success` | Reset exitoso — botón a login |
| `error` | Token inválido, expirado o ya usado — sin formulario, link a forgot-password |

---

## Formulario

### Campos

| Campo | Tipo | Validación |
|---|---|---|
| `password` | `<input type="password">` | requerido, mín. 8 caracteres |
| `password_confirmation` | `<input type="password">` | requerido, debe coincidir con `password` |

- Validar igualdad de contraseñas en el frontend antes de enviar
- Mostrar indicador de fortaleza de contraseña (opcional pero recomendado)
- Botón de toggle show/hide para ambos campos

### UI mínima esperada

```
┌─────────────────────────────────────────┐
│  🔒  Restablecer contraseña             │
│                                         │
│  Nueva contraseña                       │
│  ┌───────────────────────────────────┐  │
│  │ ••••••••                      👁  │  │
│  └───────────────────────────────────┘  │
│                                         │
│  Confirmar nueva contraseña             │
│  ┌───────────────────────────────────┐  │
│  │ ••••••••                      👁  │  │
│  └───────────────────────────────────┘  │
│                                         │
│  ┌───────────────────────────────────┐  │
│  │      Restablecer contraseña       │  │
│  └───────────────────────────────────┘  │
└─────────────────────────────────────────┘
```

---

## Llamada al API

### Request

```
POST /api/auth/reset-password
Content-Type: application/json
```

```json
{
  "token": "<valor del query param ?token>",
  "email": "<valor del query param ?email>",
  "password": "nuevaContraseña123",
  "password_confirmation": "nuevaContraseña123"
}
```

> El `token` y `email` se leen de los query params al cargar la ruta — el usuario nunca los ve ni los edita.

### Respuestas posibles

#### `200` — Éxito
```json
{ "message": "Contraseña restablecida correctamente. Ya puedes iniciar sesión." }
```
→ Mostrar estado `success`

#### `422` — Token inválido, expirado o email no encontrado
```json
{ "message": "Token inválido o expirado." }
```
→ Mostrar estado `error`

#### `422` — Validación de campos
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "password": ["The password field must be at least 8 characters."]
  }
}
```
→ Mostrar errores inline en los campos correspondientes

---

## Estado `success`

```
┌─────────────────────────────────────────┐
│  ✅  ¡Contraseña restablecida!          │
│                                         │
│  Tu contraseña fue actualizada          │
│  correctamente. Ya puedes iniciar       │
│  sesión con tu nueva contraseña.        │
│                                         │
│  ┌───────────────────────────────────┐  │
│  │          Ir al login              │  │
│  └───────────────────────────────────┘  │
└─────────────────────────────────────────┘
```

- Botón "Ir al login" → `router.navigate(['/auth/login'])`
- No mostrar el token ni el email en ningún momento

---

## Estado `error`

```
┌─────────────────────────────────────────┐
│  ❌  Link inválido o expirado           │
│                                         │
│  Este enlace de recuperación ya no      │
│  es válido. Puede haber expirado        │
│  (válido por 60 minutos) o ya fue       │
│  usado.                                 │
│                                         │
│  ┌───────────────────────────────────┐  │
│  │    Solicitar nuevo enlace         │  │
│  └───────────────────────────────────┘  │
└─────────────────────────────────────────┘
```

- Botón "Solicitar nuevo enlace" → `router.navigate(['/auth/forgot-password'])`
- Mensaje genérico — no revelar si el token expiró, fue usado, o el email no existe

---

## Ruta complementaria — `/auth/forgot-password`

Formulario previo al reset donde el usuario ingresa su email.

### Campos

| Campo | Tipo | Validación |
|---|---|---|
| `email` | `<input type="email">` | requerido, formato email válido |

### Request

```
POST /api/auth/forgot-password
Content-Type: application/json
```

```json
{ "email": "usuario@empresa.com" }
```

### Response (siempre `200`)

```json
{ "message": "Si el correo está registrado, recibirás un enlace para restablecer tu contraseña." }
```

> **Siempre mostrar el mismo mensaje de confirmación** independientemente de si el email existe o no — el backend no revela esta información y el frontend tampoco debe intentarlo.

### UI

```
┌─────────────────────────────────────────┐
│  🔑  Recuperar acceso                   │
│                                         │
│  Ingresa tu email y te enviaremos       │
│  un enlace para restablecer tu          │
│  contraseña.                            │
│                                         │
│  Email                                  │
│  ┌───────────────────────────────────┐  │
│  │ usuario@empresa.com               │  │
│  └───────────────────────────────────┘  │
│                                         │
│  ┌───────────────────────────────────┐  │
│  │      Enviar enlace                │  │
│  └───────────────────────────────────┘  │
│                                         │
│  ← Volver al login                      │
└─────────────────────────────────────────┘
```

**Estado post-envío (sin importar resultado):**
```
┌─────────────────────────────────────────┐
│  📧  Revisa tu correo                   │
│                                         │
│  Si el correo está registrado,          │
│  recibirás un enlace en los             │
│  próximos minutos. El enlace            │
│  expira en 60 minutos.                  │
│                                         │
│  ← Volver al login                      │
└─────────────────────────────────────────┘
```

---

## Notas de seguridad para el frontend

- **No cachear** el token ni el email en `localStorage` o `sessionStorage`
- El link es de **un solo uso** — una vez usado, el backend elimina el token
- TTL: **60 minutos** — mostrar este dato en el estado de error para orientar al usuario
- Después de un reset exitoso, **todos los tokens Sanctum anteriores del usuario quedan inválidos** — si el usuario tenía otras sesiones abiertas en otros dispositivos, serán desconectadas
