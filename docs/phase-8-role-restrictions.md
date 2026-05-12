# CyberPass API — Fase 8: Restricciones de Vista y Alcance por Rol

## Contexto

La Fase 8 define las reglas de visibilidad y alcance para cada rol del sistema, y establece la asignación automática de rol al momento del registro según el dominio del correo electrónico.

---

## 1. Roles del sistema

| Rol | Descripción |
|-----|-------------|
| `sysadmin` | Acceso total a todas las organizaciones y recursos del sistema |
| `org_admin` | Gestión completa dentro de su propia organización |
| `org_user` | Acceso de lectura/escritura limitado dentro de su organización |
| `user` | Usuario personal sin organización; solo accede a su vault personal |

---

## 2. Asignación automática de rol al registrarse

### `POST /auth/register`

El rol se asigna automáticamente según el dominio del correo:

| Tipo de correo | Ejemplo | Rol | account_type |
|---|---|---|---|
| Proveedor público | `@gmail.com`, `@outlook.com`, `@hotmail.com`, `@yahoo.com`, `@icloud.com`, etc. | `user` | `personal` |
| Dominio corporativo (no está en la lista pública) | `@miempresa.com`, `@startup.io` | `org_user` | `enterprise` |
| Dominio corporativo registrado en el sistema | `@empresa.com` con org verificada | `org_user` + `organization_id` | `enterprise` |

**Response `201` — usuario personal:**
```json
{
  "user": {
    "id": 1,
    "name": "Juan García",
    "email": "juan@gmail.com",
    "account_type": "personal",
    "role": "user",
    "organization": null
  },
  "token": "1|abc..."
}
```

**Response `201` — usuario corporativo:**
```json
{
  "user": {
    "id": 2,
    "name": "Ana Pérez",
    "email": "ana@miempresa.com",
    "account_type": "enterprise",
    "role": "org_user",
    "organization": {
      "id": 5,
      "name": "Mi Empresa SA",
      "slug": "mi-empresa-sa"
    }
  },
  "token": "1|xyz..."
}
```

La misma lógica aplica para el registro mediante **Google OAuth** (`GET /auth/google/callback`).

### Proveedores de correo considerados públicos

`gmail.com`, `googlemail.com`, `outlook.com`, `hotmail.com`, `hotmail.es`, `live.com`, `live.es`, `msn.com`, `yahoo.com`, `yahoo.es`, `icloud.com`, `me.com`, `mac.com`, `proton.me`, `protonmail.com`, `tutanota.com`, `aol.com`, `zoho.com`, `mail.com`, `yandex.com`, `gmx.com`, `gmx.net`, `web.de`, y otros.

---

## 3. Matriz de permisos por rol

### Organizaciones

| Acción | `user` | `org_user` | `org_admin` | `sysadmin` |
|--------|:------:|:----------:|:-----------:|:----------:|
| Listar todas las orgs | ✗ | ✗ | ✗ | ✓ |
| Ver su propia org | ✗ | ✓ | ✓ | ✓ |
| Crear org | ✗ | ✗ | ✗ | ✓ |
| Editar org | ✗ | ✗ | ✓ (solo la suya) | ✓ |
| Eliminar org | ✗ | ✗ | ✗ | ✓ |
| Gestionar dominios | ✗ | ✗ | ✓ (solo la suya) | ✓ |
| Gestionar divisiones | ✗ | ✗ | ✓ (solo la suya) | ✓ |

### Usuarios

| Acción | `user` | `org_user` | `org_admin` | `sysadmin` |
|--------|:------:|:----------:|:-----------:|:----------:|
| Listar usuarios de la org | ✗ | ✗ | ✓ (solo la suya) | ✓ |
| Ver perfil de otro usuario | ✗ | ✗ | ✓ (solo la suya) | ✓ |
| Invitar usuarios | ✗ | ✗ | ✓ | ✓ |
| Editar usuario | ✗ | ✗ | ✓ (solo la suya, no sysadmin) | ✓ |
| Suspender / activar | ✗ | ✗ | ✓ (solo la suya) | ✓ |
| Eliminar usuario | ✗ | ✗ | ✓ (solo la suya) | ✓ |

### Categorías (org)

| Acción | `user` | `org_user` | `org_admin` | `sysadmin` |
|--------|:------:|:----------:|:-----------:|:----------:|
| Listar categorías | ✗ | ✓ (solo la suya) | ✓ | ✓ |
| Ver categoría | ✗ | ✓ (solo la suya) | ✓ | ✓ |
| Crear categoría | ✗ | ✗ | ✓ | ✓ |
| Editar categoría | ✗ | ✗ | ✓ | ✓ |
| Eliminar categoría | ✗ | ✗ | ✓ | ✓ |

### Credenciales (org)

| Acción | `user` | `org_user` | `org_admin` | `sysadmin` |
|--------|:------:|:----------:|:-----------:|:----------:|
| Listar credenciales | ✗ | ✓ (solo su org) | ✓ | ✓ |
| Ver credencial | ✗ | ✓ (solo su org) | ✓ | ✓ |
| Crear credencial | ✗ | ✓ | ✓ | ✓ |
| Editar credencial | ✗ | ✓ (solo las propias) | ✓ (toda la org) | ✓ |
| Eliminar credencial | ✗ | ✗ | ✓ | ✓ |
| Compartir token (SAT) | ✗ | ✓ | ✓ | ✓ |
| Revocar token ajeno | ✗ | ✗ | ✓ | ✓ |

### Audit Logs

| Acción | `user` | `org_user` | `org_admin` | `sysadmin` |
|--------|:------:|:----------:|:-----------:|:----------:|
| Ver audit logs globales | ✗ | ✗ | ✗ | ✓ |
| Ver audit logs de su org | ✗ | ✗ | ✓ | ✓ |

### Vault Personal

| Acción | `user` | `org_user` | `org_admin` | `sysadmin` |
|--------|:------:|:----------:|:-----------:|:----------:|
| Gestionar vault propio | ✓ | ✓ | ✓ | ✓ |

Todos los usuarios autenticados tienen acceso completo a su propio vault personal, independientemente del rol.

---

## 4. Comportamiento en login para `org_user` sin org asignada

Si un `org_user` inicia sesión y aún no tiene `organization_id` (porque la org se registró después), el sistema intenta asociarlo automáticamente al hacer login (`syncEnterpriseOrganization`):

1. Extrae el dominio del email
2. Busca en `organization_domains` un dominio verificado coincidente
3. Si existe, asigna `organization_id` y `account_type = 'enterprise'`

Los usuarios con rol `user` (personal) **nunca** pasan por esta sincronización.

---

## 5. Errores de autorización

Cualquier intento de acceso fuera del alcance del rol devuelve:

**Response `403`:**
```json
{
  "message": "This action is unauthorized."
}
```

---

## Archivos creados

| Archivo | Descripción |
|---------|-------------|
| `app/Helpers/PublicEmailDetector.php` | Detecta si un email pertenece a un proveedor público |

## Archivos modificados

| Archivo | Cambio |
|---------|--------|
| `app/Services/AuthService.php` | `register()` y `findOrCreateFromGoogle()` asignan rol según dominio; `syncEnterpriseOrganization()` excluye rol `user` |
| `app/Models/User.php` | Nuevos helpers: `isOrgAdmin()`, `isOrgUser()`, `isPersonalUser()`, `belongsToOrg()` |
| `app/Policies/OrganizationPolicy.php` | Bloquea `user`; usa helpers de rol |
| `app/Policies/CategoryPolicy.php` | Bloquea `user`; usa helpers de rol |
| `app/Policies/CredentialPolicy.php` | Bloquea `user`; usa helpers de rol |
| `app/Policies/UserPolicy.php` | Bloquea `user`; usa helpers de rol |
| `app/Policies/AuditLogPolicy.php` | Usa helpers de rol |
| `app/Policies/SharedAccessTokenPolicy.php` | Bloquea `user`; usa helpers de rol |
