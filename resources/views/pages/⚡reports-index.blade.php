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
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    #[Validate('nullable|string|max:2000')]
    public string $fb_working = '';

    #[Validate('nullable|string|max:2000')]
    public string $fb_not_working = '';

    /** @var list<\Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $fb_images = [];

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'fb_working' => "what's working",
            'fb_not_working' => "what's not working",
            'fb_images.*' => 'image',
        ];
    }

    public function getFeedbackImageLimitProperty(): int
    {
        return Feedback::imageLimit();
    }

    public function getFeedbackDailyLimitProperty(): int
    {
        return Feedback::dailyLimit();
    }

    /** Feedback submissions the user has left today. */
    public function getFeedbackLeftTodayProperty(): int
    {
        $used = Feedback::where('user_id', Auth::id())
            ->whereDate('created_at', today())
            ->count();

        return max(0, $this->feedbackDailyLimit - $used);
    }

    /** Drop one of the staged (not-yet-sent) images. */
    public function removeFeedbackImage(int $index): void
    {
        unset($this->fb_images[$index]);
        $this->fb_images = array_values($this->fb_images);
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
        // Daily cap (admin-configurable) — keeps the inbox manageable.
        if ($this->feedbackLeftToday <= 0) {
            $this->addError('fb_working', "You've reached today's feedback limit ({$this->feedbackDailyLimit}). Please try again tomorrow.");

            return;
        }

        $this->validate([
            'fb_images' => 'array|max:'.$this->feedbackImageLimit,
            'fb_images.*' => 'image|max:5120', // 5 MB each
        ]);

        $this->validate();

        if (trim($this->fb_working) === '' && trim($this->fb_not_working) === '') {
            $this->addError('fb_working', 'Please tell us what is working or what is not.');

            return;
        }

        $paths = [];

        foreach ($this->fb_images as $image) {
            $paths[] = $image->store('feedback', 'public');
        }

        $feedback = Feedback::create([
            'user_id' => Auth::id(),
            'working' => $this->fb_working ?: null,
            'not_working' => $this->fb_not_working ?: null,
            'images' => $paths ?: null,
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

        $this->reset('fb_working', 'fb_not_working', 'fb_images');

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
                                <a href="{{ route('reports.live-check', $report) }}" wire:navigate class="rounded-md bg-white px-3 py-1.5 text-xs font-semibold text-gray-900 ring-1 ring-gray-300 hover:bg-gray-50">
                                    Format check
                                </a>
                                <a href="{{ route('reports.output', $report) }}" class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500">
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

                {{-- Screenshots / images (optional, capped) --}}
                <div>
                    <div class="flex items-center justify-between">
                        <label class="block text-sm font-medium text-gray-700">Screenshots <span class="font-normal text-gray-400">(optional)</span></label>
                        <span class="text-xs text-gray-400">{{ count($fb_images) }}/{{ $this->feedbackImageLimit }}</span>
                    </div>

                    @if (count($fb_images) < $this->feedbackImageLimit)
                        <label class="mt-1 flex cursor-pointer items-center justify-center gap-2 rounded-md border border-dashed border-gray-300 px-3 py-3 text-sm text-gray-500 hover:border-indigo-400 hover:text-indigo-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5z" /></svg>
                            <span wire:loading.remove wire:target="fb_images">Add image (up to {{ $this->feedbackImageLimit }})</span>
                            <span wire:loading wire:target="fb_images">Uploading…</span>
                            <input type="file" wire:model="fb_images" multiple accept="image/*" class="hidden">
                        </label>
                    @endif
                    @error('fb_images.*') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    @error('fb_images') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

                    @if (count($fb_images))
                        <div class="mt-2 grid grid-cols-3 gap-2">
                            @foreach ($fb_images as $index => $image)
                                <div wire:key="fb-img-{{ $index }}" class="group relative">
                                    <img src="{{ $image->temporaryUrl() }}" alt="" class="h-20 w-full rounded-md object-cover ring-1 ring-gray-200">
                                    <button type="button" wire:click="removeFeedbackImage({{ $index }})" class="absolute -right-1.5 -top-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-gray-900 text-xs text-white shadow hover:bg-red-600" title="Remove">&times;</button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="flex items-center justify-between gap-3">
                    <span class="text-xs {{ $this->feedbackLeftToday <= 0 ? 'text-red-600' : 'text-gray-400' }}">
                        @if ($this->feedbackLeftToday <= 0)
                            Daily limit reached — try again tomorrow.
                        @else
                            {{ $this->feedbackLeftToday }} of {{ $this->feedbackDailyLimit }} feedbacks left today.
                        @endif
                    </span>
                    <div class="flex gap-3">
                        <button type="button" x-on:click="feedbackOpen = false" class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50">Cancel</button>
                        <button type="submit" @disabled($this->feedbackLeftToday <= 0) class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50">
                            <span wire:loading.remove wire:target="sendFeedback">Send feedback</span>
                            <span wire:loading wire:target="sendFeedback">Sending…</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
