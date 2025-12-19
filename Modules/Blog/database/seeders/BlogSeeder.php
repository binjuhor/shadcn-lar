<?php

namespace Modules\Blog\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Tag;

class BlogSeeder extends Seeder
{
    public function run(): void
    {
        // Create categories
        $categories = [
            [
                'name' => 'Technology',
                'description' => 'All things tech related',
                'color' => '#3b82f6',
                'icon' => 'laptop',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Development',
                'description' => 'Web development tutorials and guides',
                'color' => '#10b981',
                'icon' => 'code',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Design',
                'description' => 'UI/UX design and visual design articles',
                'color' => '#f59e0b',
                'icon' => 'palette',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Backend',
                'description' => 'Server-side development and architecture',
                'color' => '#8b5cf6',
                'icon' => 'server',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'DevOps',
                'description' => 'Deployment, CI/CD, and infrastructure topics',
                'color' => '#ef4444',
                'icon' => 'cloud',
                'is_active' => true,
                'sort_order' => 5,
            ],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }

        // Create tags
        $tags = [
            ['name' => 'React', 'color' => '#61dafb', 'is_active' => true],
            ['name' => 'Vue', 'color' => '#42b883', 'is_active' => true],
            ['name' => 'Angular', 'color' => '#dd0031', 'is_active' => true],
            ['name' => 'Laravel', 'color' => '#ff2d20', 'is_active' => true],
            ['name' => 'PHP', 'color' => '#777bb4', 'is_active' => true],
            ['name' => 'JavaScript', 'color' => '#f7df1e', 'is_active' => true],
            ['name' => 'TypeScript', 'color' => '#3178c6', 'is_active' => true],
            ['name' => 'Python', 'color' => '#3776ab', 'is_active' => true],
            ['name' => 'Node.js', 'color' => '#339933', 'is_active' => true],
            ['name' => 'Docker', 'color' => '#2496ed', 'is_active' => true],
            ['name' => 'Kubernetes', 'color' => '#326ce5', 'is_active' => true],
            ['name' => 'AWS', 'color' => '#ff9900', 'is_active' => true],
            ['name' => 'CSS', 'color' => '#1572b6', 'is_active' => true],
            ['name' => 'Tailwind', 'color' => '#06b6d4', 'is_active' => true],
            ['name' => 'Tutorial', 'color' => '#10b981', 'is_active' => true],
        ];

        foreach ($tags as $tag) {
            Tag::create($tag);
        }

        $this->command->info('Blog categories and tags seeded successfully!');
    }
}
