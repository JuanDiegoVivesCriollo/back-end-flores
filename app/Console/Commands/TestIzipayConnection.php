<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Payment\IzipayService;
use Illuminate\Support\Facades\Http;

class TestIzipayConnection extends Command
{
    protected $signature = 'izipay:test-connection';
    protected $description = 'Test Izipay API connection and configuration';

    public function handle()
    {
        $this->info('=== TESTING IZIPAY CONNECTION ===');
        $this->newLine();

        // 1. Verificar configuración
        $this->info('1. Checking configuration...');
        $config = config('services.izipay');

        $this->table(['Setting', 'Value'], [
            ['Environment', $config['environment'] ?? 'NOT SET'],
            ['Shop ID', $config['shop_id'] ?? 'NOT SET'],
            ['Username', $config['username'] ?? 'NOT SET'],
            ['Test Password', !empty($config['test_password']) ? 'SET' : 'NOT SET'],
            ['Prod Password', !empty($config['prod_password']) ? 'SET' : 'NOT SET'],
            ['Public Key', !empty($config['prod_public_key']) ? 'SET' : 'NOT SET'],
        ]);

        // 2. Probar conexión básica
        $this->newLine();
        $this->info('2. Testing basic connection...');

        $environment = $config['environment'] ?? 'test';
        $isTest = ($environment === 'test' || $environment === 'TEST');

        $url = $isTest
            ? "https://sandbox-api-pw.izipay.pe/api-payment/V4/Charge/CreatePayment"
            : "https://api.micuentaweb.pe/api-payment/V4/Charge/CreatePayment";

        $this->info("Testing URL: $url");

        $username = $config['username'] ?? $config['shop_id'];
        $password = $isTest ? $config['test_password'] : $config['prod_password'];

        try {
            $testData = [
                'amount' => 100, // 1.00 PEN en centavos
                'currency' => 'PEN',
                'orderId' => 'TEST-' . time(),
                'customer' => [
                    'email' => 'test@example.com',
                    'billingDetails' => [
                        'firstName' => 'Test',
                        'lastName' => 'User',
                        'phoneNumber' => '999999999',
                        'identityType' => 'DNI',
                        'identityCode' => '12345678',
                        'address' => 'Test Address',
                        'country' => 'PE',
                        'city' => 'Lima',
                        'state' => 'Lima',
                        'zipCode' => '15000',
                    ]
                ]
            ];

            $response = Http::timeout(60)
                ->retry(2, 2000)
                ->withHeaders([
                    'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
                    'Content-Type' => 'application/json'
                ])
                ->post($url, $testData);

            if ($response->successful()) {
                $this->info('✅ Connection successful!');
                $this->info('Response status: ' . $response->status());

                $data = $response->json();
                if (isset($data['answer']['formToken'])) {
                    $this->info('✅ Form token received successfully');
                    $this->info('Token length: ' . strlen($data['answer']['formToken']));
                } else {
                    $this->warn('⚠️ No form token in response');
                    $this->line('Response: ' . $response->body());
                }

            } else {
                $this->error('❌ Connection failed');
                $this->error('Status: ' . $response->status());
                $this->error('Response: ' . $response->body());
            }

        } catch (\Exception $e) {
            $this->error('❌ Exception occurred: ' . $e->getMessage());

            // Sugerencias específicas
            if (strpos($e->getMessage(), 'timeout') !== false) {
                $this->warn('💡 Suggestion: Server might be blocking outgoing connections');
                $this->warn('   Check with hosting provider about firewall rules');
            }

            if (strpos($e->getMessage(), 'SSL') !== false) {
                $this->warn('💡 Suggestion: SSL/TLS verification issue');
                $this->warn('   Check server SSL configuration');
            }
        }

        // 3. Verificar desde IzipayService
        $this->newLine();
        $this->info('3. Testing through IzipayService...');

        try {
            $service = new IzipayService();
            $this->info('✅ IzipayService instantiated successfully');
        } catch (\Exception $e) {
            $this->error('❌ Failed to create IzipayService: ' . $e->getMessage());
        }

        $this->newLine();
        $this->info('=== TEST COMPLETED ===');
    }
}
