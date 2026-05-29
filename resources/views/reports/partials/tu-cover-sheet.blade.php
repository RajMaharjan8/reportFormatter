@php
    $students = is_array($report->tu_students) ? array_values(array_filter($report->tu_students, fn ($s) => is_array($s) && (filled($s['name'] ?? null) || filled($s['roll'] ?? null)))) : [];
    if ($students === [] && filled($report->student_name)) {
        $students[] = [
            'name' => $report->student_name,
            'roll' => $report->tu_roll_number,
            'batch' => null,
        ];
    }
@endphp

<main class="cover-sheet report-page tu-sheet mx-auto my-6 shadow-md ring-1 ring-gray-200">
    <h1 class="tu-uni">Tribhuvan University</h1>

    @if (filled($report->tu_institute))
        <div class="tu-institute">{{ $report->tu_institute }}</div>
    @endif

    @if (filled($report->tu_college_name))
        <div class="tu-campus">{!! nl2br(e($report->tu_college_name)) !!}</div>
    @endif

    <div class="tu-logo">
        <img src="{{ asset('images/tu/tulogo.png') }}" alt="Tribhuvan University">
    </div>

    @if (filled($report->tu_report_type))
        <div class="tu-report-type">
            {{ $report->tu_report_type }}
            <div class="tu-on">on</div>
        </div>
    @endif

    @if (filled($report->title))
        <p class="tu-title">{{ $report->title }}</p>
    @endif

    @if (filled($report->tu_supervisor_name) || filled($report->tu_submitted_to_position))
        <p class="tu-supervisor-label">Under the Supervision of</p>

        @if (filled($report->tu_supervisor_name))
            <p class="tu-supervisor-name">{{ $report->tu_supervisor_name }}</p>
        @endif

        @if (filled($report->tu_department))
            <p class="tu-supervisor-detail">{{ $report->tu_department }}</p>
        @endif

        @if (filled($report->tu_campus_address))
            <p class="tu-supervisor-detail">{{ $report->tu_campus_address }}</p>
        @endif
    @endif

    @if (filled($report->tu_degree))
        <p class="tu-degree">In partial fulfillment of the requirements for the {{ $report->tu_degree }} of Tribhuvan University</p>
    @endif

    @if ($students !== [])
        <p class="tu-block-label">Submitted by:</p>
        <div class="tu-students">
            @foreach ($students as $student)
                <p>
                    {{ $student['name'] ?? '' }}
                    @if (filled($student['roll'] ?? null))
                        / Roll No. {{ $student['roll'] }}
                    @endif
                    @if (filled($student['batch'] ?? null))
                        / Batch {{ $student['batch'] }}
                    @endif
                </p>
            @endforeach
        </div>
    @endif

    @if (filled($report->tu_department) || filled($report->tu_campus_address) || filled($report->submitted_to))
        <p class="tu-block-label">Submitted to:</p>
        <div class="tu-submitted-to">
            @if (filled($report->tu_department))
                <p>{{ $report->tu_department }}</p>
            @endif
            @if (filled($report->tu_campus_address))
                <p>{{ $report->tu_campus_address }}</p>
            @endif
            <p>Tribhuvan University</p>
        </div>
    @endif

    @if ($report->submission_date)
        <p class="tu-date">{{ $report->submission_date->format('F, Y') }}</p>
    @endif
</main>
