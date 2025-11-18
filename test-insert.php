<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\WebAuthnCredential;
use App\Models\User;

try {
    $user = User::first();
    echo 'User found: ' . $user->email . PHP_EOL;

    $cred = WebAuthnCredential::create([
        'user_id' => $user->id,
        'credential_id' => 'test-credential-' . time(),
        'public_key' => 'test-public-key',
        'counter' => 0,
        'device_name' => 'Test Device',
        'device_type' => 'fingerprint',
    ]);

    echo 'Credential created with ID: ' . $cred->id . PHP_EOL;

    // Delete test credential
    $cred->delete();
    echo 'Test credential deleted successfully' . PHP_EOL;

} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
    echo 'File: ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL;
}
