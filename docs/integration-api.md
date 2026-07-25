# API de Integración — Referencia

**Recurso:** `PersonalAccessToken` (tipo `integration`) + superficie `/api/integration/*`
**Tabla:** `personal_access_tokens` (columna `type`)
**Fecha:** 2026-07-25 (versión inicial — consumida por Axis)

---

## Propósito

Permite que una aplicación externa —hoy **Axis**— cree, actualice y consulte credenciales **en nombre de una persona concreta**, servidor a servidor, sin compartir su contraseña ni su segundo factor.

La persona emite un *token de integración* desde su perfil de CyberPass y lo pega en la otra aplicación. Ese token:

- vive mucho más que una sesión humana (por defecto 365 días, tope 730),
- lleva **permisos acotados**, no el comodín `*`,
- solo alcanza `/api/integration/*` — el resto del API le está vedado,
- es revocable en cualquier momento por su dueño, sin tocar su contraseña.

> **CyberPass no es zero-knowledge.** `EncryptionService` cifra con AES-256-GCM usando una llave global del servidor, así que el API recibe las contraseñas **en texto plano** y las cifra él. Esto es lo que hace posible la integración sin master password, y es también la razón de que **todo el tráfico deba ir por HTTPS**.

---

## Modelo de confianza

```
Persona (sesión + TOTP)
   │
   ├─ emite ──► Token de integración  ──► solo /api/integration/*
   │                                        (permisos acotados)
   └─ usa ────► Token de sesión       ──► todo el resto del API
                                           (20 min, comodín '*')
```

Reglas que sostienen esa separación:

| Regla | Dónde |
|---|---|
| Un token de integración no puede tocar ninguna ruta preexistente | `EnsureSessionToken` |
| Un token de sesión no puede usar la superficie de integración | `EnsureIntegrationTokenAbility` |
| Un token de integración ignora el tope global de 20 min | `AppServiceProvider::registerIntegrationTokenLifetime()` |
| Iniciar sesión no destruye los tokens de integración | `AuthService::issueSingleSessionToken()` |
| Un token de integración vivo no bloquea el login con 409 | `AuthService::findActiveToken()` |
| Cambiar la contraseña no revoca la integración | `ProfileController::changePassword()` |
| Desactivar el 2FA sí la corta | `EnsureTwoFactorSetup` en el grupo |

---

## Permisos

| Ability | Permite |
|---|---|
| `integration:me.read` | `GET /integration/me` |
| `integration:categories.read` | `GET /integration/categories` |
| `integration:credentials.create` | Crear credenciales |
| `integration:credentials.read` | Ver metadatos de una credencial |
| `integration:credentials.update` | Editar una credencial |
| `integration:credentials.reveal` | **Leer el secreto en claro** |
| `integration:credentials.delete` | Borrar una credencial |

Por defecto se emiten todos **menos `delete`**. `reveal` es el permiso sensible: sin él la aplicación externa queda de solo escritura, que es la configuración más segura si no necesita mostrar contraseñas.

Nunca se puede emitir `*`: la validación lo rechaza, y el middleware comprueba con `in_array` en vez de `can()` precisamente para que un comodín no saltara la comprobación.

---

## Emisión (sesión humana + TOTP)

### `POST /api/auth/integration-tokens`

Requiere sesión activa, 2FA configurado y un **OTP en vivo**. Limitado a 5 por hora.

```json
{
  "name": "Axis producción",
  "abilities": ["integration:me.read", "integration:categories.read", "integration:credentials.create"],
  "expires_in_days": 365,
  "otp": "123456"
}
```

**201**

```json
{
  "id": 42,
  "name": "Axis producción",
  "abilities": ["integration:me.read", "..."],
  "expires_at": "2027-07-25T14:03:00.000000Z",
  "last_used_at": null,
  "created_at": "2026-07-25T14:03:00.000000Z",
  "token": "42|cyberpass_XXXXXXXXXXXXXXXXXXXX"
}
```

> `token` aparece **una sola vez**. En la base de datos solo queda su hash: si se pierde, hay que emitir otro.

Errores: `422` si el OTP es incorrecto, si una ability no está en la lista blanca, si `expires_in_days` supera el tope, o si se alcanzó el máximo de tokens activos.

### `GET /api/auth/integration-tokens`

Lista los tokens de integración del usuario. **Nunca** incluye el valor del token.

### `DELETE /api/auth/integration-tokens/{token}`

Revoca uno. Responde `404` si el token es de otra persona **o** si es un token de sesión — para cerrar sesión está `/api/auth/logout`.

### `DELETE /api/auth/integration-tokens`

Botón de pánico: revoca todos los de integración de una vez, sin tocar la sesión.

> Los tokens vencidos desaparecen del listado: `routes/console.php` programa `sanctum:prune-expired` cada cinco minutos.

---

## Consumo (token de integración)

Cabecera: `Authorization: Bearer 42|cyberpass_...`
Opcional: `X-Integration-Client: axis` — solo enriquece la auditoría, y se valida contra `config('integrations.clients')` para que no se pueda falsear.

### `GET /api/integration/me`

Identidad a la que apunta el token. Es lo que conviene llamar justo después de que alguien lo pegue, para confirmar que es suyo.

```json
{
  "user": { "id": 7, "name": "Juan Suárez", "email": "juan@cybertec.com.co", "role": "org_user", "account_type": "enterprise" },
  "organization": { "id": 3, "name": "Cybertec", "slug": "cybertec" },
  "token": { "id": 42, "name": "Axis producción", "abilities": ["..."], "expires_at": "...", "last_used_at": "..." }
}
```

### `GET /api/integration/categories`

Bóveda personal y categorías de la organización **en una sola respuesta, sin paginar** (tope de 500), con discriminador `scope`. El cliente no necesita conocer las dos familias de rutas que usa el SPA.

```json
{
  "data": [
    { "id": 12, "name": "Mis cosas", "description": null, "scope": "personal",
      "division_id": null, "can_create_credential": true },
    { "id": 30, "name": "Infra", "description": "Servidores", "scope": "organization",
      "organization": { "id": 3, "name": "Cybertec" }, "division_id": 4, "can_create_credential": true }
  ]
}
```

`can_create_credential` lo calcula el mismo resolver que usa la escritura, así que descubrimiento y escritura no pueden discrepar.

### `POST /api/integration/credentials`

```json
{
  "scope": "personal",
  "category_id": 12,
  "name": "Correo corporativo",
  "username": "juan@cybertec.com.co",
  "password": "texto-plano",
  "url": "https://mail.example.com",
  "type": "password"
}
```

- `scope` es **obligatorio y no derivado**: si no coincide con el ámbito real de la categoría se responde `422` sin escribir nada. Es lo que impide que un id equivocado meta un secreto corporativo dentro de la bóveda personal de alguien.
- `url` debe ser una URL válida con esquema. `www.ejemplo.com` da `422` — hay que normalizarla antes de enviarla.
- La respuesta **nunca** incluye `password`.

**201** → `IntegrationCredentialResource` con `{ id, name, username, url, type, category: { id, name, scope }, image_id, created_at, updated_at }`.

### `GET /api/integration/credentials/{credential}`

Metadatos. Nunca el secreto.

### `PUT /api/integration/credentials/{credential}`

Campos `sometimes`: `name`, `username`, `password`, `url`, `type`, `image_id`.

- Enviar `username: null` **sí** limpia el valor.
- **No se puede mover una credencial de categoría**: `CredentialService::update()` ignora `category_id`. Cambiar de destino exige crear otra y borrar la anterior.
- Cada llamada escribe una fila en `credential_versions`, incluso si no cambia nada. Conviene no disparar actualizaciones vacías.

### `GET /api/integration/credentials/{credential}/reveal`

El **único** endpoint que devuelve el secreto.

```json
{ "password": "texto-plano", "url": "https://mail.example.com" }
```

Responde con `Cache-Control: no-store, private` y `Referrer-Policy: no-referrer`.

> Solo revela credenciales **creadas por el dueño del token**. Es más estricto que `CredentialPolicy::view()`, que alcanza a toda la organización: sin esa restricción, la integración sería un proxy de lectura de la bóveda entera.

### `DELETE /api/integration/credentials/{credential}`

Borrado lógico (`SoftDeletes`). Requiere la ability `delete`, que no se emite por defecto.

---

## Códigos de error

| Código | `error_code` | Significado |
|---|---|---|
| 401 | — | Token inválido, vencido o revocado |
| 403 | `session_token_required` | Se usó un token de integración en una ruta que no le corresponde |
| 403 | `integration_token_required` | Se usó un token de sesión en `/api/integration/*` |
| 403 | `integration_ability_missing` | Falta el permiso; el campo `ability` dice cuál |
| 403 | `two_factor_setup_required` | El dueño del token desactivó su 2FA |
| 404 | — | No existe **o** no es suyo (deliberadamente indistinguible) |
| 422 | — | Datos inválidos, o `scope` que no corresponde a la categoría |
| 429 | — | Se superó el límite de peticiones |

---

## Límites de peticiones

Se limita **por id de token**, no por IP: todo el tráfico llega desde el mismo servidor para muchos usuarios distintos.

| Grupo | Límite |
|---|---|
| `/api/integration/*` | 60 por minuto |
| Escrituras (`POST`, `PUT`, `DELETE`) | 30 por minuto |
| `/reveal` | 10 por minuto **y** 300 por día |
| Emisión de tokens | 5 por hora |

---

## Auditoría

Toda acción de integración escribe en `audit_logs` con las acciones ya existentes (`create`, `update`, `view`, `reveal_password`, `delete`) y la procedencia en `metadata`:

```json
{
  "source": "integration",
  "client": "axis",
  "token_id": 42,
  "token_name": "Axis producción",
  "ability": "integration:credentials.reveal",
  "scope": "personal",
  "category_id": 12
}
```

Para filtrar en Postgres: `WHERE metadata->>'source' = 'integration'`.

> La acción queda atribuida a la persona dueña del token, que es lo correcto. `metadata.scope` es el ámbito autoritativo: `organization_id` refleja la organización del usuario aunque la credencial sea de su bóveda personal, igual que ya ocurre con `VaultCredentialController`.

---

## Notas de operación

- `SANCTUM_TOKEN_EXPIRATION` **no debe ponerse en `null`**. Nueve sitios hacen `(int) config('sanctum.expiration', 20)` y `(int) null === 0` → sesiones humanas de un minuto. Los tokens de integración esquivan ese tope por diseño, mediante `Sanctum::authenticateAccessTokensUsing()`.
- `laravel/sanctum` está fijado a `^4.3` porque el mecanismo depende de la semántica de `Guard::isValidAccessToken()`. El test *"outlives the global sanctum expiration cap"* es el canario ante una actualización.
- **Los tests de integración no pueden usar `Sanctum::actingAs()`**: monta un mock que no pasa por el Guard, así que el callback nunca corre y el test pasaría sin comprobar nada. Hay que emitir un token real (`integrationToken()` en `tests/Pest.php`) y usar `withBearer()`.
