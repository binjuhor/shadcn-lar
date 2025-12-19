<?php

namespace Modules\Blog\Policies;

use App\Models\User;
use Modules\Blog\Models\Tag;

class TagPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tags.view');
    }

    public function view(User $user, Tag $tag): bool
    {
        return $user->can('tags.view');
    }

    public function create(User $user): bool
    {
        return $user->can('tags.create');
    }

    public function update(User $user, Tag $tag): bool
    {
        return $user->can('tags.edit');
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $user->can('tags.delete');
    }
}
