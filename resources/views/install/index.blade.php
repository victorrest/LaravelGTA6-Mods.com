@extends('layouts.app', ['title' => 'Install'])

@section('content')
    <section class="max-w-3xl mx-auto space-y-6">
        <header class="space-y-3 text-center">
            <h1 class="text-3xl font-bold text-gray-900">GTA6 Nexus Installer</h1>
            <p class="text-sm text-gray-500">Provide your database connection and administrator details to finish the setup.</p>
        </header>

        <form method="POST" action="{{ route('install.store') }}" class="card p-6 space-y-6">
            @csrf
            @include('components.validation-errors')
            @error('install')
                <div class="rounded-xl bg-rose-50 border border-rose-200 p-4 text-sm text-rose-700">{{ $message }}</div>
            @enderror

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="form-label" for="app_name">Application name</label>
                    <input class="form-input" type="text" id="app_name" name="app_name" value="{{ old('app_name', config('app.name')) }}" required>
                </div>
                <div>
                    <label class="form-label" for="app_url">Application URL</label>
                    <input class="form-input" type="url" id="app_url" name="app_url" value="{{ old('app_url', url('/')) }}" required>
                </div>
            </div>

            <div class="space-y-4">
                <h2 class="text-lg font-semibold text-gray-900">Database connection</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="form-label" for="db_host">Host</label>
                        <input class="form-input" type="text" id="db_host" name="db_host" value="{{ old('db_host', '127.0.0.1') }}" required>
                    </div>
                    <div>
                        <label class="form-label" for="db_port">Port</label>
                        <input class="form-input" type="number" id="db_port" name="db_port" value="{{ old('db_port', 3306) }}" required>
                    </div>
                    <div>
                        <label class="form-label" for="db_database">Database</label>
                        <input class="form-input" type="text" id="db_database" name="db_database" value="{{ old('db_database') }}" required>
                    </div>
                    <div>
                        <label class="form-label" for="db_username">Username</label>
                        <input class="form-input" type="text" id="db_username" name="db_username" value="{{ old('db_username') }}" required>
                    </div>
                    <div>
                        <label class="form-label" for="db_password">Password</label>
                        <input class="form-input" type="password" id="db_password" name="db_password" value="{{ old('db_password') }}">
                        <p class="form-help">Leave blank if your database user does not have a password.</p>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <h2 class="text-lg font-semibold text-gray-900">Administrator account</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="form-label" for="admin_name">Display name</label>
                        <input class="form-input" type="text" id="admin_name" name="admin_name" value="{{ old('admin_name') }}" required>
                    </div>
                    <div>
                        <label class="form-label" for="admin_email">Email</label>
                        <input class="form-input" type="email" id="admin_email" name="admin_email" value="{{ old('admin_email') }}" required>
                    </div>
                    <div>
                        <label class="form-label" for="admin_password">Password</label>
                        <input class="form-input" type="password" id="admin_password" name="admin_password" required>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <button type="submit" class="inline-flex items-center px-5 py-2.5 rounded-lg bg-pink-600 text-white text-sm font-semibold shadow hover:bg-pink-700 transition">
                    <i class="fa-solid fa-rocket mr-2"></i>Run installation
                </button>
            </div>
        </form>
    </section>
@endsection
