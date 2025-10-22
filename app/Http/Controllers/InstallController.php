<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Throwable;

class InstallController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if ($this->isInstalled()) {
            return redirect()->route('home');
        }

        return view('install.index');
    }

    public function store(Request $request): RedirectResponse
    {
        if ($this->isInstalled()) {
            return redirect()->route('home');
        }

        $data = $request->validate([
            'app_name' => ['required', 'string', 'max:80'],
            'app_url' => ['required', 'url'],
            'db_host' => ['required', 'string', 'max:120'],
            'db_port' => ['required', 'numeric'],
            'db_database' => ['required', 'string', 'max:120'],
            'db_username' => ['required', 'string', 'max:120'],
            'db_password' => ['nullable', 'string', 'max:120'],
            'admin_name' => ['required', 'string', 'max:80'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'min:8', 'max:120'],
        ]);

        try {
            $this->writeEnvironment([
                'APP_NAME' => $data['app_name'],
                'APP_ENV' => 'production',
                'APP_KEY' => env('APP_KEY') ?: 'base64:' . base64_encode(random_bytes(32)),
                'APP_DEBUG' => 'false',
                'APP_URL' => $data['app_url'],
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => $data['db_host'],
                'DB_PORT' => $data['db_port'],
                'DB_DATABASE' => $data['db_database'],
                'DB_USERNAME' => $data['db_username'],
                'DB_PASSWORD' => $data['db_password'] ?? '',
            ]);

            config([
                'app.name' => $data['app_name'],
                'app.url' => $data['app_url'],
                'database.connections.mysql.host' => $data['db_host'],
                'database.connections.mysql.port' => $data['db_port'],
                'database.connections.mysql.database' => $data['db_database'],
                'database.connections.mysql.username' => $data['db_username'],
                'database.connections.mysql.password' => $data['db_password'],
            ]);

            app('db')->purge();
            app('db')->reconnect();

            Artisan::call('config:clear');
            Artisan::call('key:generate', ['--force' => true]);
            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('db:seed', ['--force' => true]);

            if (! file_exists(public_path('storage'))) {
                Artisan::call('storage:link');
            }

            Artisan::call('gta6:link-assets', ['--force' => true]);

            User::updateOrCreate(
                ['email' => $data['admin_email']],
                [
                    'name' => $data['admin_name'],
                    'password' => Hash::make($data['admin_password']),
                ]
            );

            file_put_contents($this->installedFlagPath(), now()->toDateTimeString());
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors(['install' => 'Installation failed: ' . $exception->getMessage()]);
        }

        return redirect()->route('home')->with('status', 'Installation completed successfully.');
    }

    private function isInstalled(): bool
    {
        return file_exists($this->installedFlagPath());
    }

    private function installedFlagPath(): string
    {
        return storage_path('app/installed');
    }

    private function writeEnvironment(array $values): void
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            copy(base_path('.env.example'), $envPath);
        }

        $content = file_get_contents($envPath);

        foreach ($values as $key => $value) {
            $formatted = $key . '=' . $this->formatEnvValue($value);
            $pattern = "/^{$key}=.*$/m";

            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, $formatted, $content);
            } else {
                $content .= PHP_EOL . $formatted;
            }
        }

        file_put_contents($envPath, $content);
    }

    private function formatEnvValue(mixed $value): string
    {
        $value = (string) $value;

        if ($value === '') {
            return '""';
        }

        if (str_contains($value, ' ') || str_contains($value, '#')) {
            return '"' . addslashes($value) . '"';
        }

        return $value;
    }
}
