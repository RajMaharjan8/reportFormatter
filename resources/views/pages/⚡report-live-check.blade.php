<?php

use App\Models\Report;
use App\Support\Checks\CheckResult;
use App\Support\Checks\LiveReportChecker;
use Livewire\Component;

new class extends Component
{
    public Report $report;

    /** @var list<array{label: string, status: string, detail: string}> */
    public array $results = [];

    public string $formatName = '';

    public function mount(Report $report): void
    {
        $this->authorize('view', $report);

        $this->report = $report;
        $this->run();
    }

    public function run(): void
    {
        $outcome = (new LiveReportChecker)->check($this->report);
        $this->formatName = $outcome['format'];
        $this->results = array_map(
            fn (CheckResult $r) => ['label' => $r->label, 'status' => $r->status, 'detail' => $r->detail],
            $outcome['results'],
        );
    }
}; ?>

<div class="min-h-screen bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-3xl">
        <div class="mb-6">
            <a href="{{ route('reports.index') }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:text-indigo-500">&larr; All reports</a>
        </div>

        <div class="mb-8">
            <h1 class="text-3xl font-semibold text-gray-900">Format check</h1>
            <p class="mt-2 text-sm text-gray-600">
                Live check of <span class="font-medium">{{ $report->title ?: 'this report' }}</span> against <span class="font-medium">{{ $formatName }}</span>.
            </p>
            <p class="mt-1 text-xs text-gray-500">This runs against the data you've entered on the site — your cover fields, sections and references — not a PDF.</p>
        </div>

        @php
            $grouped = [
                \App\Support\Checks\CheckResult::FAIL => [],
                \App\Support\Checks\CheckResult::WARN => [],
                \App\Support\Checks\CheckResult::PASS => [],
            ];
            foreach ($results as $r) {
                $grouped[$r['status']][] = $r;
            }
            $failCount = count($grouped[\App\Support\Checks\CheckResult::FAIL]);
            $warnCount = count($grouped[\App\Support\Checks\CheckResult::WARN]);
            $passCount = count($grouped[\App\Support\Checks\CheckResult::PASS]);
        @endphp

        <div class="space-y-6">
            <div class="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Your report check</h2>
                        <p class="mt-1 text-xs text-gray-500">{{ $report->sections->count() }} sections &middot; {{ $report->references->count() }} references</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-sm font-semibold">
                        <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-3 py-1 text-red-800">
                            <span class="text-base leading-none">✗</span> {{ $failCount }} to fix
                        </span>
                        <span class="inline-flex items-center gap-1 rounded-full bg-yellow-100 px-3 py-1 text-yellow-900">
                            <span class="text-base leading-none">!</span> {{ $warnCount }} to review
                        </span>
                        <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-3 py-1 text-green-800">
                            <span class="text-base leading-none">✓</span> {{ $passCount }} OK
                        </span>
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap gap-2 text-xs">
                    <a href="{{ route('reports.edit', $report) }}" wire:navigate class="rounded-md bg-white px-3 py-1.5 font-semibold text-gray-900 ring-1 ring-gray-300 hover:bg-gray-50">Edit cover</a>
                    <a href="{{ route('reports.sections', $report) }}" wire:navigate class="rounded-md bg-white px-3 py-1.5 font-semibold text-gray-900 ring-1 ring-gray-300 hover:bg-gray-50">Write sections</a>
                    <button wire:click="run" class="rounded-md bg-indigo-600 px-3 py-1.5 font-semibold text-white hover:bg-indigo-500">Re-check</button>
                </div>
            </div>

            @if ($failCount > 0)
                <section>
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-red-700">Fix these</h3>
                    <ul class="space-y-2">
                        @foreach ($grouped[\App\Support\Checks\CheckResult::FAIL] as $result)
                            <li class="flex items-start gap-3 rounded-md border border-red-200 bg-red-50 px-4 py-3">
                                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-red-600 text-xs font-bold text-white">✗</span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-red-900">{{ $result['label'] }}</p>
                                    @if ($result['detail'])
                                        <p class="mt-0.5 text-sm text-red-800">{{ $result['detail'] }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($warnCount > 0)
                <section>
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-yellow-800">Worth reviewing</h3>
                    <ul class="space-y-2">
                        @foreach ($grouped[\App\Support\Checks\CheckResult::WARN] as $result)
                            <li class="flex items-start gap-3 rounded-md border border-yellow-200 bg-yellow-50 px-4 py-3">
                                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-yellow-500 text-xs font-bold text-white">!</span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-yellow-900">{{ $result['label'] }}</p>
                                    @if ($result['detail'])
                                        <p class="mt-0.5 text-sm text-yellow-800">{{ $result['detail'] }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($passCount > 0)
                <details class="rounded-md border border-green-200 bg-green-50 px-4 py-3">
                    <summary class="cursor-pointer text-sm font-semibold text-green-800">
                        ✓ {{ $passCount }} checks passed (click to expand)
                    </summary>
                    <ul class="mt-3 space-y-1 text-sm text-green-900">
                        @foreach ($grouped[\App\Support\Checks\CheckResult::PASS] as $result)
                            <li class="flex items-start gap-2">
                                <span class="mt-0.5 text-green-600">✓</span>
                                <span><strong>{{ $result['label'] }}</strong>@if ($result['detail']) — {{ $result['detail'] }}@endif</span>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </div>
    </div>
</div>
