# Shared Token — Especificación de expiración

## Contexto del cambio

Actualmente `expires_at` acepta una fecha/hora absoluta (`YYYY-MM-DD HH:mm:ss`) o puede omitirse.
El nuevo requerimiento introduce un segundo modo: **expiración relativa** (en horas o minutos desde ahora),
y hace que **algún tipo de expiración sea obligatoria**.

---

## Reglas de negocio

| # | Regla |
|---|-------|
| 1 | `expires_at` ya **no es opcional**. El cliente DEBE enviar exactamente uno de los dos modos. |
| 2 | **Modo absoluto** (`expires_at`): fecha y hora futura en formato ISO-8601. Se usa cuando el token debe vencer en un día/hora concreto (ej. mañana). |
| 3 | **Modo relativo** (`expires_in`): objeto con `unit` + `value`. Se usa cuando el token debe vencer en X minutos/horas desde el momento de la creación. |
| 4 | Enviar ambos campos (`expires_at` + `expires_in`) es un error de validación. |
| 5 | Rango permitido para `expires_in.unit = "minutes"`: 1 – 60. |
| 6 | Rango permitido para `expires_in.unit = "hours"`: 1 – 12. |
| 7 | La fecha calculada internamente siempre debe ser **posterior a `now()`**. |
| 8 | El backend convierte `expires_in` a un `expires_at` absoluto antes de persistir. El campo almacenado sigue siendo `expires_at`. |

---

## Endpoints afectados

### `POST /api/organizations/{org}/credentials/{credential}/tokens`

Crea un nuevo shared-access token para una credencial.

#### Datos entrantes — Modo absoluto

```json
{
  "expires_at": "2026-05-13T10:00:00Z",
  "max_uses": 5,
  "pin": "1234"
}
```

#### Datos entrantes — Modo relativo (minutos)

```json
{
  "expires_in": {
    "unit": "minutes",
    "value": 30
  },
  "max_uses": 3
}
```

#### Datos entrantes — Modo relativo (horas)

```json
{
  "expires_in": {
    "unit": "hours",
    "value": 2
  }
}
```

#### Validación de campos

| Campo | Tipo | Requerido | Reglas |
|-------|------|-----------|--------|
| `expires_at` | string (ISO-8601) | Condicional* | `after:now`, no puede coexistir con `expires_in` |
| `expires_in` | object | Condicional* | no puede coexistir con `expires_at` |
| `expires_in.unit` | string | Si se envía `expires_in` | `in:minutes,hours` |
| `expires_in.value` | integer | Si se envía `expires_in` | `minutes`: 1–60 · `hours`: 1–12 |
| `max_uses` | integer | No | 1–1000 |
| `pin` | string | No | 4–16 caracteres |

*Exactamente uno de `expires_at` o `expires_in` es obligatorio.

#### Datos salientes (201 Created)

```json
{
  "id": 12,
  "token": "abc123...xyz",
  "expires_at": "2026-05-12T15:35:00.000000Z",
  "max_uses": 3,
  "use_count": 0,
  "is_active": true,
  "requires_pin": false,
  "share_url": "https://app.cyberpass.io/share/abc123...xyz",
  "created_at": "2026-05-12T15:05:00.000000Z"
}
```

> `expires_at` siempre se devuelve en UTC como fecha absoluta, independientemente de si el cliente usó el modo relativo.

#### Errores esperados

| HTTP | Caso |
|------|------|
| 422 | Ni `expires_at` ni `expires_in` fueron enviados |
| 422 | Ambos `expires_at` y `expires_in` fueron enviados |
| 422 | `expires_at` no es una fecha futura |
| 422 | `expires_in.unit` no es `minutes` ni `hours` |
| 422 | `expires_in.value` fuera del rango permitido |
| 403 | El usuario no tiene permiso para crear tokens en esta credencial |

---

### `GET /api/organizations/{org}/credentials/{credential}/tokens`

Sin cambios en la interfaz. Devuelve `expires_at` absoluto en cada token.

---

### `GET /api/shared/{token}` — Info pública

Sin cambios. Devuelve `expires_at` y `is_valid`.

### `POST /api/shared/{token}/claim` — Consumo público

Sin cambios. El campo `token.expires_at` en la respuesta refleja la fecha absoluta persistida.

---

## Cambios de implementación requeridos

### 1. `CreateSharedTokenRequest` — reglas de validación

```php
public function rules(): array
{
    return [
        'expires_at'       => ['required_without:expires_in', 'prohibited_if:expires_in,present',
                               'date', 'after:now'],
        'expires_in'       => ['required_without:expires_at', 'prohibited_if:expires_at,present', 'array'],
        'expires_in.unit'  => ['required_with:expires_in', 'in:minutes,hours'],
        'expires_in.value' => ['required_with:expires_in', 'integer',
                               /* rango dinámico según unit — validar en withValidator() */],
        'max_uses'         => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1000'],
        'pin'              => ['sometimes', 'nullable', 'string', 'min:4', 'max:16'],
    ];
}
```

La validación del rango de `value` según `unit` se implementa en `withValidator()`:

```php
public function withValidator($validator): void
{
    $validator->after(function ($v) {
        if ($this->has('expires_in')) {
            $unit  = $this->input('expires_in.unit');
            $value = (int) $this->input('expires_in.value');

            if ($unit === 'minutes' && ($value < 1 || $value > 60)) {
                $v->errors()->add('expires_in.value', 'Para minutos el valor debe estar entre 1 y 60.');
            }

            if ($unit === 'hours' && ($value < 1 || $value > 12)) {
                $v->errors()->add('expires_in.value', 'Para horas el valor debe estar entre 1 y 12.');
            }
        }
    });
}
```

### 2. `SharedAccessTokenService::create` — resolución de `expires_at`

```php
$expiresAt = match(true) {
    isset($data['expires_at'])  => $data['expires_at'],
    isset($data['expires_in'])  => match($data['expires_in']['unit']) {
        'minutes' => now()->addMinutes($data['expires_in']['value']),
        'hours'   => now()->addHours($data['expires_in']['value']),
    },
    default => null, // no debería llegar aquí con la validación activa
};
```

---

## Ejemplos de flujo

### Token que vence en 5 minutos
```
POST /tokens
{ "expires_in": { "unit": "minutes", "value": 5 } }
→ expires_at = now() + 5 min
```

### Token que vence mañana a las 9 AM
```
POST /tokens
{ "expires_at": "2026-05-13T09:00:00Z" }
→ expires_at = 2026-05-13T09:00:00Z
```

### Token que vence en 1 hora, con PIN y máximo 3 usos
```
POST /tokens
{ "expires_in": { "unit": "hours", "value": 1 }, "pin": "4892", "max_uses": 3 }
→ expires_at = now() + 1 hora
```
