<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Rosas',
                'slug' => 'rosas',
                'description' => 'Hermosas rosas de diferentes colores y variedades para expresar tus sentimientos.',
                'image' => '/images/categories/rosas.jpg',
                'is_active' => true,
                'sort_order' => 1
            ],
            [
                'name' => 'Bouquets',
                'slug' => 'bouquets',
                'description' => 'Arreglos florales elegantes y personalizados para ocasiones especiales.',
                'image' => '/images/categories/bouquets.jpg',
                'is_active' => true,
                'sort_order' => 2
            ],
            [
                'name' => 'Flores Tropicales',
                'slug' => 'flores-tropicales',
                'description' => 'Flores exóticas y tropicales que aportan color y vida a cualquier espacio.',
                'image' => '/images/categories/tropicales.jpg',
                'is_active' => true,
                'sort_order' => 3
            ],
            [
                'name' => 'Plantas',
                'slug' => 'plantas',
                'description' => 'Plantas decorativas y de interior para embellecer tu hogar u oficina.',
                'image' => '/images/categories/plantas.jpg',
                'is_active' => true,
                'sort_order' => 4
            ],
            [
                'name' => 'Coronas',
                'slug' => 'coronas',
                'description' => 'Coronas funerarias y de condolencias elaboradas con respeto y delicadeza.',
                'image' => '/images/categories/coronas.jpg',
                'is_active' => true,
                'sort_order' => 5
            ],
            [
                'name' => 'Flores de Temporada',
                'slug' => 'flores-de-temporada',
                'description' => 'Flores frescas de temporada, disponibles según la época del año.',
                'image' => '/images/categories/temporada.jpg',
                'is_active' => true,
                'sort_order' => 6
            ]
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }

        $this->command->info('Categories created successfully!');
    }
}
