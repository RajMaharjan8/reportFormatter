<?php

use App\Models\Report;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    public ?Report $report = null;

    public string $cover_format = 'london_met';

    public string $tu_college_name = '';

    public string $tu_roll_number = '';

    public string $tu_submitted_to_position = '';

    #[Validate('nullable|string|max:50')]
    public string $module_code = '';

    #[Validate('nullable|string|max:255')]
    public string $module_title = '';

    #[Validate('nullable|string|max:255')]
    public string $title = '';

    #[Validate('nullable|string|max:5000')]
    public string $abstract = '';

    #[Validate('nullable|string|max:30')]
    public string $section_label = '';

    #[Validate('nullable|string|max:100')]
    public string $assessment_type = '';

    #[Validate('nullable|string|max:50')]
    public string $semester = '';

    #[Validate('nullable|string|max:20')]
    public string $academic_year = '';

    #[Validate('nullable|string|max:255')]
    public string $student_name = '';

    #[Validate('nullable|string|max:50')]
    public string $london_id = '';

    #[Validate('nullable|string|max:255')]
    public string $college_id = '';

    #[Validate('nullable|date')]
    public string $assignment_due_date = '';

    #[Validate('nullable|date')]
    public string $submission_date = '';

    #[Validate('nullable|string|max:255')]
    public string $submitted_to = '';

    public function mount(?Report $report = null): void
    {
        if ($report && $report->exists) {
            $this->report = $report;
            $this->cover_format = $report->cover_format ?: 'london_met';
            $this->tu_college_name = (string) $report->tu_college_name;
            $this->tu_roll_number = (string) $report->tu_roll_number;
            $this->tu_submitted_to_position = (string) $report->tu_submitted_to_position;
            $this->module_code = (string) $report->module_code;
            $this->module_title = (string) $report->module_title;
            $this->title = (string) $report->title;
            $this->abstract = (string) $report->abstract;
            $this->section_label = (string) $report->section_label;
            $this->assessment_type = (string) $report->assessment_type;
            $this->semester = (string) $report->semester;
            $this->academic_year = (string) $report->academic_year;
            $this->student_name = (string) $report->student_name;
            $this->london_id = (string) $report->london_id;
            $this->college_id = (string) $report->college_id;
            $this->assignment_due_date = $report->assignment_due_date?->format('Y-m-d') ?? '';
            $this->submission_date = $report->submission_date?->format('Y-m-d') ?? '';
            $this->submitted_to = (string) $report->submitted_to;
        }
    }

    /**
     * Every cover field, all optional — the baseline for drafts.
     *
     * @return array<string, string>
     */
    protected function draftRules(): array
    {
        return [
            'cover_format' => 'required|in:london_met,tu',
            'tu_college_name' => 'nullable|string|max:255',
            'tu_roll_number' => 'nullable|string|max:50',
            'tu_submitted_to_position' => 'nullable|string|max:255',
            'module_code' => 'nullable|string|max:50',
            'module_title' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
            'abstract' => 'nullable|string|max:5000',
            'section_label' => 'nullable|string|max:30',
            'assessment_type' => 'nullable|string|max:100',
            'semester' => 'nullable|string|max:50',
            'academic_year' => 'nullable|string|max:20',
            'student_name' => 'nullable|string|max:255',
            'london_id' => 'nullable|string|max:50',
            'college_id' => 'nullable|string|max:255',
            'assignment_due_date' => 'nullable|date',
            'submission_date' => 'nullable|date',
            'submitted_to' => 'nullable|string|max:255',
        ];
    }

    /**
     * Validation rules for a finished cover, scoped to the chosen format.
     *
     * @return array<string, string>
     */
    protected function coverRules(): array
    {
        $required = $this->cover_format === 'tu'
            ? [
                'tu_college_name' => 'required|string|max:255',
                'title' => 'required|string|max:255',
                'student_name' => 'required|string|max:255',
                'tu_roll_number' => 'required|string|max:50',
            ]
            : [
                'module_code' => 'required|string|max:50',
                'module_title' => 'required|string|max:255',
                'title' => 'required|string|max:255',
                'student_name' => 'required|string|max:255',
                'london_id' => 'required|string|max:50',
                'college_id' => 'required|string|max:255',
            ];

        return array_merge($this->draftRules(), $required);
    }

    /**
     * Turn empty date strings into null so the date columns accept them.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeDates(array $data): array
    {
        foreach (['assignment_due_date', 'submission_date'] as $field) {
            if (($data[$field] ?? null) === '') {
                $data[$field] = null;
            }
        }

        return $data;
    }

    public function save()
    {
        $data = $this->normalizeDates($this->validate($this->coverRules()));

        $report = $this->report
            ? tap($this->report)->update($data)
            : Report::create($data);

        $saved = $this->report ?? $report;

        return $this->redirectRoute('reports.cover', ['report' => $saved], navigate: true);
    }

    /**
     * Persist whatever the student has entered so far, even if incomplete.
     */
    public function saveDraft()
    {
        $data = $this->normalizeDates($this->validate($this->draftRules()));

        $report = $this->report
            ? tap($this->report)->update($data)
            : Report::create($data);

        $saved = $this->report ?? $report;

        session()->flash('draft-saved', 'Draft saved — you can safely close this page and finish later.');

        return $this->redirectRoute('reports.edit', ['report' => $saved], navigate: true);
    }

    public function isEditing(): bool
    {
        return $this->report !== null;
    }
}; ?>

<div class="min-h-screen bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-3xl">
        <div class="mb-6">
            <a href="{{ route('reports.index') }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:text-indigo-500">&larr; All reports</a>
        </div>

        <div class="mb-8 text-center">
            <h1 class="text-3xl font-semibold text-gray-900">
                {{ $this->isEditing() ? 'Edit Cover Page' : 'Assignment Cover Page Generator' }}
            </h1>
            <p class="mt-2 text-sm text-gray-600">
                @if ($cover_format === 'tu')
                    Tribhuvan University
                @else
                    Islington College &middot; London Metropolitan University
                @endif
            </p>
        </div>

        @if (session('draft-saved'))
            <div class="mb-6 rounded-md bg-green-50 px-4 py-3 text-sm font-medium text-green-800 ring-1 ring-green-200">
                {{ session('draft-saved') }}
            </div>
        @endif

        <form wire:submit="save" class="space-y-8 rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:p-8">
            <section>
                <h2 class="text-base font-semibold text-gray-900">Cover format</h2>

                <div class="mt-4">
                    <label for="cover_format" class="block text-sm font-medium text-gray-700">Choose a cover page style</label>
                    <select id="cover_format" wire:model.live="cover_format" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="london_met">London Metropolitan University</option>
                        <option value="tu">Tribhuvan University (TU)</option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500">This decides which cover layout and fields are used.</p>
                    @error('cover_format') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </section>

            <section>
                <h2 class="text-base font-semibold text-gray-900">Report</h2>

                <div class="mt-4 grid grid-cols-1 gap-4">
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700">
                            {{ $cover_format === 'tu' ? 'Assignment title' : 'Report title' }} <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="title" wire:model="title" placeholder="{{ $cover_format === 'tu' ? 'e.g. Energy, Finance and Economics — Assignment No. 10' : "e.g. Amazon's Fulfilment Network" }}" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <p class="mt-1 text-xs text-gray-500">Shown on the cover and title page, and used as the report heading.</p>
                        @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="abstract" class="block text-sm font-medium text-gray-700">Abstract <span class="text-xs font-normal text-gray-400">(optional)</span></label>
                        <textarea id="abstract" wire:model="abstract" rows="5" placeholder="A short summary of the report. Appears on its own page before the contents." class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                        @error('abstract') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="section_label" class="block text-sm font-medium text-gray-700">Section heading word</label>
                        <input type="text" id="section_label" wire:model="section_label" placeholder="e.g. Chapter" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <p class="mt-1 text-xs text-gray-500">Leave blank to number sections <strong>1.</strong>, <strong>2.</strong> &hellip; Enter a word like <strong>Chapter</strong> to get <strong>Chapter 1</strong>, <strong>Chapter 2</strong>.</p>
                        @error('section_label') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            @if ($cover_format === 'tu')
            <section>
                <h2 class="text-base font-semibold text-gray-900">College</h2>

                <div class="mt-4">
                    <label for="tu_college_name" class="block text-sm font-medium text-gray-700">College name <span class="text-red-500">*</span></label>
                    <textarea id="tu_college_name" wire:model="tu_college_name" rows="2" placeholder="e.g. Institute of Engineering&#10;Pulchowk Campus" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                    <p class="mt-1 text-xs text-gray-500">Appears under "Tribhuvan University" on the cover. Use a new line for the campus.</p>
                    @error('tu_college_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </section>

            <section>
                <h2 class="text-base font-semibold text-gray-900">Submitted by</h2>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="tu_student_name" class="block text-sm font-medium text-gray-700">Name <span class="text-red-500">*</span></label>
                        <input type="text" id="tu_student_name" wire:model="student_name" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        @error('student_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="tu_roll_number" class="block text-sm font-medium text-gray-700">Roll number <span class="text-red-500">*</span></label>
                        <input type="text" id="tu_roll_number" wire:model="tu_roll_number" placeholder="e.g. 073/MSREE/519" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        @error('tu_roll_number') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="tu_submission_date" class="block text-sm font-medium text-gray-700">Date</label>
                        <input type="date" id="tu_submission_date" wire:model="submission_date" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        @error('submission_date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <section>
                <h2 class="text-base font-semibold text-gray-900">Submitted to</h2>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="tu_submitted_to" class="block text-sm font-medium text-gray-700">Name</label>
                        <input type="text" id="tu_submitted_to" wire:model="submitted_to" placeholder="e.g. Prof. Dr. Amrit Man Nakarmi" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        @error('submitted_to') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="tu_submitted_to_position" class="block text-sm font-medium text-gray-700">Position</label>
                        <input type="text" id="tu_submitted_to_position" wire:model="tu_submitted_to_position" placeholder="e.g. Department of Mechanical Engineering" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        @error('tu_submitted_to_position') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>
            @endif

            @if ($cover_format === 'london_met')
            <section>
                <h2 class="text-base font-semibold text-gray-900">Module</h2>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label for="module_code" class="block text-sm font-medium text-gray-700">Module code <span class="text-red-500">*</span></label>
                        <input type="text" id="module_code" wire:model="module_code" placeholder="e.g. MN7983NI" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        @error('module_code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="module_title" class="block text-sm font-medium text-gray-700">Module title <span class="text-red-500">*</span></label>
                        <input type="text" id="module_title" wire:model="module_title" placeholder="e.g. Management Learning and Research" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        @error('module_title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="assessment_type" class="block text-sm font-medium text-gray-700">Assessment type</label>
                        <input type="text" id="assessment_type" wire:model="assessment_type" placeholder="e.g. Individual Report" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        @error('assessment_type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="semester" class="block text-sm font-medium text-gray-700">Semester</label>
                        <select id="semester" wire:model="semester" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">&mdash; Select &mdash;</option>
                            <option value="Spring">Spring</option>
                            <option value="Autumn">Autumn</option>
                            <option value="Spring/Autumn">Spring/Autumn</option>
                        </select>
                        @error('semester') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="academic_year" class="block text-sm font-medium text-gray-700">Academic year</label>
                        <input type="text" id="academic_year" wire:model="academic_year" placeholder="e.g. 2024/25" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        @error('academic_year') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <section>
                <h2 class="text-base font-semibold text-gray-900">Student</h2>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="student_name" class="block text-sm font-medium text-gray-700">Student name <span class="text-red-500">*</span></label>
                        <input type="text" id="student_name" wire:model="student_name" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        @error('student_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="london_id" class="block text-sm font-medium text-gray-700">London Met ID <span class="text-red-500">*</span></label>
                        <input type="text" id="london_id" wire:model="london_id" placeholder="e.g. 25030253" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        @error('london_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="college_id" class="block text-sm font-medium text-gray-700">College ID <span class="text-red-500">*</span></label>
                        <input type="text" id="college_id" wire:model="college_id" placeholder="e.g. np01mb7a250180@islingtoncollege.edu.np" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        @error('college_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <section>
                <h2 class="text-base font-semibold text-gray-900">Submission</h2>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="assignment_due_date" class="block text-sm font-medium text-gray-700">Assignment due date</label>
                        <input type="date" id="assignment_due_date" wire:model="assignment_due_date" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        @error('assignment_due_date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="submission_date" class="block text-sm font-medium text-gray-700">Submission date</label>
                        <input type="date" id="submission_date" wire:model="submission_date" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        @error('submission_date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="submitted_to" class="block text-sm font-medium text-gray-700">Submitted to</label>
                        <input type="text" id="submitted_to" wire:model="submitted_to" placeholder="e.g. Ichchhuk Poudel" class="mt-1 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        @error('submitted_to') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>
            @endif

            <div class="flex flex-wrap items-center justify-end gap-3 border-t border-gray-200 pt-6">
                @if ($this->isEditing())
                    <a href="{{ route('reports.cover', ['report' => $report]) }}" wire:navigate class="mr-auto text-sm font-medium text-gray-600 hover:text-gray-900">Cancel</a>
                @endif
                <button type="button" wire:click="saveDraft" class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50">
                    <span wire:loading.remove wire:target="saveDraft">Save draft</span>
                    <span wire:loading wire:target="saveDraft">Saving...</span>
                </button>
                <button type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    <span wire:loading.remove wire:target="save">{{ $this->isEditing() ? 'Save changes' : 'Generate cover page' }}</span>
                    <span wire:loading wire:target="save">{{ $this->isEditing() ? 'Saving...' : 'Generating...' }}</span>
                </button>
            </div>
        </form>
    </div>
</div>
