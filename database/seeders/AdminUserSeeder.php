<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        User::create([
            'name' => 'Administrador',
            'email' => 'admin@floresydetalleslima.com',
            'password' => Hash::make('admin123'),
            'phone' => '+51 999 999 999',
            'role' => 'admin',
            'address' => 'Lima, Perú',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // Create demo customer
        User::create([
            'name' => 'Cliente Demo',
            'email' => 'cliente@demo.com',
            'password' => Hash::make('demo123'),
            'phone' => '+51 988 888 888',
            'address' => 'Jr. Demo 123, Huancayo',
            'role' => 'user', // Changed from 'customer' to 'user'
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->command->info('Admin user and demo customer created successfully!');
        $this->command->info('Admin credentials: admin@floresydetalleslima.com / admin123');
        $this->command->info('Demo customer: cliente@demo.com / demo123');
    }
}
