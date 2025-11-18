<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\WebAuthnCredential;

$creds = WebAuthnCredential::all();
echo "Total credentials: " . $creds->count() . PHP_EOL;

foreach ($creds as $cred) {
    echo "\nCredential ID: " . $cred->id . PHP_EOL;
    echo "User ID: " . $cred->user_id . PHP_EOL;
    echo "Device: " . $cred->device_name . PHP_EOL;
    echo "Counter: " . $cred->counter . PHP_EOL;
    echo "Created: " . $cred->created_at . PHP_EOL;
}
