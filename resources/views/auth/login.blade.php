<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in &middot; {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="min-h-screen bg-gray-50 flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-md rounded-lg bg-white p-8 shadow-sm ring-1 ring-gray-200">
            <div class="text-center">
                <h1 class="text-2xl font-semibold text-gray-900">Sign in to Report Generator</h1>
                <p class="mt-2 text-sm text-gray-600">Sign in with your Google account to create and edit reports.</p>
            </div>

            @if (session('auth-error'))
                <div class="mt-6 rounded-md bg-red-50 px-4 py-3 text-sm font-medium text-red-800 ring-1 ring-red-200">
                    {{ session('auth-error') }}
                </div>
            @endif

            <a href="{{ route('auth.google.redirect') }}"
               class="mt-8 inline-flex w-full items-center justify-center gap-3 rounded-md bg-white px-4 py-2.5 text-sm font-semibold text-gray-800 ring-1 ring-gray-300 hover:bg-gray-50">
                <svg class="h-5 w-5" viewBox="0 0 48 48" aria-hidden="true">
                    <path fill="#FFC107" d="M43.611 20.083H42V20H24v8h11.303C33.972 32.91 29.418 36 24 36c-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 12.955 4 4 12.955 4 24s8.955 20 20 20 20-8.955 20-20c0-1.341-.138-2.65-.389-3.917z"/>
                    <path fill="#FF3D00" d="M6.306 14.691l6.571 4.819C14.655 15.108 18.961 12 24 12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 16.318 4 9.656 8.337 6.306 14.691z"/>
                    <path fill="#4CAF50" d="M24 44c5.166 0 9.86-1.977 13.409-5.192l-6.19-5.238C29.211 35.091 26.715 36 24 36c-5.397 0-9.939-3.073-11.278-7.946l-6.522 5.025C9.5 39.556 16.227 44 24 44z"/>
                    <path fill="#1976D2" d="M43.611 20.083H42V20H24v8h11.303a12.04 12.04 0 0 1-4.087 5.571l.003-.002 6.19 5.238C36.971 39.205 44 34 44 24c0-1.341-.138-2.65-.389-3.917z"/>
                </svg>
                Continue with Google
            </a>

            <p class="mt-6 text-center text-xs text-gray-500">By signing in you agree to keep up to {{ \App\Models\User::MAX_REPORTS }} active reports on your account.</p>
        </div>
    </div>
</body>
</html>
