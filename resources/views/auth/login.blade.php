@extends('layouts.app', ['title' => 'Sign in'])

@section('content')
    <section class="max-w-md mx-auto space-y-6">
        <header class="space-y-2 text-center">
            <h1 class="text-3xl font-bold text-gray-900">Welcome back</h1>
            <p class="text-sm text-gray-500">Sign in to manage your mods, bookmarks and forum activity.</p>
        </header>

        <form method="POST" action="{{ route('login') }}" class="card p-6 space-y-5">
            @include('components.validation-errors')
            @csrf
            <div>
                <label class="form-label" for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" class="form-input" required autofocus>
            </div>
            <div>
                <label class="form-label" for="password">Password</label>
                <input id="password" name="password" type="password" class="form-input" required>
            </div>
            <div class="flex items-center justify-between text-sm">
                <label class="flex items-center gap-2 text-gray-600">
                    <input type="checkbox" name="remember" class="rounded border-gray-300 text-pink-600 focus:ring-pink-500">
                    Remember me
                </label>
                <a href="#" class="text-pink-600 hover:text-pink-700">Forgot password?</a>
            </div>
            <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2.5 bg-pink-600 text-white text-sm font-semibold rounded-lg shadow hover:bg-pink-700 transition">
                Sign in
            </button>
        </form>

        <p class="text-center text-sm text-gray-500">
            New here? <a href="{{ route('register') }}" class="text-pink-600 hover:text-pink-700">Create an account</a>
        </p>
    </section>
@endsection
