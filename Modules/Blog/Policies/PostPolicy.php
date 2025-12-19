<?php

namespace Modules\Blog\Policies;

use App\Models\User;
use Modules\Blog\Models\Post;

class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('posts.view');
    }

    public function view(User $user, Post $post): bool
    {
        return $user->can('posts.view');
    }

    public function create(User $user): bool
    {
        return $user->can('posts.create');
    }

    public function update(User $user, Post $post): bool
    {
        return $user->can('posts.edit');
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->can('posts.delete');
    }
}
