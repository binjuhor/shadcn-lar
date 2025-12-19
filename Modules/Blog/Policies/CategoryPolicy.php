<?php

namespace Modules\Blog\Policies;

use App\Models\User;
use Modules\Blog\Models\Category;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('categories.view');
    }

    public function view(User $user, Category $category): bool
    {
        return $user->can('categories.view');
    }

    public function create(User $user): bool
    {
        return $user->can('categories.create');
    }

    public function update(User $user, Category $category): bool
    {
        return $user->can('categories.edit');
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->can('categories.delete');
    }
}
