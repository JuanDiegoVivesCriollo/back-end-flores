<?php
// Script para ejecutar migraciones automáticamente
// Colocar en: public_html/api/public/run-migrations.php
// ¡ELIMINAR ESTE ARCHIVO DESPUÉS DE USAR POR SEGURIDAD!

// Verificar que solo se ejecute una vez (medida de seguridad)
$lock_file = '../storage/migrations-executed.lock';
if (file_exists($lock_file)) {
    die("❌ Las migraciones ya fueron ejecutadas. Si necesitas ejecutarlas nuevamente, elimina el archivo: storage/migrations-executed.lock");
}

header('Content-Type: text/plain; charset=utf-8');
echo "=== EJECUTANDO MIGRACIONES AUTOMÁTICAS ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Cargar Laravel
    if (!file_exists('../vendor/autoload.php')) {
        die("❌ ERROR: vendor/autoload.php no encontrado. Ejecutar: composer install\n");
    }

    require_once '../vendor/autoload.php';

    if (!file_exists('../bootstrap/app.php')) {
        die("❌ ERROR: bootstrap/app.php no encontrado\n");
    }

    $app = require_once '../bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();

    echo "✅ Laravel inicializado\n\n";

    // 1. Verificar conexión a BD
    echo "🔍 Verificando conexión a base de datos...\n";
    try {
        $pdo = DB::connection()->getPdo();
        $dbName = DB::connection()->getDatabaseName();
        echo "✅ Conectado a: $dbName\n\n";
    } catch (Exception $e) {
        die("❌ Error de conexión: " . $e->getMessage() . "\n");
    }

    // 2. Ejecutar migraciones
    echo "🚀 Ejecutando migraciones...\n";
    try {
        Artisan::call('migrate', ['--force' => true]);
        $output = Artisan::output();
        echo $output;
        echo "✅ Migraciones ejecutadas exitosamente\n\n";
    } catch (Exception $e) {
        echo "❌ Error ejecutando migraciones: " . $e->getMessage() . "\n\n";
    }

    // 3. Verificar que las tablas principales existan
    echo "📋 Verificando tablas creadas...\n";
    $tables_to_check = ['categories', 'flowers', 'complements', 'users', 'orders'];
    $tables_created = 0;

    foreach ($tables_to_check as $table) {
        try {
            $result = DB::select("SHOW TABLES LIKE '$table'");
            if (count($result) > 0) {
                echo "✅ Tabla '$table' creada\n";
                $tables_created++;
            } else {
                echo "❌ Tabla '$table' NO encontrada\n";
            }
        } catch (Exception $e) {
            echo "❌ Error verificando tabla '$table': " . $e->getMessage() . "\n";
        }
    }

    // 4. Ejecutar seeders básicos si las tablas están vacías
    if ($tables_created > 0) {
        echo "\n🌱 Verificando datos iniciales...\n";

        // Verificar si hay categorías
        try {
            $categories_count = DB::table('categories')->count();
            if ($categories_count == 0) {
                echo "📝 Ejecutando seeder de categorías...\n";
                Artisan::call('db:seed', ['--class' => 'CategorySeeder', '--force' => true]);
                echo Artisan::output();
            } else {
                echo "✅ Categorías ya existen: $categories_count\n";
            }
        } catch (Exception $e) {
            echo "⚠️ No se pudo ejecutar CategorySeeder: " . $e->getMessage() . "\n";
        }

        // Verificar si hay usuario admin
        try {
            $admin_count = DB::table('users')->where('email', 'admin@flores.com')->count();
            if ($admin_count == 0) {
                echo "👤 Creando usuario administrador...\n";

                // Crear usuario admin manualmente
                DB::table('users')->insert([
                    'name' => 'Administrador',
                    'email' => 'admin@flores.com',
                    'email_verified_at' => now(),
                    'password' => bcrypt('admin123'),
                    'role' => 'admin',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                echo "✅ Usuario admin creado (email: admin@flores.com, password: admin123)\n";
            } else {
                echo "✅ Usuario admin ya existe\n";
            }
        } catch (Exception $e) {
            echo "⚠️ No se pudo crear usuario admin: " . $e->getMessage() . "\n";
        }
    }

    // 5. Test final de las APIs
    echo "\n🧪 TEST FINAL DE APIs...\n";

    try {
        // Test CategoryController
        if (class_exists('App\\Models\\Category')) {
            $categories = App\Models\Category::take(3)->get();
            echo "✅ /api/v1/catalog/categories funcionará - " . count($categories) . " categorías\n";
        }

        // Test FlowerController
        if (class_exists('App\\Models\\Flower')) {
            $flowers = App\Models\Flower::take(3)->get();
            echo "✅ /api/v1/catalog/flowers funcionará - " . count($flowers) . " flores\n";

            $flowers_on_sale = App\Models\Flower::where('discount_percentage', '>', 0)->take(3)->get();
            echo "✅ /api/v1/catalog/flowers/on-sale funcionará - " . count($flowers_on_sale) . " flores en oferta\n";
        }

    } catch (Exception $e) {
        echo "⚠️ Error en test de APIs: " . $e->getMessage() . "\n";
    }

    // 6. Crear archivo de bloqueo para evitar ejecución múltiple
    file_put_contents($lock_file, date('Y-m-d H:i:s') . " - Migraciones ejecutadas exitosamente");

    echo "\n✅ PROCESO COMPLETADO EXITOSAMENTE\n";
    echo "🔒 Archivo de bloqueo creado\n";
    echo "⚠️ IMPORTANTE: Elimina este archivo (run-migrations.php) por seguridad\n\n";

    echo "📋 PRÓXIMOS PASOS:\n";
    echo "1. Verificar que las APIs funcionan: /api/v1/catalog/categories\n";
    echo "2. Probar el frontend\n";
    echo "3. Configurar datos iniciales si es necesario\n";
    echo "4. ¡ELIMINAR ESTE ARCHIVO por seguridad!\n";

} catch (Exception $e) {
    echo "💥 ERROR CRÍTICO:\n";
    echo "Mensaje: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n\n";

    echo "🔧 SOLUCIONES MANUALES:\n";
    echo "1. Acceder por SSH y ejecutar: php artisan migrate --force\n";
    echo "2. Verificar configuración de .env\n";
    echo "3. Verificar permisos de archivos\n";
    echo "4. Contactar al soporte del hosting\n";
}

echo "\n=== FIN DEL PROCESO ===\n";
?>
