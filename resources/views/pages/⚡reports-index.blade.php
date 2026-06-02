<?php

use App\Mail\FeedbackSubmitted;
use App\Models\Feedback;
use App\Models\Report;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    #[Validate('nullable|string|max:2000')]
    public string $fb_working = '';

    #[Validate('nullable|string|max:2000')]
    public string $fb_not_working = '';

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'fb_working' => "what's working",
            'fb_not_working' => "what's not working",
        ];
    }
    /**
     * The signed-in user's reports, newest activity first.
     *
     * @return Collection<int, Report>
     */
    public function getReportsProperty(): Collection
    {
        return Auth::user()
            ->reports()
            ->withCount('sections')
            ->latest('updated_at')
            ->get();
    }

    public function getReportLimitProperty(): int
    {
        return User::MAX_REPORTS;
    }

    public function getCanCreateMoreProperty(): bool
    {
        return $this->reports->count() < $this->reportLimit;
    }

    public function deleteReport(int $reportId): void
    {
        $report = Report::whereKey($reportId)->firstOrFail();
        $this->authorize('delete', $report);
        $report->delete();
    }

    /**
     * Store the user's feedback and email it to the configured admin address
     * via the admin-managed SMTP settings.
     */
    public function sendFeedback(): void
    {
        $this->validate();

        if (trim($this->fb_working) === '' && trim($this->fb_not_working) === '') {
            $this->addError('fb_working', 'Please tell us what is working or what is not.');

            return;
        }

        $feedback = Feedback::create([
            'user_id' => Auth::id(),
            'working' => $this->fb_working ?: null,
            'not_working' => $this->fb_not_working ?: null,
        ]);

        $recipient = Setting::get('admin_notification_email', config('mail.from.address'));

        if ($recipient) {
            try {
                Mail::to($recipient)->send(new FeedbackSubmitted($feedback->load('user')));
            } catch (\Throwable $e) {
                // The feedback is saved regardless; don't fail the user's action
                // if the mail server is misconfigured.
                report($e);
            }
        }

        $this->reset('fb_working', 'fb_not_working');

        session()->flash('feedback-sent', 'Thanks for your feedback!');

        $this->dispatch('feedback-sent');
    }
}; ?>

<div class="min-h-screen bg-gray-50 py-12 px-4 sm:px-6 lg:px-8" x-data="{ feedbackOpen: false }" @feedback-sent.window="feedbackOpen = false">
    <x-validation-popup />

    <div class="mx-auto max-w-4xl">
        <div class="mb-6 flex items-center justify-between gap-4 text-sm">
            <div class="text-gray-600">
                Signed in as <span class="font-medium text-gray-900">{{ auth()->user()->email }}</span>
            </div>
            <div class="flex items-center gap-4">
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('admin.dashboard') }}" wire:navigate class="font-medium text-indigo-600 hover:text-indigo-500">Admin panel</a>
                @endif
                <button type="button" x-on:click="feedbackOpen = true" class="text-gray-600 hover:text-gray-900 underline">Send feedback</button>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-gray-600 hover:text-gray-900 underline">Sign out</button>
                </form>
            </div>
        </div>

        @if (session('feedback-sent'))
            <div class="mb-6 rounded-md bg-green-50 px-4 py-3 text-sm font-medium text-green-800 ring-1 ring-green-200">{{ session('feedback-sent') }}</div>
        @endif

        @if (session('report-limit'))
            <div class="mb-6 rounded-md bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-amber-200">
                {{ session('report-limit') }}
            </div>
        @endif

        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 sm:text-3xl">Your Reports</h1>
                <p class="mt-2 text-sm text-gray-600">Islington College &middot; London Metropolitan University</p>
                <p class="mt-1 text-xs text-gray-500">
                    {{ $this->reports->count() }} of {{ $this->reportLimit }} reports used &middot; delete one to start another.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('cover.templates') }}" wire:navigate class="inline-flex shrink-0 items-center rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-900 ring-1 ring-gray-300 hover:bg-gray-50">
                    Cover Designer
                </a>
                <a href="{{ route('reports.check') }}" wire:navigate class="inline-flex shrink-0 items-center rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-900 ring-1 ring-gray-300 hover:bg-gray-50">
                    Check My Report
                </a>
                @if ($this->canCreateMore)
                    <a href="{{ route('reports.create') }}" wire:navigate class="inline-flex shrink-0 items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                        + New report
                    </a>
                @else
                    <span class="inline-flex shrink-0 items-center rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-500 cursor-not-allowed" title="Delete an existing report to create another.">
                        + New report
                    </span>
                @endif
            </div>
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
                                    @php($subtitle = $report->cover_format === 'tu' ? trim((string) $report->title) : trim($report->module_code.' '.$report->module_title))
                                    @if (filled($subtitle))
                                        {{ $subtitle }}
                                    @else
                                        <span class="text-gray-400">{{ $report->cover_format === 'tu' ? 'No assignment title yet' : 'No module set yet' }}</span>
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
                                <a href="{{ route('reports.live-check', $report) }}" wire:navigate class="rounded-md bg-white px-3 py-1.5 text-xs font-semibold text-indigo-700 ring-1 ring-indigo-300 hover:bg-indigo-50">
                                    Format check
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

    {{-- Feedback modal --}}
    <div x-show="feedbackOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" x-on:keydown.escape.window="feedbackOpen = false">
        <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl" x-on:click.outside="feedbackOpen = false">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Send feedback</h2>
                    <p class="mt-0.5 text-sm text-gray-500">Tell us how the report generator is working for you.</p>
                </div>
                <button type="button" x-on:click="feedbackOpen = false" class="text-gray-400 hover:text-gray-600" title="Close">&times;</button>
            </div>

            <form wire:submit="sendFeedback" class="mt-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">What's working for you?</label>
                    <textarea wire:model="fb_working" rows="3" placeholder="Things you like or that work well…" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                    @error('fb_working') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">What's not working?</label>
                    <textarea wire:model="fb_not_working" rows="3" placeholder="Problems, bugs, or things you'd change…" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                    @error('fb_not_working') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" x-on:click="feedbackOpen = false" class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                        <span wire:loading.remove wire:target="sendFeedback">Send feedback</span>
                        <span wire:loading wire:target="sendFeedback">Sending…</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
