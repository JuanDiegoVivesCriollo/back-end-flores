<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DeliveryDistrictsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $districts = [
            // Lima Centro
            ['name' => 'Lima Cercado', 'cost' => 20, 'zone' => 'Centro'],
            ['name' => 'Breña', 'cost' => 20, 'zone' => 'Centro'],
            ['name' => 'La Victoria', 'cost' => 20, 'zone' => 'Centro'],
            ['name' => 'Rímac', 'cost' => 20, 'zone' => 'Centro'],

            // Lima Norte
            ['name' => 'Ancón', 'cost' => 69, 'zone' => 'Norte'],
            ['name' => 'Carabayllo', 'cost' => 50, 'zone' => 'Norte'],
            ['name' => 'Comas', 'cost' => 35, 'zone' => 'Norte'],
            ['name' => 'Independencia', 'cost' => 30, 'zone' => 'Norte'],
            ['name' => 'Los Olivos', 'cost' => 25, 'zone' => 'Norte'],
            ['name' => 'Puente Piedra', 'cost' => 35, 'zone' => 'Norte'],
            ['name' => 'San Martín de Porres', 'cost' => 25, 'zone' => 'Norte'],
            ['name' => 'Santa Rosa', 'cost' => 26, 'zone' => 'Norte'],

            // Lima Este
            ['name' => 'Ate', 'cost' => 50, 'zone' => 'Este'],
            ['name' => 'Canto Rey', 'cost' => 0, 'zone' => 'Este'],
            ['name' => 'Chaclacayo', 'cost' => 70, 'zone' => 'Este'],
            ['name' => 'Cieneguilla', 'cost' => 90, 'zone' => 'Este'],
            ['name' => 'El Agustino', 'cost' => 20, 'zone' => 'Este'],
            ['name' => 'La Molina', 'cost' => 35, 'zone' => 'Este'],
            ['name' => 'Lurigancho - Chosica', 'cost' => 40, 'zone' => 'Este'],
            ['name' => 'San Juan de Lurigancho', 'cost' => 20, 'zone' => 'Este'],
            ['name' => 'Santa Anita', 'cost' => 25, 'zone' => 'Este'],

            // Lima Sur
            ['name' => 'Chorrillos', 'cost' => 30, 'zone' => 'Sur'],
            ['name' => 'Lurín', 'cost' => 55, 'zone' => 'Sur'],
            ['name' => 'Pachacámac', 'cost' => 45, 'zone' => 'Sur'],
            ['name' => 'Pucusana', 'cost' => 120, 'zone' => 'Sur'],
            ['name' => 'Punta Hermosa', 'cost' => 140, 'zone' => 'Sur'],
            ['name' => 'Punta Negra', 'cost' => 100, 'zone' => 'Sur'],
            ['name' => 'San Bartolo', 'cost' => 80, 'zone' => 'Sur'],
            ['name' => 'San Juan de Miraflores', 'cost' => 40, 'zone' => 'Sur'],
            ['name' => 'Villa El Salvador', 'cost' => 45, 'zone' => 'Sur'],
            ['name' => 'Villa María del Triunfo', 'cost' => 60, 'zone' => 'Sur'],

            // Lima Oeste
            ['name' => 'Barranco', 'cost' => 25, 'zone' => 'Oeste'],
            ['name' => 'Jesús María', 'cost' => 20, 'zone' => 'Oeste'],
            ['name' => 'La Magdalena del Mar', 'cost' => 20, 'zone' => 'Oeste'],
            ['name' => 'Lince', 'cost' => 25, 'zone' => 'Oeste'],
            ['name' => 'Miraflores', 'cost' => 26, 'zone' => 'Oeste'],
            ['name' => 'Pueblo Libre', 'cost' => 20, 'zone' => 'Oeste'],
            ['name' => 'San Isidro', 'cost' => 25, 'zone' => 'Oeste'],
            ['name' => 'San Miguel', 'cost' => 20, 'zone' => 'Oeste'],
            ['name' => 'Surquillo', 'cost' => 20, 'zone' => 'Oeste'],
            ['name' => 'Santiago de Surco', 'cost' => 25, 'zone' => 'Oeste'],
            ['name' => 'San Borja', 'cost' => 20, 'zone' => 'Oeste'],

            // Callao
            ['name' => 'Bellavista', 'cost' => 40, 'zone' => 'Callao'],
            ['name' => 'Callao', 'cost' => 40, 'zone' => 'Callao'],
            ['name' => 'Carmen de la Legua Reynoso', 'cost' => 30, 'zone' => 'Callao'],
            ['name' => 'La Perla', 'cost' => 30, 'zone' => 'Callao'],
            ['name' => 'La Punta', 'cost' => 50, 'zone' => 'Callao'],
            ['name' => 'Mi Perú', 'cost' => 36, 'zone' => 'Callao'],
            ['name' => 'Ventanilla', 'cost' => 50, 'zone' => 'Callao'],
        ];

        foreach ($districts as $district) {
            // Verificar si el distrito ya existe antes de insertarlo
            $exists = DB::table('delivery_districts')
                       ->where('name', $district['name'])
                       ->exists();

            if (!$exists) {
                DB::table('delivery_districts')->insert([
                    'name' => $district['name'],
                    'slug' => Str::slug($district['name']),
                    'shipping_cost' => $district['cost'],
                    'zone' => $district['zone'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                echo "✅ Distrito agregado: {$district['name']} - Costo: S/ {$district['cost']}\n";
            } else {
                echo "⚠️ Distrito ya existe: {$district['name']}\n";
            }
        }

        echo "✅ " . count($districts) . " distritos de entrega creados exitosamente.\n";
    }
}
