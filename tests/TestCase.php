<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

use Illuminate\Contracts\Console\Kernel;

abstract class TestCase extends BaseTestCase
{
    protected static bool $createdEnvFile = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (empty(config('app.key'))) {
            config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        }

        $manifestPath = public_path('build/manifest.json');

        if (! is_dir(dirname($manifestPath))) {
            mkdir(dirname($manifestPath), 0755, true);
        }

        $manifest = [
            'resources/js/app.js' => [
                'file' => 'assets/app.js',
                'isEntry' => true,
                'src' => 'resources/js/app.js',
                'css' => ['assets/app.css'],
            ],
            'resources/css/app.css' => [
                'file' => 'assets/app.css',
                'isEntry' => false,
                'src' => 'resources/css/app.css',
            ],
        ];

        file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT));
    }

    public function createApplication()
    {
        $basePath = dirname(__DIR__);
        $envPath = $basePath.'/.env';

        if (! static::$createdEnvFile && ! file_exists($envPath)) {
            $key = 'base64:'.base64_encode(random_bytes(32));
            file_put_contents($envPath, "APP_ENV=testing\nAPP_KEY={$key}\n");
            static::$createdEnvFile = true;
        }

        $app = require $basePath.'/bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
