# Fase 3 — Correcciones

**Fecha:** 2026-05-09

---

## Motivo

La implementación inicial de Fase 3 incluyó `Subcategory` y `Asset` como capas intermedias entre `Category` y `Credential`. El flujo real del sistema es más simple:

```
Organization → Division → Category → Credential
```

Las capas `Subcategory` y `Asset` fueron eliminadas del flujo activo.

---

## Cambios realizados

### Archivos eliminados

| Archivo | Motivo |
|---------|--------|
| `app/Http/Controllers/SubcategoryController.php` | Capa eliminada |
| `app/Http/Controllers/AssetController.php` | Capa eliminada |
| `app/Http/Resources/SubcategoryResource.php` | Capa eliminada |
| `app/Http/Resources/AssetResource.php` | Capa eliminada |
| `app/Http/Requests/Asset/CreateSubcategoryRequest.php` | Capa eliminada |
| `app/Http/Requests/Asset/UpdateSubcategoryRequest.php` | Capa eliminada |
| `app/Http/Requests/Asset/CreateAssetRequest.php` | Capa eliminada |
| `app/Http/Requests/Asset/UpdateAssetRequest.php` | Capa eliminada |

### Archivos modificados

| Archivo | Cambio |
|---------|--------|
| `database/migrations/…create_credentials_table.php` | `asset_id` → `category_id` (FK a `categories`) |
| `app/Models/Credential.php` | `asset_id` → `category_id`; relación `asset()` → `category()` |
| `app/Models/Category.php` | Relación `subcategories()` reemplazada por `credentials()` |
| `app/Policies/CredentialPolicy.php` | Parámetro `Asset` → `Category` en `viewAny` y `create` |
| `app/Services/CredentialService.php` | Parámetro `Asset` → `Category` en `create()` |
| `app/Http/Requests/Asset/CreateCredentialRequest.php` | Autorización usa `category` de la ruta |
| `app/Http/Controllers/CredentialController.php` | Scoped a `Category` en lugar de `Asset` |
| `app/Http/Resources/CredentialResource.php` | `asset_id` → `category_id` |
| `routes/api.php` | Rutas de subcategories y assets eliminadas; credentials bajo `categories/{category}` |

---

## Rutas activas (Fase 3 final)

```
# Categories (bajo organization)
GET/POST        /api/organizations/{organization}/categories
GET/PUT/DELETE  /api/organizations/{organization}/categories/{category}

# Credentials (bajo category)
GET/POST        /api/categories/{category}/credentials
GET/PUT/DELETE  /api/categories/{category}/credentials/{credential}
```

---

## Nota sobre migraciones existentes

Las tablas `subcategories` y `assets` siguen existiendo en el schema (sus migraciones no se eliminaron), pero no forman parte del flujo de negocio activo. Pueden usarse en fases futuras si el producto lo requiere.

Si las migraciones aún no se han corrido en producción, ejecutar:

```bash
php artisan migrate
```

Si ya se corrieron con `asset_id` en `credentials`, crear una migración adicional:

```bash
php artisan make:migration rename_asset_id_to_category_id_in_credentials_table
```
