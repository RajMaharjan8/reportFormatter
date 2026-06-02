<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public function mount(): void
    {
        // An already-signed-in admin can skip the form.
        if (Auth::user()?->isAdmin()) {
            $this->redirectRoute('admin.dashboard', navigate: true);
        }
    }

    public function authenticate()
    {
        $this->validate();

        $user = \App\Models\User::where('email', $this->email)->first();

        if (! $user || ! $user->isAdmin() || ! Auth::attempt(['email' => $this->email, 'password' => $this->password])) {
            $this->addError('email', 'These credentials do not match an admin account.');

            return null;
        }

        session()->regenerate();

        return $this->redirectRoute('admin.dashboard', navigate: true);
    }
}; ?>

<div class="flex min-h-screen items-center justify-center bg-gray-100 px-4">
    <x-validation-popup />

    <div class="w-full max-w-sm rounded-lg bg-white p-8 shadow-sm ring-1 ring-gray-200">
        <h1 class="text-center text-xl font-semibold text-gray-900">Admin sign in</h1>
        <p class="mt-1 text-center text-sm text-gray-500">Staff access only.</p>

        <form wire:submit="authenticate" class="mt-6 space-y-4">
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                <input type="email" id="email" wire:model="email" autocomplete="username" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                <input type="password" id="password" wire:model="password" autocomplete="current-password" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                <span wire:loading.remove wire:target="authenticate">Sign in</span>
                <span wire:loading wire:target="authenticate">Signing in…</span>
            </button>
        </form>
    </div>
</div>
