<?php

use App\Models\Otp;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Title('Create account')] class extends Component
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|email|max:255|unique:users,email')]
    public string $email = '';

    #[Validate('required|string|min:8|confirmed')]
    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        if (Auth::check()) {
            $this->redirectRoute('reports.index', navigate: true);
        }
    }

    public function register()
    {
        $this->validate();

        // Created unverified: the user cannot sign in until the OTP is confirmed.
        User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
        ]);

        Otp::send($this->email, Otp::PURPOSE_REGISTRATION);
        session(['otp_email' => $this->email]);

        return $this->redirectRoute('verify-otp', navigate: true);
    }
}; ?>

<div class="flex min-h-screen items-center justify-center bg-gray-50 px-4 py-12">
    <x-validation-popup />

    <div class="w-full max-w-md rounded-lg bg-white p-8 shadow-sm ring-1 ring-gray-200">
        <div class="text-center">
            <h1 class="text-2xl font-semibold text-gray-900">Create your account</h1>
            <p class="mt-2 text-sm text-gray-600">We'll email you a code to verify your address.</p>
        </div>

        <form wire:submit="register" class="mt-8 space-y-4">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                <input type="text" id="name" wire:model="name" autocomplete="name" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                <input type="email" id="email" wire:model="email" autocomplete="username" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                <input type="password" id="password" wire:model="password" autocomplete="new-password" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm password</label>
                <input type="password" id="password_confirmation" wire:model="password_confirmation" autocomplete="new-password" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <button type="submit" class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                <span wire:loading.remove wire:target="register">Create account</span>
                <span wire:loading wire:target="register">Sending code…</span>
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-gray-600">
            Already have an account? <a href="{{ route('login') }}" wire:navigate class="font-medium text-indigo-600 hover:text-indigo-500">Sign in</a>
        </p>
    </div>
</div>
