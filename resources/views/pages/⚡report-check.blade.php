<?php

use App\Support\Checks\ReportPdfAnalyzer;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    #[Validate('required|file|mimes:pdf|max:20480')]
    public $pdf;

    public string $format = 'london_met';

    /** @var list<array{label: string, status: string, detail: string}>|null */
    public ?array $results = null;

    public ?string $checkerName = null;

    public ?int $pageCount = null;

    /**
     * @return array<string, string>
     */
    public function getAvailableFormatsProperty(): array
    {
        $labels = [];
        foreach (ReportPdfAnalyzer::availableFormats() as $slug => $checker) {
            $labels[$slug] = $checker->name();
        }

        return $labels;
    }

    public function analyze(): void
    {
        $this->validate();

        $path = $this->pdf->getRealPath();

        try {
            $outcome = (new ReportPdfAnalyzer)->analyze($path, $this->format);

            $this->results = array_map(
                fn ($r) => ['label' => $r->label, 'status' => $r->status, 'detail' => $r->detail],
                $outcome['results'],
            );
            $this->checkerName = $outcome['checker']->name();
            $this->pageCount = $outcome['pages'];
        } catch (\Throwable) {
            $this->results = null;
            $this->checkerName = null;
            $this->pageCount = null;
            $this->addError('pdf', 'Could not read this PDF — it may be corrupted, password-protected, or not a PDF.');
        } finally {
            if ($path && is_file($path)) {
                @unlink($path);
            }
            $this->pdf = null;
        }
    }

    public function reset_(): void
    {
        $this->reset(['pdf', 'results', 'checkerName', 'pageCount']);
        $this->resetValidation();
    }
}; ?>

<div class="min-h-screen bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-3xl">
        <div class="mb-6">
            <a href="{{ route('reports.index') }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:text-indigo-500">&larr; All reports</a>
        </div>

        <div class="mb-8">
            <h1 class="text-3xl font-semibold text-gray-900">Check My Report</h1>
            <p class="mt-2 text-sm text-gray-600">Upload a PDF and we'll flag missing or non-conforming parts against the chosen university format.</p>
        </div>

        <form wire:submit="analyze" class="space-y-6 rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:p-8">
            <div>
                <label for="format" class="block text-sm font-medium text-gray-700">University format</label>
                <select id="format" wire:model="format" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    @foreach ($this->availableFormats as $slug => $label)
                        <option value="{{ $slug }}">{{ $label }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500">More university formats coming soon.</p>
            </div>

            <div>
                <label for="pdf" class="block text-sm font-medium text-gray-700">Report PDF</label>
                <input type="file" id="pdf" wire:model="pdf" accept="application/pdf" class="mt-1 block w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">
                <p class="mt-1 text-xs text-gray-500">PDF, up to 20&nbsp;MB.</p>
                @error('pdf') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

                <div wire:loading wire:target="pdf" class="mt-2 text-xs text-gray-500">Uploading…</div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <button type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50" wire:loading.attr="disabled" wire:target="analyze,pdf">
                    <span wire:loading.remove wire:target="analyze">Check report</span>
                    <span wire:loading wire:target="analyze">Checking…</span>
                </button>

                @if ($results !== null)
                    <button type="button" wire:click="reset_" class="text-sm text-gray-600 hover:text-gray-900 underline">Reset</button>
                @endif
            </div>
        </form>

        @if ($results !== null)
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

            <div class="mt-8 space-y-6">
                <div class="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Your report check</h2>
                            <p class="mt-1 text-xs text-gray-500">{{ $checkerName }} &middot; {{ $pageCount }} {{ Str::plural('page', $pageCount) }} analysed</p>
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

                <p class="text-xs text-gray-500">Margins, italics and line-spacing can't be reliably read from a PDF's text, so we don't pretend to check them.</p>
            </div>
        @endif
    </div>
</div>
