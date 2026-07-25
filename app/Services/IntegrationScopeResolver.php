<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Credential;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Única fuente de verdad sobre a qué ámbito pertenece una categoría o credencial
 * y quién puede tocarla desde la superficie de integración.
 *
 * Existe porque las dos rutas humanas divergen: la de organización revienta con
 * categorías personales y la personal responde 403 con las de organización.
 * Descubrimiento (`can_create_credential`) y escritura usan este mismo resolver,
 * así que no pueden discrepar.
 */
class IntegrationScopeResolver
{
    public const SCOPE_PERSONAL = 'personal';

    public const SCOPE_ORGANIZATION = 'organization';

    /**
     * El ámbito sale de la fila, nunca del cliente. En la BD hay un CHECK que
     * garantiza que exactamente uno de user_id / organization_id está puesto.
     */
    public function scopeOf(Category|Credential $model): string
    {
        return $model->user_id !== null ? self::SCOPE_PERSONAL : self::SCOPE_ORGANIZATION;
    }

    public function canCreateIn(User $user, Category $category): bool
    {
        return $this->scopeOf($category) === self::SCOPE_PERSONAL
            ? $category->user_id === $user->id
            : Gate::forUser($user)->allows('create', [Credential::class, $category]);
    }

    public function assertCanCreateIn(User $user, Category $category, string $declaredScope): void
    {
        // 422 y no 404: si el ámbito declarado no coincide, el cliente se
        // equivocó de id. Fallar aquí evita escribir un secreto corporativo
        // dentro de la bóveda personal de alguien (o al revés).
        abort_if(
            $this->scopeOf($category) !== $declaredScope,
            422,
            'El scope declarado no corresponde a la categoría indicada.',
        );

        // 404 y no 403: un token filtrado no debe servir de oráculo para
        // enumerar ids ajenos. Divergencia deliberada de las rutas del SPA.
        abort_unless($this->canCreateIn($user, $category), 404);
    }

    /** @param  'view'|'update'|'delete'  $action */
    public function assertCan(User $user, Credential $credential, string $action): void
    {
        $allowed = $this->scopeOf($credential) === self::SCOPE_PERSONAL
            ? $credential->user_id === $user->id
            : Gate::forUser($user)->allows($action, $credential);

        abort_unless($allowed, 404);
    }

    /**
     * Categorías donde el usuario puede escribir, en ambos ámbitos y sin
     * paginar: Axis las necesita todas de una vez para poblar un desplegable,
     * y no puede iterar páginas dentro de un worker de PHP-FPM.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Category>
     */
    public function visibleCategories(User $user)
    {
        return Category::query()
            ->with(['image', 'organization'])
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id);

                if ($user->isSysAdmin()) {
                    $query->orWhereNotNull('organization_id');
                } elseif (! $user->isPersonalUser() && $user->organization_id !== null) {
                    $query->orWhere('organization_id', $user->organization_id);
                }
            })
            ->orderBy('name')
            // Tope duro: un sysadmin arrastraria todas las categorias del sistema.
            ->limit(500)
            ->get();
    }
}
