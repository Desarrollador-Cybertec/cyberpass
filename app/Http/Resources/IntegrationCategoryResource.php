<?php

namespace App\Http\Resources;

use App\Services\IntegrationScopeResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Categoría normalizada para clientes de integración.
 *
 * Recurso propio y no CategoryResource: el SPA consume aquel y no tiene noción
 * de `scope`, que es justo lo que Axis necesita para saber a qué ámbito escribe.
 */
class IntegrationCategoryResource extends JsonResource
{
    public function __construct($resource, private readonly bool $canCreateCredential = true)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $scope = $this->user_id !== null
            ? IntegrationScopeResolver::SCOPE_PERSONAL
            : IntegrationScopeResolver::SCOPE_ORGANIZATION;

        return [
            'id'                    => $this->id,
            'name'                  => $this->name,
            'description'           => $this->description,
            'scope'                 => $scope,
            'organization'          => $this->when(
                $this->organization_id !== null,
                fn () => [
                    'id'   => $this->organization_id,
                    'name' => $this->whenLoaded('organization', fn () => $this->organization?->name),
                ],
            ),
            'division_id'           => $this->division_id,
            'can_create_credential' => $this->canCreateCredential,
        ];
    }
}
