<?php
// Test simple de conectividad API
// Colocar en: public_html/api/public/test-api.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Si es OPTIONS (preflight), responder directamente
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$response = [
    'status' => 'success',
    'message' => 'API Backend está funcionando',
    'timestamp' => date('Y-m-d H:i:s'),
    'server' => [
        'php_version' => phpversion(),
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
        'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'Unknown'
    ],
    'request' => [
        'method' => $_SERVER['REQUEST_METHOD'],
        'uri' => $_SERVER['REQUEST_URI'] ?? 'Unknown',
        'host' => $_SERVER['HTTP_HOST'] ?? 'Unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
    ]
];

echo json_encode($response, JSON_PRETTY_PRINT);
?>
