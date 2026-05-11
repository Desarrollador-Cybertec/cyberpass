# Shared Link — Parche Frontend Angular

**Fecha:** 2026-05-11  
**Contexto:** El backend genera `share_url` apuntando al frontend. Angular necesita la ruta y el componente para manejarla.

---

## Ruta a registrar

```
/shared/:token
```

Esta ruta es **pública** — no requiere guard de autenticación. Cualquier persona con el link puede abrirla.

---

## Flujo de trabajo completo

```
Usuario abre  /shared/:token
       │
       ▼
GET /api/shared/{token}
       │
       ├─ 404 ──────────────────────────────────► Estado: INVÁLIDO
       │                                           Mostrar pantalla de error (ver más abajo)
       │
       └─ 200
              │
              ├─ is_valid = false ───────────────► Estado: EXPIRADO / AGOTADO
              │                                    Mostrar pantalla de error (ver más abajo)
              │
              └─ is_valid = true
                     │
                     ├─ requires_pin = false ────► Llamar claim directo (sin form)
                     │
                     └─ requires_pin = true ─────► Mostrar formulario de PIN
                                                          │
                                                          ▼
                                              POST /api/shared/{token}/claim
                                              Body: { pin }
                                                          │
                                                    ├─ 403 ──► "PIN incorrecto"
                                                    │          (mantener form, permitir reintento)
                                                    │
                                                    └─ 200 ──► Mostrar tarjeta de credencial
```

---

## Calls a la API

### 1. Consultar metadata del link

```
GET {API_URL}/api/shared/{token}
Sin Authorization header
```

**Respuesta `200` (link válido):**

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

**Respuesta `404`:** link inválido, expirado, revocado o agotado.

---

### 2. Reclamar el link

```
POST {API_URL}/api/shared/{token}/claim
Content-Type: application/json
Sin Authorization header

{ "pin": "7294" }   // omitir si requires_pin = false
```

**Respuesta `200` (éxito):**

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

**Respuesta `403`:** PIN incorrecto — mostrar error en el campo, no navegar.  
**Respuesta `404`:** el link expiró entre el GET y el POST — mostrar pantalla de error.

---

## Qué mostrar en cada estado

### Estado: formulario de PIN

Pantalla simple centrada, sin navbar ni sidebar (página pública):

- Logo de CyberPass arriba
- Nombre de la credencial (viene del GET): `credential.name`
- Tipo (viene del GET): `credential.type`
- Campo de PIN (numérico o alfanumérico, max 16 chars)
- Botón "Acceder"
- Si PIN incorrecto → mensaje de error inline, el campo se limpia

---

### Estado: tarjeta de credencial (tras claim exitoso)

Mostrar la **misma tarjeta visual que se usa dentro de la app**, con:

| Campo | Fuente | Notas |
|-------|--------|-------|
| Imagen / ícono | `credential.image` (si existe) | Mostrar placeholder si no hay imagen |
| Nombre | `credential.name` | |
| Tipo | `credential.type` | Chip o badge |
| Usuario | `credential.username` | Con botón de copiar |
| Contraseña | `credential.password` | Oculta por defecto, toggle para revelar + botón copiar |
| Notas | `credential.notes` | Solo si no es `null` |

**Restricciones importantes:**

- No mostrar ningún elemento de navegación (navbar, sidebar, menú)
- No mostrar el ID de la credencial ni de la organización
- No hay botón de editar ni eliminar
- Si `max_uses` no es null: mostrar "Uso X de Y" con `token.use_count` y `token.max_uses`
- Si `expires_at` no es null: mostrar "Expira el {fecha formateada}"

---

### Estado: inválido / expirado

Pantalla de error centrada, misma estructura que el formulario de PIN:

- Logo de CyberPass
- Ícono de candado cerrado o reloj
- Título: **"Este link ya no está disponible"**
- Descripción: _"El link que intentas abrir ha expirado, fue revocado o alcanzó su límite de usos. Contacta a quien te lo compartió para obtener uno nuevo."_
- Sin botón de reintentar (el link realmente no sirve más)

> **Importante:** no distinguir entre "expirado", "revocado" o "agotado" — siempre mostrar el mismo mensaje genérico. No filtrar información sobre el motivo.

---

## Ciclo de vida que el frontend debe respetar

```
Link generado → válido por N usos / hasta fecha X
     │
     ├─ Cada claim exitoso: use_count++
     │       └─ Al llegar a max_uses: próximo GET devuelve 404
     │
     ├─ Al pasar expires_at: GET devuelve 404
     │
     └─ Si fue revocado: GET devuelve 404
```

Una vez que el GET devuelve `404` o `is_valid = false`, **no hay forma de recuperar el link**. El componente no debe reintentar — debe mostrar la pantalla de error permanentemente.

---

## Componente Angular sugerido

```
shared-credential/
  ├── shared-credential.component.ts
  ├── shared-credential.component.html
  └── shared-credential.component.scss

Estados internos del componente:
  loading     → spinner mientras se hace el GET inicial
  pin_required → mostrar formulario de PIN
  claiming    → spinner mientras se hace el POST /claim
  revealed    → mostrar tarjeta de credencial
  invalid     → mostrar pantalla de error
```

---

## Archivos del backend relacionados

| Archivo | Descripción |
|---------|-------------|
| `app/Http/Controllers/PublicTokenController.php` | `info()` (GET) y `claim()` (POST) |
| `app/Services/SharedAccessTokenService.php` | `info()` y `claim()` |
| `routes/api.php` | `GET /api/shared/{token}` y `POST /api/shared/{token}/claim` |
