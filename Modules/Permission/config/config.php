<?php

return [
    'name' => 'Permission',

    'default_roles' => [
        'Super Admin',
        'Admin',
        'Editor',
        'Author',
        'Viewer',
    ],

    'permissions' => [
        // Blog Module
        'posts' => ['view', 'create', 'edit', 'delete'],
        'categories' => ['view', 'create', 'edit', 'delete'],
        'tags' => ['view', 'create', 'edit', 'delete'],

        // Ecommerce Module
        'products' => ['view', 'create', 'edit', 'delete'],
        'product-categories' => ['view', 'create', 'edit', 'delete'],
        'product-tags' => ['view', 'create', 'edit', 'delete'],
        'orders' => ['view', 'create', 'edit', 'delete'],

        // User Management
        'users' => ['view', 'create', 'edit', 'delete'],

        // Role/Permission Management
        'roles' => ['view', 'create', 'edit', 'delete'],
        'permissions' => ['view', 'create', 'edit', 'delete'],
    ],

    'role_permissions' => [
        'Super Admin' => '*', // All permissions (handled via Gate::before)

        'Admin' => [
            'posts.*', 'categories.*', 'tags.*',
            'products.*', 'product-categories.*', 'product-tags.*', 'orders.*',
            'users.*', 'roles.*', 'permissions.*',
        ],

        'Editor' => [
            'posts.*', 'categories.*', 'tags.*',
            'products.*', 'product-categories.*', 'product-tags.*',
            'orders.view', 'orders.edit',
        ],

        'Author' => [
            'posts.view', 'posts.create', 'posts.edit',
            'categories.view', 'tags.view',
            'products.view', 'products.create', 'products.edit',
            'product-categories.view', 'product-tags.view',
        ],

        'Viewer' => [
            'posts.view', 'categories.view', 'tags.view',
            'products.view', 'product-categories.view', 'product-tags.view',
            'orders.view',
        ],
    ],
];
