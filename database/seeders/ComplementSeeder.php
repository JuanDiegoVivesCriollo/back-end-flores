<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Complement;

class ComplementSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        $complements = [
            // Globos
            [
                'name' => 'Globo Corazón Rojo',
                'description' => 'Hermoso globo en forma de corazón ideal para expresar amor y cariño. Perfecto para San Valentín, aniversarios o cualquier ocasión romántica.',
                'short_description' => 'Globo romántico en forma de corazón',
                'price' => 15.00,
                'type' => 'globos',
                'color' => 'Rojo',
                'size' => 'Mediano',
                'images' => ['/img/catalogo/imagencatalogo1.webp'],
                'stock' => 50,
                'is_featured' => true,
                'is_active' => true,
                'sort_order' => 1
            ],
            [
                'name' => 'Globo Número Dorado',
                'description' => 'Elegante globo en forma de número dorado para celebrar cumpleaños, aniversarios y ocasiones especiales con estilo.',
                'short_description' => 'Globo número personalizado dorado',
                'price' => 20.00,
                'type' => 'globos',
                'color' => 'Dorado',
                'size' => 'Grande',
                'images' => ['/img/catalogo/imagencatalogo2.webp'],
                'stock' => 30,
                'is_active' => true,
                'sort_order' => 2
            ],
            [
                'name' => 'Globos Arcoíris',
                'description' => 'Set de globos multicolores que crean un hermoso arcoíris. Perfectos para celebraciones infantiles y eventos alegres.',
                'short_description' => 'Set de globos multicolores',
                'price' => 25.00,
                'type' => 'globos',
                'color' => 'Multicolor',
                'size' => 'Set',
                'images' => ['/img/catalogo/imagencatalogo3.webp'],
                'stock' => 25,
                'is_featured' => true,
                'is_active' => true,
                'sort_order' => 3
            ],

            // Peluches
            [
                'name' => 'Osito de Peluche Clásico',
                'description' => 'Adorable osito de peluche de calidad premium, suave y abrazable. El regalo perfecto para demostrar cariño y ternura.',
                'short_description' => 'Osito clásico suave y tierno',
                'price' => 35.00,
                'type' => 'peluches',
                'color' => 'Marrón',
                'size' => 'Mediano',
                'images' => ['/img/catalogo/imagencatalogo4.webp'],
                'stock' => 40,
                'is_featured' => true,
                'is_active' => true,
                'sort_order' => 4
            ],
            [
                'name' => 'Conejo de Peluche Rosa',
                'description' => 'Tierno conejito de peluche en color rosa pastel. Ideal para bebés y niños pequeños, con materiales hipoalergénicos.',
                'short_description' => 'Conejito rosa para bebés',
                'price' => 28.00,
                'type' => 'peluches',
                'color' => 'Rosa',
                'size' => 'Pequeño',
                'images' => ['/img/catalogo/imagencatalogo5.webp'],
                'stock' => 35,
                'is_active' => true,
                'sort_order' => 5
            ],
            [
                'name' => 'Peluche Unicornio Gigante',
                'description' => 'Majestuoso unicornio de peluche de gran tamaño. Con cuerno dorado y melena multicolor, perfecto para decorar y abrazar.',
                'short_description' => 'Unicornio gigante multicolor',
                'price' => 65.00,
                'original_price' => 80.00,
                'discount_percentage' => 19,
                'type' => 'peluches',
                'color' => 'Multicolor',
                'size' => 'Grande',
                'images' => ['/img/catalogo/imagencatalogo6.webp'],
                'stock' => 15,
                'is_featured' => true,
                'is_on_sale' => true,
                'is_active' => true,
                'sort_order' => 6
            ],

            // Chocolates
            [
                'name' => 'Caja de Chocolates Gourmet',
                'description' => 'Exquisita selección de chocolates artesanales con diferentes rellenos: trufa, caramelo, frutos secos y más sabores únicos.',
                'short_description' => 'Chocolates artesanales premium',
                'price' => 45.00,
                'type' => 'chocolates',
                'brand' => 'Chocolatería Premium',
                'color' => 'Variado',
                'images' => ['/img/catalogo/imagencatalogo7.webp'],
                'stock' => 60,
                'is_featured' => true,
                'is_active' => true,
                'sort_order' => 7
            ],
            [
                'name' => 'Bombones de Chocolate Belga',
                'description' => 'Deliciosos bombones de chocolate belga de la más alta calidad. Presentación elegante perfecta para regalar.',
                'short_description' => 'Bombones belgas de lujo',
                'price' => 38.00,
                'type' => 'chocolates',
                'brand' => 'Belgian Delights',
                'color' => 'Chocolate',
                'images' => ['/img/catalogo/imagencatalogo8.webp'],
                'stock' => 45,
                'is_active' => true,
                'sort_order' => 8
            ],
            [
                'name' => 'Chocolate Corazón Rojo',
                'description' => 'Romántico chocolate en forma de corazón envuelto en papel rojo. Ideal para San Valentín y ocasiones especiales.',
                'short_description' => 'Chocolate romántico forma corazón',
                'price' => 22.00,
                'original_price' => 25.00,
                'discount_percentage' => 12,
                'type' => 'chocolates',
                'brand' => 'Amor Dulce',
                'color' => 'Rojo',
                'images' => ['/img/catalogo/imagencatalogo9.webp'],
                'stock' => 50,
                'is_on_sale' => true,
                'is_active' => true,
                'sort_order' => 9
            ],
            [
                'name' => 'Trufas de Chocolate Negro',
                'description' => 'Sofisticadas trufas de chocolate negro 70% cacao con un toque de licor. Para paladares exigentes y amantes del chocolate intenso.',
                'short_description' => 'Trufas chocolate negro premium',
                'price' => 32.00,
                'type' => 'chocolates',
                'brand' => 'Cacao Noir',
                'color' => 'Negro',
                'images' => ['/img/catalogo/imagencatalogo10.webp'],
                'stock' => 30,
                'is_featured' => true,
                'is_active' => true,
                'sort_order' => 10
            ]
        ];

        foreach ($complements as $complement) {
            Complement::create($complement);
        }
    }
}
