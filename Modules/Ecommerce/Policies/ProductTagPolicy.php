<?php

namespace Modules\Ecommerce\Policies;

use App\Models\User;
use Modules\Ecommerce\Models\ProductTag;

class ProductTagPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('product-tags.view');
    }

    public function view(User $user, ProductTag $productTag): bool
    {
        return $user->can('product-tags.view');
    }

    public function create(User $user): bool
    {
        return $user->can('product-tags.create');
    }

    public function update(User $user, ProductTag $productTag): bool
    {
        return $user->can('product-tags.edit');
    }

    public function delete(User $user, ProductTag $productTag): bool
    {
        return $user->can('product-tags.delete');
    }
}
