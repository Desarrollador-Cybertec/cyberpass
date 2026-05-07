# Fase 1 — Fundación, Base de Datos y Autenticación

**Estado:** Completada  
**Fecha:** 2026-05-07

---

## Stack

| Capa | Tecnología |
|------|-----------|
| Backend | Laravel 13 |
| Base de datos | PostgreSQL (Supabase) |
| Autenticación | Laravel Sanctum (Bearer tokens) |
| OAuth | Laravel Socialite (Google) |
| 2FA | pragmarx/google2fa-laravel (TOTP) |
| QR | bacon/bacon-qr-code |
| Testing | Pest |

---

## Paquetes instalados

```bash
composer require laravel/sanctum laravel/socialite pragmarx/google2fa-laravel bacon/bacon-qr-code
```

---

## Variables de entorno requeridas

```env
# Base de datos (Supabase)
DB_CONNECTION=pgsql
DB_HOST=your-supabase-host.supabase.co
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=your-supabase-password
DB_SSLMODE=require

# Google OAuth
GOOGLE_CLIENT_ID=your-google-client-id
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_REDIRECT_URI=http://cyberpass-api.test/api/auth/google/callback

# Cifrado de credenciales (clave base64 de exactamente 32 bytes)
CREDENTIAL_ENCRYPTION_KEY=your-32-byte-base64-key
```

---

## Modelo de datos — Tablas

### `organizations`
| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | bigint PK | |
| name | string | Nombre de la empresa |
| slug | string unique | Identificador URL |
| logo | string nullable | URL del logo |
| is_active | boolean | Estado |
| settings | jsonb | Configuración flexible |

### `organization_domains`
| Campo | Tipo | Descripción |
|-------|------|-------------|
| organization_id | FK | |
| domain | string unique | Ej: `acme.com` |
| is_verified | boolean | Dominio verificado |

### `users`
| Campo | Tipo | Descripción |
|-------|------|-------------|
| organization_id | FK nullable | |
| name, email | string | |
| password | string nullable | Argon2id via Laravel |
| google_id | string nullable | OAuth Google |
| role | enum | `sysadmin`, `org_admin`, `org_user` |
| account_type | enum | `personal`, `enterprise`, `sysadmin` |
| two_factor_secret | string nullable | Secreto TOTP |
| two_factor_enabled | boolean | 2FA activo |
| two_factor_confirmed_at | timestamp | Cuándo se confirmó |
| is_active | boolean | |
| last_login_at | timestamp | |

### `categories`
| Campo | Tipo | Descripción |
|-------|------|-------------|
| organization_id | FK | |
| name | string | Ej: Equipos, Infraestructura |
| description | text nullable | |

### `subcategories`
| Campo | Tipo | Descripción |
|-------|------|-------------|
| category_id | FK | |
| name | string | Ej: Laptops, Tablets |
| description | text nullable | |

### `assets`
| Campo | Tipo | Descripción |
|-------|------|-------------|
| subcategory_id | FK | |
| organization_id | FK | |
| name | string | Ej: Laptop CEO |
| description | text nullable | |
| metadata | jsonb | Datos adicionales |

### `credentials`
| Campo | Tipo | Descripción |
|-------|------|-------------|
| asset_id | FK | |
| organization_id | FK | |
| created_by | FK users | |
| name | string | Ej: Windows Admin |
| username | string nullable | |
| encrypted_password | text | AES-256-GCM |
| iv | string | Vector de inicialización |
| notes_encrypted | text nullable | Notas cifradas |
| iv_notes | string nullable | |
| type | string | `password`, `ssh_key`, etc. |

### `credential_versions`
Historial de cambios por credencial. Cada update genera una versión.

### `audit_logs`
Acciones: `login`, `logout`, `reveal_password`, `copy_password`, `create`, `update`, `delete`, `export`, `assign`, `share`, `2fa_enabled`, `2fa_disabled`, `2fa_verified`.

### `shared_access_tokens`
Tokens temporales con expiración, límite de usos y desactivación manual.

---

## Endpoints de autenticación

### Públicos

| Método | Ruta | Descripción | Rate Limit |
|--------|------|-------------|------------|
| POST | `/api/auth/register` | Registro personal | 10/min |
| POST | `/api/auth/login` | Login email+password | 6/min |
| POST | `/api/auth/login/2fa` | Verificar TOTP | 3/min |
| GET | `/api/auth/google/redirect` | Redirect a Google | — |
| GET | `/api/auth/google/callback` | Callback Google OAuth | — |

### Protegidos (`Authorization: Bearer <token>`)

| Método | Ruta | Descripción |
|--------|------|-------------|
| POST | `/api/auth/logout` | Cerrar sesión |
| GET | `/api/auth/me` | Usuario autenticado |
| POST | `/api/auth/2fa/setup` | Generar secret + QR |
| POST | `/api/auth/2fa/enable` | Activar 2FA con OTP |
| POST | `/api/auth/2fa/disable` | Desactivar 2FA |

---

## Flujo de login enterprise

```
POST /api/auth/login
  │
  ├── Valida email + password
  ├── Busca dominio en organization_domains
  │   ├── Dominio encontrado → account_type = enterprise
  │   └── Sin dominio → personal
  │
  ├── ¿Enterprise + 2FA activo?
  │   ├── SÍ → { requires_2fa: true, temp_token } (válido 5 min en Cache)
  │   └── NO → emite Sanctum token
  │
POST /api/auth/login/2fa
  │
  ├── Valida temp_token en Cache
  ├── Verifica OTP con Google2FA
  └── Emite Sanctum token + registra audit log
```

## Flujo 2FA setup

```
POST /api/auth/2fa/setup      → genera secret, retorna { secret, qr_svg, qr_uri }
  (usuario escanea QR en Google Authenticator)
POST /api/auth/2fa/enable     → valida OTP, activa two_factor_enabled
```

---

## Seguridad implementada

- Contraseñas de usuarios: **Argon2id** (default de Laravel desde v10)
- Contraseñas de credenciales: **AES-256-GCM** con IV único por registro
- 2FA: **TOTP** compatible con Google Authenticator (RFC 6238)
- Tokens de API: **Laravel Sanctum** Bearer tokens
- Soft Deletes en: `organizations`, `users`, `categories`, `subcategories`, `assets`, `credentials`
- Audit log automático en: login, logout, 2fa_enabled, 2fa_disabled, 2fa_verified

---

## Archivos creados

```
app/
  Models/
    Organization.php
    OrganizationDomain.php
    User.php              (modificado)
    Category.php
    Subcategory.php
    Asset.php
    Credential.php
    CredentialVersion.php
    AuditLog.php
    SharedAccessToken.php
  Services/
    EncryptionService.php
    AuditService.php
  Http/Controllers/Auth/
    AuthController.php
    GoogleAuthController.php
    TwoFactorController.php
database/migrations/
  0001_01_01_000000_create_organizations_table.php
  0001_01_01_000001_create_users_table.php         (reemplazó default)
  2026_05_07_*_create_organization_domains_table.php
  2026_05_07_*_create_categories_table.php
  2026_05_07_*_create_subcategories_table.php
  2026_05_07_*_create_assets_table.php
  2026_05_07_*_create_credentials_table.php
  2026_05_07_*_create_credential_versions_table.php
  2026_05_07_*_create_audit_logs_table.php
  2026_05_07_*_create_shared_access_tokens_table.php
routes/api.php              (creado)
bootstrap/app.php           (modificado — añadido api.php)
config/services.php         (añadido Google OAuth)
config/google2fa.php        (publicado)
config/sanctum.php          (publicado)
```

---

## Siguiente fase

**Fase 2 — Gestión de Organizaciones**
- CRUD de organizaciones (SysAdmin)
- Gestión de dominios y verificación
- CRUD de usuarios por organización
- Roles y permisos (policies)
