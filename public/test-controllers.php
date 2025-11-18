<?php
// Verificación específica de los controladores que fallan
// Colocar en: public_html/api/public/test-controllers.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Si es OPTIONS (preflight), responder directamente
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

echo "=== TEST DE CONTROLADORES ESPECÍFICOS ===\n\n";

try {
    // Cargar Laravel
    if (file_exists('../vendor/autoload.php')) {
        require_once '../vendor/autoload.php';

        if (file_exists('../bootstrap/app.php')) {
            $app = require_once '../bootstrap/app.php';

            // Intentar inicializar la aplicación
            $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
            $kernel->bootstrap();

            echo "✅ Laravel cargado correctamente\n\n";

            // Test 1: Verificar CategoryController
            echo "📁 TEST CategoryController:\n";
            if (class_exists('App\\Http\\Controllers\\Api\\CategoryController')) {
                echo "   ✅ CategoryController existe\n";

                // Verificar método index
                $controller = new App\Http\Controllers\Api\CategoryController();
                if (method_exists($controller, 'index')) {
                    echo "   ✅ Método index existe\n";
                } else {
                    echo "   ❌ Método index no existe\n";
                }
            } else {
                echo "   ❌ CategoryController no encontrado\n";
            }

            // Test 2: Verificar FlowerController
            echo "\n🌸 TEST FlowerController:\n";
            if (class_exists('App\\Http\\Controllers\\Api\\FlowerController')) {
                echo "   ✅ FlowerController existe\n";

                // Verificar método onSale
                $controller = new App\Http\Controllers\Api\FlowerController();
                if (method_exists($controller, 'onSale')) {
                    echo "   ✅ Método onSale existe\n";
                } else {
                    echo "   ❌ Método onSale no existe\n";
                }
            } else {
                echo "   ❌ FlowerController no encontrado\n";
            }

            // Test 3: Verificar modelos
            echo "\n💾 TEST Modelos:\n";
            if (class_exists('App\\Models\\Category')) {
                echo "   ✅ Modelo Category existe\n";

                // Test de conexión a BD
                try {
                    $count = App\Models\Category::count();
                    echo "   ✅ Categorías en BD: $count\n";
                } catch (Exception $e) {
                    echo "   ❌ Error accediendo a BD: " . $e->getMessage() . "\n";
                }
            } else {
                echo "   ❌ Modelo Category no encontrado\n";
            }

            if (class_exists('App\\Models\\Flower')) {
                echo "   ✅ Modelo Flower existe\n";

                try {
                    $count = App\Models\Flower::count();
                    echo "   ✅ Flores en BD: $count\n";
                } catch (Exception $e) {
                    echo "   ❌ Error accediendo a BD: " . $e->getMessage() . "\n";
                }
            } else {
                echo "   ❌ Modelo Flower no encontrado\n";
            }

            // Test 4: Verificar rutas
            echo "\n🛣️ TEST Rutas registradas:\n";
            $routes = app('router')->getRoutes();
            $api_routes = [];

            foreach ($routes as $route) {
                $uri = $route->uri();
                if (strpos($uri, 'api/v1/catalog') !== false) {
                    $api_routes[] = $route->methods()[0] . ' ' . $uri;
                }
            }

            if (count($api_routes) > 0) {
                echo "   ✅ Rutas de catálogo encontradas:\n";
                foreach ($api_routes as $route) {
                    echo "      - $route\n";
                }
            } else {
                echo "   ❌ No se encontraron rutas de catálogo\n";
            }

        } else {
            echo "❌ bootstrap/app.php no encontrado\n";
        }
    } else {
        echo "❌ vendor/autoload.php no encontrado\n";
    }

} catch (Exception $e) {
    echo "❌ ERROR CRÍTICO: " . $e->getMessage() . "\n";
    echo "📍 Archivo: " . $e->getFile() . "\n";
    echo "📍 Línea: " . $e->getLine() . "\n";
    echo "📍 Trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN DEL TEST ===\n";
echo "🕒 " . date('Y-m-d H:i:s') . "\n";
?>
