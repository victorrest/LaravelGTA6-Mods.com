@extends('layouts.app', ['title' => 'Create account'])

@section('content')
    <section class="max-w-md mx-auto space-y-6">
        <header class="space-y-2 text-center">
            <h1 class="text-3xl font-bold text-gray-900">Join the community</h1>
            <p class="text-sm text-gray-500">Upload mods, bookmark favourites and engage with the GTA 6 forum.</p>
        </header>

        <form method="POST" action="{{ route('register') }}" class="card p-6 space-y-5">
            @include('components.validation-errors')
            @csrf
            <div>
                <label class="form-label" for="name">Display name</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" class="form-input" required autofocus>
            </div>
            <div>
                <label class="form-label" for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" class="form-input" required>
            </div>
            <div>
                <label class="form-label" for="password">Password</label>
                <input id="password" name="password" type="password" class="form-input" required>
            </div>
            <div>
                <label class="form-label" for="password_confirmation">Confirm password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" class="form-input" required>
            </div>
            <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2.5 bg-pink-600 text-white text-sm font-semibold rounded-lg shadow hover:bg-pink-700 transition">
                Create account
            </button>
        </form>

        <p class="text-center text-sm text-gray-500">
            Already registered? <a href="{{ route('login') }}" class="text-pink-600 hover:text-pink-700">Sign in</a>
        </p>
    </section>
@endsection
