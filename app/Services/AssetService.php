<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Organization;
use App\Models\Subcategory;

class AssetService
{
    public function createCategory(Organization $organization, array $data): Category
    {
        return $organization->categories()->create($data);
    }

    public function updateCategory(Category $category, array $data): Category
    {
        $category->update($data);

        return $category->fresh();
    }

    public function deleteCategory(Category $category): void
    {
        $category->delete();
    }

    public function createSubcategory(Category $category, array $data): Subcategory
    {
        return $category->subcategories()->create($data);
    }

    public function updateSubcategory(Subcategory $subcategory, array $data): Subcategory
    {
        $subcategory->update($data);

        return $subcategory->fresh();
    }

    public function deleteSubcategory(Subcategory $subcategory): void
    {
        $subcategory->delete();
    }

    public function createAsset(Subcategory $subcategory, Organization $organization, array $data): Asset
    {
        return $subcategory->assets()->create(array_merge($data, [
            'organization_id' => $organization->id,
        ]));
    }

    public function updateAsset(Asset $asset, array $data): Asset
    {
        $asset->update($data);

        return $asset->fresh();
    }

    public function deleteAsset(Asset $asset): void
    {
        $asset->delete();
    }
}
