# CyberPass API — Fase 6: Organización Robusta

## Contexto

La Fase 6 cierra las brechas de autorización encontradas en las Fases 1–5: controladores que bypaseaban policies existentes, `org_user` con acceso a recursos restringidos, y gestión de usuarios limitada. Formaliza el modelo de roles `sysadmin / org_admin / org_user` end-to-end.

---

## 1. Corrección de Gates Faltantes

### Cambios aplicados

| Controller | Método | Problema | Fix |
|---|---|---|---|
| `CategoryController` | `update()` | Sin `authorize()` | Agregado `$this->authorize('update', $category)` |
| `CategoryController` | `store()` | Ya cubierto por `CreateCategoryRequest::authorize()` | Sin cambio |
| `CredentialController` | `update()` | Sin `authorize()` — ya tenía policy correcta | Agregado `$this->authorize('update', $credential)` |

### `CredentialVersionPolicy::viewAny()` corregida

Antes: cualquier miembro de la org podía ver el historial de versiones.
Ahora: solo `sysadmin` u `org_admin`.

---

## 2. Modelo de Permisos org_admin vs org_user

| Acción | `sysadmin` | `org_admin` | `org_user` |
|---|---|---|---|
| Crear categoría | ✅ | ✅ | ❌ |
| Editar/eliminar categoría | ✅ | ✅ | ❌ |
| Crear credencial | ✅ | ✅ | ✅ |
| Editar credencial propia | ✅ | ✅ | ✅ |
| Editar credencial ajena | ✅ | ✅ | ❌ |
| Eliminar credencial | ✅ | ✅ | ❌ |
| Ver historial de versiones | ✅ | ✅ | ❌ |
| Restaurar versión | ✅ | ✅ | ❌ |
| Crear token compartido | ✅ | ✅ | ✅ |
| Revocar token ajeno | ✅ | ✅ | ❌ |
| Exportar CSV | ✅ | ✅ | ❌ |
| Ver audit logs | ✅ | ✅ (su org) | ❌ |
| Invitar/gestionar usuarios | ✅ | ✅ | ❌ |
| Gestionar dominios | ✅ | ✅ | ❌ |

---

## 3. Actividad Propia del Usuario

### `GET /auth/activity`
**Requiere autenticación. Accesible por cualquier rol.**

**Query params:**
| Param | Descripción |
|---|---|
| `action` | Filtrar por tipo de acción |
| `start_date` | Fecha inicio (YYYY-MM-DD) |
| `end_date` | Fecha fin (YYYY-MM-DD) |
| `page` | Página |

**Response `200`:** paginado estándar `{ data, links, meta }`

```json
{
  "data": [
    {
      "id": 1,
      "action": "login",
      "entity_type": null,
      "entity_id": null,
      "metadata": {},
      "ip_address": "192.168.1.1",
      "created_at": "2026-05-12T10:00:00Z"
    }
  ]
}
```

Devuelve únicamente los logs donde `user_id = auth()->id()`. No expone logs de otros usuarios.

---

## 4. Gestión de Usuarios por org_admin

### `GET /organizations/{org}/users` — filtros agregados

**Query params nuevos:**
| Param | Descripción |
|---|---|
| `role` | `org_admin` \| `org_user` |
| `is_active` | `true` \| `false` |
| `q` | Búsqueda por nombre o email (ILIKE) |

---

### `PATCH /organizations/{org}/users/{user}/suspend`
**Solo `org_admin` (su org) y `sysadmin`.**

Establece `is_active = false` sin eliminar al usuario de la organización.

**Response `200`:**
```json
{ "message": "Usuario suspendido." }
```

Registra en `audit_logs` action `suspend`.

---

### `PATCH /organizations/{org}/users/{user}/activate`
**Solo `org_admin` (su org) y `sysadmin`.**

Establece `is_active = true` (reactiva un usuario suspendido).

**Response `200`:**
```json
{ "message": "Usuario activado." }
```

Registra en `audit_logs` action `activate`.

---

### `POST /organizations/{org}/users/{user}/make-admin`
**Solo `sysadmin`.**

Cambia el `role` del usuario a `org_admin`.

**Response `200`:**
```json
{ "message": "Usuario promovido a org_admin." }
```

**Response `403`** si lo intenta un `org_admin`.

Registra en `audit_logs` action `assign`, metadata `{ role: "org_admin" }`.

---

### `POST /organizations/{org}/users/{user}/make-user`
**Solo `sysadmin`.**

Degrada un `org_admin` a `org_user`.

**Response `200`:**
```json
{ "message": "Usuario degradado a org_user." }
```

Registra en `audit_logs` action `assign`, metadata `{ role: "org_user" }`.

---

## Acciones de audit_logs agregadas en Fase 6

| Action | Cuándo |
|---|---|
| `suspend` | org_admin o sysadmin suspende un usuario |
| `activate` | org_admin o sysadmin reactiva un usuario |

---

## Migraciones

| Archivo | Cambio |
|---|---|
| `2026_05_12_120000_add_phase6_actions_to_audit_logs_table` | Agrega `suspend`, `activate` al CHECK constraint |

---

## Archivos modificados

| Archivo | Cambio |
|---|---|
| `app/Http/Controllers/CategoryController.php` | `authorize('update')` en `update()` |
| `app/Http/Controllers/CredentialController.php` | `authorize('update')` en `update()` |
| `app/Policies/CredentialVersionPolicy.php` | `viewAny()` ahora solo `sysadmin` u `org_admin` |
| `app/Http/Controllers/OrganizationUserController.php` | `suspend()`, `activate()`, `makeAdmin()`, `makeUser()`, filtros en `index()` |
| `app/Services/OrganizationService.php` | `suspendUser()`, `activateUser()`, `changeRole()` |
| `routes/api.php` | `GET /auth/activity`, 4 rutas de gestión de usuarios |

## Archivos creados

| Archivo | Descripción |
|---|---|
| `app/Http/Controllers/Auth/ActivityController.php` | Historial de actividad del usuario autenticado |
