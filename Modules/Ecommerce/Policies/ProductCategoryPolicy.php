<?php

namespace Modules\Ecommerce\Policies;

use App\Models\User;
use Modules\Ecommerce\Models\ProductCategory;

class ProductCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('product-categories.view');
    }

    public function view(User $user, ProductCategory $productCategory): bool
    {
        return $user->can('product-categories.view');
    }

    public function create(User $user): bool
    {
        return $user->can('product-categories.create');
    }

    public function update(User $user, ProductCategory $productCategory): bool
    {
        return $user->can('product-categories.edit');
    }

    public function delete(User $user, ProductCategory $productCategory): bool
    {
        return $user->can('product-categories.delete');
    }
}
