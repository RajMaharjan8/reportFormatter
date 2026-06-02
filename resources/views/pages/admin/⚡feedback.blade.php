<?php

use App\Models\Feedback;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination;

    public function delete(int $id): void
    {
        Feedback::whereKey($id)->delete();
    }

    public function getFeedbackProperty()
    {
        return Feedback::with('user')->latest()->paginate(20);
    }
}; ?>

@php($title = 'Feedback')

<div class="space-y-4">
    @if ($this->feedback->isEmpty())
        <div class="rounded-lg bg-white p-12 text-center text-sm text-gray-500 shadow-sm ring-1 ring-gray-200">No feedback has been submitted yet.</div>
    @else
        <div class="space-y-4">
            @foreach ($this->feedback as $item)
                <div wire:key="fb-{{ $item->id }}" class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">{{ $item->user?->name ?? 'Anonymous' }}</p>
                            <p class="text-xs text-gray-500">{{ $item->user?->email }} &middot; {{ $item->created_at->diffForHumans() }}</p>
                        </div>
                        <button wire:click="delete({{ $item->id }})" wire:confirm="Delete this feedback?" class="text-xs font-medium text-red-600 hover:text-red-700">Delete</button>
                    </div>
                    @if ($item->working)
                        <div class="mt-3 rounded-md bg-green-50 px-3 py-2 text-sm text-green-900"><span class="font-semibold">Working well:</span> {{ $item->working }}</div>
                    @endif
                    @if ($item->not_working)
                        <div class="mt-2 rounded-md bg-red-50 px-3 py-2 text-sm text-red-900"><span class="font-semibold">Not working:</span> {{ $item->not_working }}</div>
                    @endif
                </div>
            @endforeach
        </div>

        <div>{{ $this->feedback->links() }}</div>
    @endif
</div>
