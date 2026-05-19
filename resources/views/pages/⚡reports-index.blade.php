<?php

use App\Models\Report;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component
{
    /**
     * Every saved report, newest activity first.
     *
     * @return Collection<int, Report>
     */
    public function getReportsProperty(): Collection
    {
        return Report::withCount('sections')->latest('updated_at')->get();
    }

    public function deleteReport(int $reportId): void
    {
        Report::whereKey($reportId)->delete();
    }
}; ?>

<div class="min-h-screen bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-4xl">
        <div class="mb-8 flex items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-semibold text-gray-900">Your Reports</h1>
                <p class="mt-2 text-sm text-gray-600">Islington College &middot; London Metropolitan University</p>
            </div>
            <a href="{{ route('reports.create') }}" wire:navigate class="inline-flex shrink-0 items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                + New report
            </a>
        </div>

        @if ($this->reports->isEmpty())
            <div class="rounded-lg bg-white p-12 text-center shadow-sm ring-1 ring-gray-200">
                <p class="text-sm text-gray-600">No reports yet. Start one and your progress is saved automatically.</p>
                <a href="{{ route('reports.create') }}" wire:navigate class="mt-4 inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                    Create your first report
                </a>
            </div>
        @else
            <ul class="space-y-3">
                @foreach ($this->reports as $report)
                    <li wire:key="report-{{ $report->id }}" class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:p-5">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="min-w-0">
                                <h2 class="truncate text-base font-semibold text-gray-900">
                                    {{ $report->student_name ?: 'Untitled draft' }}
                                </h2>
                                <p class="mt-0.5 truncate text-sm text-gray-600">
                                    @if ($report->module_code || $report->module_title)
                                        {{ trim($report->module_code.' '.$report->module_title) }}
                                    @else
                                        <span class="text-gray-400">No module set yet</span>
                                    @endif
                                </p>
                                <p class="mt-1 text-xs text-gray-400">
                                    {{ $report->sections_count }} {{ Str::plural('section', $report->sections_count) }}
                                    &middot; updated {{ $report->updated_at->diffForHumans() }}
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('reports.edit', $report) }}" wire:navigate class="rounded-md bg-white px-3 py-1.5 text-xs font-semibold text-gray-900 ring-1 ring-gray-300 hover:bg-gray-50">
                                    Edit cover
                                </a>
                                <a href="{{ route('reports.sections', $report) }}" wire:navigate class="rounded-md bg-white px-3 py-1.5 text-xs font-semibold text-gray-900 ring-1 ring-gray-300 hover:bg-gray-50">
                                    Write content
                                </a>
                                <a href="{{ route('reports.output', $report) }}" class="rounded-md bg-gray-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-gray-700">
                                    View report
                                </a>
                                <button type="button" wire:click="deleteReport({{ $report->id }})" wire:confirm="Delete this report and all its sections? This cannot be undone." class="rounded-md px-2 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50">
                                    Delete
                                </button>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
