<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Division;
use App\Models\Organization;

/**
 * Replica una categoría o una división recién creada en TODAS las demás
 * organizaciones activas. Solo la invoca un sysadmin (ver CategoryPolicy y
 * OrganizationPolicy) y solo cuando el creador lo pide explícitamente.
 */
class ReplicationService
{
    /**
     * @return array<int, array{organization_id: int, organization_name: string, status: string, category_id: ?int}>
     */
    public function replicateCategory(Category $source): array
    {
        $targets = Organization::where('is_active', true)
            ->where('id', '!=', $source->organization_id)
            ->get();

        $results = [];

        foreach ($targets as $organization) {
            $duplicate = $organization->categories()
                ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($source->name))])
                ->first();

            if ($duplicate) {
                $results[] = [
                    'organization_id'   => $organization->id,
                    'organization_name' => $organization->name,
                    'status'            => 'skipped_duplicate',
                    'category_id'       => $duplicate->id,
                ];

                continue;
            }

            $divisionId = null;

            if ($source->division_id !== null) {
                $sourceDivision = $source->division;

                $matchingDivision = $sourceDivision
                    ? $organization->divisions()
                        ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($sourceDivision->name))])
                        ->first()
                    : null;

                $divisionId = $matchingDivision?->id;
            }

            $category = $organization->categories()->create([
                'name'        => $source->name,
                'description' => $source->description,
                'image_id'    => $source->image_id,
                'division_id' => $divisionId,
            ]);

            $results[] = [
                'organization_id'   => $organization->id,
                'organization_name' => $organization->name,
                'status'            => 'created',
                'category_id'       => $category->id,
            ];
        }

        return $results;
    }

    /**
     * @return array<int, array{organization_id: int, organization_name: string, status: string, division_id: ?int}>
     */
    public function replicateDivision(Division $source): array
    {
        $targets = Organization::where('is_active', true)
            ->where('id', '!=', $source->organization_id)
            ->get();

        $results = [];

        foreach ($targets as $organization) {
            $duplicate = $organization->divisions()
                ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($source->name))])
                ->first();

            if ($duplicate) {
                $results[] = [
                    'organization_id'   => $organization->id,
                    'organization_name' => $organization->name,
                    'status'            => 'skipped_duplicate',
                    'division_id'       => $duplicate->id,
                ];

                continue;
            }

            $division = $organization->divisions()->create([
                'name'        => $source->name,
                'description' => $source->description,
                'image_id'    => $source->image_id,
                'is_active'   => $source->is_active,
            ]);

            $results[] = [
                'organization_id'   => $organization->id,
                'organization_name' => $organization->name,
                'status'            => 'created',
                'division_id'       => $division->id,
            ];
        }

        return $results;
    }
}
