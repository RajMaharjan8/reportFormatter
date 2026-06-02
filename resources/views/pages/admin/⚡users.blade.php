<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function suspend(int $userId): void
    {
        $user = User::findOrFail($userId);

        // Never suspend an admin or yourself.
        if ($user->isAdmin() || $user->id === Auth::id()) {
            return;
        }

        $user->forceFill(['suspended_at' => now()])->save();
    }

    public function unsuspend(int $userId): void
    {
        User::findOrFail($userId)->forceFill(['suspended_at' => null])->save();
    }

    public function getUsersProperty()
    {
        return User::query()
            ->when($this->search !== '', function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%");
                });
            })
            ->withCount('reports')
            ->latest()
            ->paginate(15);
    }
}; ?>

@php($title = 'Users')

<div class="space-y-4">
    <div class="flex items-center justify-between gap-4">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search name or email…" class="w-full max-w-xs rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
    </div>

    <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3">User</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Reports</th>
                    <th class="px-4 py-3">Last active</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($this->users as $user)
                    @php($online = $user->last_active_at && $user->last_active_at->gte(now()->subMinutes(5)))
                    <tr wire:key="user-{{ $user->id }}">
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-900">{{ $user->name }} @if($user->isAdmin())<span class="ml-1 rounded bg-indigo-100 px-1.5 py-0.5 text-[10px] font-semibold text-indigo-700">ADMIN</span>@endif</div>
                            <div class="text-xs text-gray-500">{{ $user->email }}</div>
                        </td>
                        <td class="px-4 py-3">
                            @if ($user->isSuspended())
                                <span class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">Suspended</span>
                            @elseif ($online)
                                <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700"><span class="h-1.5 w-1.5 rounded-full bg-green-500"></span>Online</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">Offline</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-700">{{ $user->reports_count }}</td>
                        <td class="px-4 py-3 text-xs text-gray-500">{{ $user->last_active_at?->diffForHumans() ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            @if (! $user->isAdmin() && $user->id !== auth()->id())
                                @if ($user->isSuspended())
                                    <button wire:click="unsuspend({{ $user->id }})" class="rounded-md bg-green-50 px-3 py-1.5 text-xs font-semibold text-green-700 ring-1 ring-green-200 hover:bg-green-100">Unsuspend</button>
                                @else
                                    <button wire:click="suspend({{ $user->id }})" wire:confirm="Suspend {{ $user->name }}? They will be signed out and blocked." class="rounded-md bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 ring-1 ring-red-200 hover:bg-red-100">Suspend</button>
                                @endif
                            @else
                                <span class="text-xs text-gray-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No users found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $this->users->links() }}</div>
</div>
