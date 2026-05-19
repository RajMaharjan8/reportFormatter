<main class="cover-sheet report-page mx-auto my-6 flex h-[297mm] w-[210mm] flex-col bg-white px-16 py-12 shadow-md ring-1 ring-gray-200">
    <div class="flex justify-end">
        <img src="{{ asset('images/london-met-logo.png') }}" alt="London Metropolitan University" class="h-16 w-auto object-contain">
    </div>

    <div class="mt-8 flex justify-center">
        <img src="{{ asset('images/islington-logo.png') }}" alt="Islington College" class="h-40 w-auto object-contain">
    </div>

    <div class="mt-16 text-center font-bold text-gray-900">
        <p>Module Code &amp; Module Title</p>
        <p>{{ $report->module_code }} {{ $report->module_title }}</p>
    </div>

    <div class="mt-10 text-center font-bold text-gray-900">
        @if ($report->assessment_type)
            <p>{{ $report->assessment_type }}</p>
        @else
            <p>Assessment Type</p>
        @endif
        <p>Semester</p>
        <p>
            @if ($report->academic_year){{ $report->academic_year }}@endif
            @if ($report->academic_year && $report->semester) @endif
            @if ($report->semester){{ $report->semester }}@endif
        </p>
    </div>

    <div class="mt-12 text-center font-bold text-gray-900">
        <p>Student Name: {{ $report->student_name }}</p>
        <p>London Met ID: {{ $report->london_id }}</p>
        <p>College ID: {{ $report->college_id }}</p>
        @if ($report->assignment_due_date)
            <p>Assignment Due Date: {{ $report->assignment_due_date->format('l, F j, Y') }}</p>
        @endif
        @if ($report->submission_date)
            <p>Assignment Submission Date: {{ $report->submission_date->format('l, F j, Y') }}</p>
        @endif
        @if ($report->submitted_to)
            <p>Submitted To: {{ $report->submitted_to }}</p>
        @endif
    </div>

    <div class="mt-auto px-4 text-center text-sm italic text-gray-800">
        <p>
            I confirm that I understand my coursework needs to be submitted online via Google Classroom under
            the relevant module page before the deadline in order for my assignment to be accepted and marked.
            I am fully aware that late submissions will be treated as non-submission and a mark of zero will be
            awarded.
        </p>
    </div>
</main>
