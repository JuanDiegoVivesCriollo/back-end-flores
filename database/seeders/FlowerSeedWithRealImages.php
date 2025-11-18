<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Flower;
use App\Models\Category;

class FlowerSeedWithRealImages extends Seeder
{
    public function run(): void
    {
        // Get category IDs
        $rosas = Category::where('name', 'Rosas')->first()->id;
        $bouquets = Category::where('name', 'Bouquets')->first()->id;
        $tropicales = Category::where('name', 'Flores Tropicales')->first()->id;
        $plantas = Category::where('name', 'Plantas')->first()->id;

        $flowers = [
            [
                'category_id' => $rosas,
                'name' => 'Rosas Rojas Premium',
                'slug' => 'rosas-rojas-premium',
                'description' => 'Hermosas rosas rojas perfectas para expresar amor y pasión. Frescas y de larga duración, ideales para sorprender a esa persona especial.',
                'short_description' => 'Rosas rojas frescas perfectas para expresar amor',
                'price' => 45.00,
                'original_price' => null,
                'discount_percentage' => 0,
                'color' => 'Rojo',
                'occasion' => 'Amor y Romance',
                'images' => json_encode(['/img/catalogo/imagencatalogo1.webp', '/img/catalogo/imagencatalogo2.webp']),
                'rating' => 4.8,
                'reviews_count' => 25,
                'stock' => 50,
                'is_active' => true,
                'is_featured' => true,
                'is_on_sale' => false,
                'views' => 0,
                'sort_order' => 1,
                'metadata' => json_encode(['care_instructions' => 'Mantener en agua fresca. Cambiar el agua cada 2 días.'])
            ],
            [
                'category_id' => $rosas,
                'name' => 'Rosas Blancas Elegantes',
                'slug' => 'rosas-blancas-elegantes',
                'description' => 'Elegantes rosas blancas simbolizando pureza y nuevos comienzos. Perfectas para bodas y ceremonias especiales.',
                'short_description' => 'Rosas blancas elegantes para ocasiones especiales',
                'price' => 38.00,
                'original_price' => 45.00,
                'discount_percentage' => 16,
                'color' => 'Blanco',
                'occasion' => 'Bodas y Ceremonias',
                'images' => json_encode(['/img/catalogo/imagencatalogo3.webp']),
                'rating' => 4.9,
                'reviews_count' => 18,
                'stock' => 30,
                'is_active' => true,
                'is_featured' => true,
                'is_on_sale' => true,
                'views' => 0,
                'sort_order' => 2,
                'metadata' => json_encode(['care_instructions' => 'Ideales para ceremonias importantes'])
            ],
            [
                'category_id' => $bouquets,
                'name' => 'Bouquet Romántico Deluxe',
                'slug' => 'bouquet-romantico-deluxe',
                'description' => 'Hermoso arreglo floral con rosas rojas, flores de temporada y follaje verde. Perfecto para sorprender en ocasiones especiales.',
                'short_description' => 'Arreglo romántico perfecto para sorprender',
                'price' => 75.00,
                'original_price' => null,
                'discount_percentage' => 0,
                'color' => 'Mixto',
                'occasion' => 'Amor y Romance',
                'images' => json_encode(['/img/catalogo/imagencatalogo4.webp', '/img/catalogo/imagencatalogo5.webp']),
                'rating' => 4.7,
                'reviews_count' => 32,
                'stock' => 20,
                'is_active' => true,
                'is_featured' => true,
                'is_on_sale' => false,
                'views' => 0,
                'sort_order' => 1,
                'metadata' => json_encode(['includes' => 'Rosas, lirios y follaje verde'])
            ],
            [
                'category_id' => $bouquets,
                'name' => 'Arreglo Primaveral',
                'slug' => 'arreglo-primaveral',
                'description' => 'Colorido arreglo floral con flores de temporada en tonos pastel. Ideal para alegrar cualquier espacio.',
                'short_description' => 'Arreglo colorido con flores de temporada',
                'price' => 55.00,
                'original_price' => 65.00,
                'discount_percentage' => 15,
                'color' => 'Multicolor',
                'occasion' => 'Celebración',
                'images' => json_encode(['/img/catalogo/imagencatalogo6.webp']),
                'rating' => 4.6,
                'reviews_count' => 14,
                'stock' => 25,
                'is_active' => true,
                'is_featured' => false,
                'is_on_sale' => true,
                'views' => 0,
                'sort_order' => 2,
                'metadata' => json_encode(['season' => 'Primavera'])
            ],
            [
                'category_id' => $tropicales,
                'name' => 'Orquídeas Exóticas',
                'slug' => 'orquideas-exoticas',
                'description' => 'Hermosas orquídeas tropicales de colores vibrantes. Perfectas para decoración moderna y elegante.',
                'short_description' => 'Orquídeas tropicales de belleza excepcional',
                'price' => 85.00,
                'original_price' => null,
                'discount_percentage' => 0,
                'color' => 'Morado',
                'occasion' => 'Decoración',
                'images' => json_encode(['/img/catalogo/imagencatalogo7.webp', '/img/catalogo/imagencatalogo8.webp']),
                'rating' => 4.9,
                'reviews_count' => 8,
                'stock' => 15,
                'is_active' => true,
                'is_featured' => false,
                'is_on_sale' => false,
                'views' => 0,
                'sort_order' => 1,
                'metadata' => json_encode(['origin' => 'Importadas', 'care_level' => 'Intermedio'])
            ],
            [
                'category_id' => $tropicales,
                'name' => 'Flores Tropicales Mix',
                'slug' => 'flores-tropicales-mix',
                'description' => 'Mezcla exótica de flores tropicales en colores vibrantes. Traen el paraíso a tu hogar.',
                'short_description' => 'Mix exótico de flores tropicales vibrantes',
                'price' => 68.00,
                'original_price' => 78.00,
                'discount_percentage' => 13,
                'color' => 'Multicolor',
                'occasion' => 'Decoración',
                'images' => json_encode(['/img/catalogo/imagencatalogo9.webp']),
                'rating' => 4.5,
                'reviews_count' => 12,
                'stock' => 18,
                'is_active' => true,
                'is_featured' => true,
                'is_on_sale' => true,
                'views' => 0,
                'sort_order' => 2,
                'metadata' => json_encode(['exotic' => true])
            ],
            [
                'category_id' => $plantas,
                'name' => 'Planta Monstera Premium',
                'slug' => 'planta-monstera-premium',
                'description' => 'Hermosa planta Monstera de interior, perfecta para decorar cualquier espacio con estilo moderno.',
                'short_description' => 'Planta decorativa de interior de fácil cuidado',
                'price' => 95.00,
                'original_price' => null,
                'discount_percentage' => 0,
                'color' => 'Verde',
                'occasion' => 'Decoración',
                'images' => json_encode(['/img/catalogo/imagencatalogo10.webp']),
                'rating' => 4.7,
                'reviews_count' => 16,
                'stock' => 12,
                'is_active' => true,
                'is_featured' => false,
                'is_on_sale' => false,
                'views' => 0,
                'sort_order' => 1,
                'metadata' => json_encode(['pot_included' => true, 'light_requirements' => 'Luz indirecta'])
            ],
            [
                'category_id' => $plantas,
                'name' => 'Jardín Miniatura',
                'slug' => 'jardin-miniatura',
                'description' => 'Encantador jardín miniatura con suculentas y plantas pequeñas. Perfecto para espacios reducidos.',
                'short_description' => 'Jardín miniatura con suculentas',
                'price' => 42.00,
                'original_price' => null,
                'discount_percentage' => 0,
                'color' => 'Verde',
                'occasion' => 'Decoración',
                'images' => json_encode(['/img/catalogo/imagencatalogo11.webp', '/img/catalogo/imagencatalogo12.webp']),
                'rating' => 4.4,
                'reviews_count' => 9,
                'stock' => 20,
                'is_active' => true,
                'is_featured' => false,
                'is_on_sale' => false,
                'views' => 0,
                'sort_order' => 2,
                'metadata' => json_encode(['low_maintenance' => true])
            ]
        ];

        foreach ($flowers as $flower) {
            Flower::create($flower);
        }

        $this->command->info('Flowers with real images created successfully!');
    }
}
