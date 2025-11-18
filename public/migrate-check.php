<?php
// Script para verificar y ejecutar migraciones
// Colocar en: public_html/api/public/migrate-check.php

header('Content-Type: text/plain; charset=utf-8');
echo "=== VERIFICACIÓN Y MIGRACIÓN DE BASE DE DATOS ===\n";
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

    echo "✅ Laravel cargado correctamente\n\n";

    // 1. Verificar conexión a base de datos
    echo "🔍 VERIFICANDO CONEXIÓN A BASE DE DATOS:\n";
    try {
        $pdo = DB::connection()->getPdo();
        echo "✅ Conexión exitosa\n";

        $dbName = DB::connection()->getDatabaseName();
        echo "✅ Base de datos: $dbName\n\n";

    } catch (Exception $e) {
        die("❌ ERROR DE CONEXIÓN: " . $e->getMessage() . "\n");
    }

    // 2. Verificar si existen las tablas principales
    echo "📋 VERIFICANDO TABLAS EXISTENTES:\n";
    $required_tables = [
        'migrations',
        'users',
        'categories',
        'flowers',
        'complements',
        'orders',
        'payments'
    ];

    $existing_tables = [];
    $missing_tables = [];

    foreach ($required_tables as $table) {
        try {
            $result = DB::select("SHOW TABLES LIKE '$table'");
            if (count($result) > 0) {
                $existing_tables[] = $table;
                echo "✅ Tabla '$table' existe\n";

                // Contar registros para algunas tablas importantes
                if (in_array($table, ['categories', 'flowers', 'users'])) {
                    $count = DB::table($table)->count();
                    echo "   📊 Registros: $count\n";
                }
            } else {
                $missing_tables[] = $table;
                echo "❌ Tabla '$table' NO existe\n";
            }
        } catch (Exception $e) {
            $missing_tables[] = $table;
            echo "❌ Error verificando tabla '$table': " . $e->getMessage() . "\n";
        }
    }

    echo "\n📊 RESUMEN DE TABLAS:\n";
    echo "✅ Existentes: " . count($existing_tables) . " de " . count($required_tables) . "\n";
    echo "❌ Faltantes: " . count($missing_tables) . "\n\n";

    // 3. Si faltan tablas, mostrar comandos para ejecutar
    if (count($missing_tables) > 0) {
        echo "🚨 ACCIÓN REQUERIDA:\n";
        echo "Faltan tablas importantes. Ejecutar los siguientes comandos:\n\n";

        echo "# Via SSH o Terminal del servidor:\n";
        echo "cd /public_html/api/\n";
        echo "php artisan migrate --force\n";
        echo "php artisan db:seed --force\n\n";

        echo "# Via File Manager (crear archivo run-migrations.php):\n";
        echo "<?php\n";
        echo "require_once 'vendor/autoload.php';\n";
        echo "\$app = require_once 'bootstrap/app.php';\n";
        echo "\$kernel = \$app->make(Illuminate\\Contracts\\Console\\Kernel::class);\n";
        echo "\$kernel->bootstrap();\n";
        echo "Artisan::call('migrate', ['--force' => true]);\n";
        echo "Artisan::call('db:seed', ['--force' => true]);\n";
        echo "echo 'Migraciones ejecutadas';\n";
        echo "?>\n\n";

    } else {
        echo "✅ TODAS LAS TABLAS EXISTEN!\n\n";
    }

    // 4. Verificar migraciones pendientes
    echo "🔄 VERIFICANDO ESTADO DE MIGRACIONES:\n";
    try {
        // Verificar tabla migrations
        $migrations = DB::select("SELECT migration FROM migrations ORDER BY batch DESC LIMIT 10");

        if (count($migrations) > 0) {
            echo "✅ Migraciones ejecutadas (últimas 10):\n";
            foreach ($migrations as $migration) {
                echo "   - " . $migration->migration . "\n";
            }
        } else {
            echo "⚠️ No se encontraron migraciones ejecutadas\n";
        }

    } catch (Exception $e) {
        echo "❌ Error verificando migraciones: " . $e->getMessage() . "\n";
    }

    // 5. Test específico de los controladores que fallan
    echo "\n🧪 TEST DE FUNCIONALIDAD:\n";

    if (in_array('categories', $existing_tables)) {
        try {
            $categories = App\Models\Category::take(5)->get(['id', 'name', 'slug']);
            echo "✅ CategoryController funcionará - " . count($categories) . " categorías disponibles\n";

            foreach ($categories as $cat) {
                echo "   - {$cat->id}: {$cat->name} ({$cat->slug})\n";
            }
        } catch (Exception $e) {
            echo "❌ Error en CategoryController: " . $e->getMessage() . "\n";
        }
    }

    if (in_array('flowers', $existing_tables)) {
        try {
            $flowers_on_sale = App\Models\Flower::where('discount_percentage', '>', 0)
                                                ->take(5)
                                                ->get(['id', 'name', 'price', 'discount_percentage']);
            echo "✅ FlowerController (on-sale) funcionará - " . count($flowers_on_sale) . " flores en oferta\n";

            foreach ($flowers_on_sale as $flower) {
                $final_price = $flower->price * (1 - $flower->discount_percentage / 100);
                echo "   - {$flower->name}: \${$flower->price} -> \${$final_price}\n";
            }
        } catch (Exception $e) {
            echo "❌ Error en FlowerController: " . $e->getMessage() . "\n";
        }
    }

    // 6. Verificar datos de prueba
    echo "\n📊 DATOS DISPONIBLES:\n";
    foreach (['categories', 'flowers', 'complements', 'users'] as $table) {
        if (in_array($table, $existing_tables)) {
            try {
                $count = DB::table($table)->count();
                echo "✅ $table: $count registros\n";

                if ($count === 0 && $table !== 'users') {
                    echo "   ⚠️ Tabla vacía - considerar ejecutar seeders\n";
                }
            } catch (Exception $e) {
                echo "❌ Error contando $table: " . $e->getMessage() . "\n";
            }
        }
    }

} catch (Exception $e) {
    echo "💥 ERROR CRÍTICO:\n";
    echo "Mensaje: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n\n";

    echo "🔧 POSIBLES SOLUCIONES:\n";
    echo "1. Verificar archivo .env\n";
    echo "2. Ejecutar: composer install\n";
    echo "3. Ejecutar: php artisan key:generate\n";
    echo "4. Ejecutar: php artisan migrate --force\n";
    echo "5. Verificar permisos de storage/ y bootstrap/cache/\n";
}

echo "\n=== FIN DE VERIFICACIÓN ===\n";
?>
