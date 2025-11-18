<?php
// Archivo de diagnóstico para el backend
// Colocar en: public_html/api/public/debug.php

echo "=== DIAGNÓSTICO DEL BACKEND - FLORES D'JAZMIN ===\n\n";

// 1. Verificar PHP
echo "✅ PHP VERSION: " . phpversion() . "\n";

// 2. Verificar extensiones requeridas
$required_extensions = ['pdo', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'curl'];
echo "✅ EXTENSIONES PHP:\n";
foreach ($required_extensions as $ext) {
    $status = extension_loaded($ext) ? '✅' : '❌';
    echo "   $status $ext\n";
}

// 3. Verificar permisos de archivos
echo "\n✅ PERMISOS DE ARCHIVOS:\n";
$files_to_check = [
    '../storage/logs' => 'Logs directory',
    '../storage/framework' => 'Framework directory',
    '../bootstrap/cache' => 'Bootstrap cache',
    '../.env' => 'Environment file'
];

foreach ($files_to_check as $file => $description) {
    if (file_exists($file)) {
        $perms = substr(sprintf('%o', fileperms($file)), -4);
        echo "   ✅ $description: $perms\n";
    } else {
        echo "   ❌ $description: NO EXISTE\n";
    }
}

// 4. Verificar variables de entorno críticas
echo "\n✅ VARIABLES DE ENTORNO:\n";
if (file_exists('../.env')) {
    $env_content = file_get_contents('../.env');
    $env_vars = ['APP_ENV', 'APP_DEBUG', 'DB_CONNECTION', 'DB_HOST', 'DB_DATABASE'];

    foreach ($env_vars as $var) {
        if (strpos($env_content, $var) !== false) {
            preg_match("/^$var=(.*?)$/m", $env_content, $matches);
            $value = isset($matches[1]) ? $matches[1] : 'NO SET';
            // Ocultar valores sensibles
            if (in_array($var, ['DB_PASSWORD'])) {
                $value = str_repeat('*', strlen($value));
            }
            echo "   ✅ $var: $value\n";
        } else {
            echo "   ❌ $var: NO DEFINIDA\n";
        }
    }
} else {
    echo "   ❌ Archivo .env no encontrado\n";
}

// 5. Test de conectividad a base de datos
echo "\n✅ CONECTIVIDAD BASE DE DATOS:\n";
try {
    if (file_exists('../.env')) {
        // Cargar variables de entorno manualmente para el test
        $env_lines = file('../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $env_vars = [];
        foreach ($env_lines as $line) {
            if (strpos($line, '=') !== false && !str_starts_with($line, '#')) {
                [$key, $value] = explode('=', $line, 2);
                $env_vars[trim($key)] = trim($value);
            }
        }

        if (isset($env_vars['DB_CONNECTION']) && $env_vars['DB_CONNECTION'] === 'mysql') {
            $host = $env_vars['DB_HOST'] ?? 'localhost';
            $database = $env_vars['DB_DATABASE'] ?? '';
            $username = $env_vars['DB_USERNAME'] ?? '';
            $password = $env_vars['DB_PASSWORD'] ?? '';

            $pdo = new PDO("mysql:host=$host;dbname=$database", $username, $password);
            echo "   ✅ Conexión MySQL exitosa\n";

            // Test de tabla crítica
            $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
            if ($stmt->rowCount() > 0) {
                echo "   ✅ Tabla 'users' existe\n";
            } else {
                echo "   ❌ Tabla 'users' no existe - ejecutar migraciones\n";
            }

        } else {
            echo "   ⚠️ No es conexión MySQL o no configurada\n";
        }
    }
} catch (Exception $e) {
    echo "   ❌ Error de conexión: " . $e->getMessage() . "\n";
}

// 6. Test de rutas Laravel
echo "\n✅ TEST DE RUTAS LARAVEL:\n";
try {
    // Intentar cargar el autoloader de Laravel
    if (file_exists('../vendor/autoload.php')) {
        echo "   ✅ Autoloader de Laravel encontrado\n";

        // Test básico de carga de clases
        require_once '../vendor/autoload.php';
        echo "   ✅ Autoloader cargado correctamente\n";

    } else {
        echo "   ❌ vendor/autoload.php no encontrado - ejecutar composer install\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error cargando Laravel: " . $e->getMessage() . "\n";
}

// 7. Verificar logs recientes
echo "\n✅ LOGS RECIENTES:\n";
$log_file = '../storage/logs/laravel.log';
if (file_exists($log_file)) {
    $log_content = file_get_contents($log_file);
    $recent_logs = array_slice(explode("\n", $log_content), -10);

    foreach ($recent_logs as $log_line) {
        if (!empty(trim($log_line))) {
            echo "   📝 " . substr($log_line, 0, 100) . "...\n";
        }
    }
} else {
    echo "   ⚠️ No hay archivo de logs disponible\n";
}

echo "\n=== FIN DEL DIAGNÓSTICO ===\n";
echo "📅 Fecha: " . date('Y-m-d H:i:s') . "\n";
echo "🌐 Server: " . $_SERVER['HTTP_HOST'] . "\n";
echo "📂 Path: " . $_SERVER['REQUEST_URI'] . "\n";
?>
