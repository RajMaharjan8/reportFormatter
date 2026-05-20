<style>
    .tu-sheet {
        box-sizing: border-box;
        width: 210mm;
        height: 297mm;
        padding: 22mm 24mm 38mm;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        text-align: center;
        font-family: "Times New Roman", Times, serif;
        color: #111;
        background: #fff;
    }
    .tu-uni { margin: 0; font-size: 32px; font-weight: 700; letter-spacing: .5px; }
    .tu-college { margin-top: 6px; font-size: 22px; font-weight: 700; line-height: 1.3; }
    .tu-logo { margin-top: 30px; }
    .tu-logo img { height: 165px; width: auto; object-fit: contain; }
    .tu-rule { margin-top: 26px; display: flex; justify-content: center; gap: 14px; }
    .tu-rule span { display: block; width: 5px; height: 105px; background: #5b9bd5; }
    .tu-title { margin: auto 0 0; font-size: 21px; font-weight: 700; text-decoration: underline; }
    .tu-people {
        margin-top: auto; display: flex; justify-content: space-between;
        gap: 40px; text-align: left; font-size: 15px;
    }
    .tu-people > div { width: 48%; }
    .tu-people p { margin: 3px 0; }
    .tu-label { font-weight: 700; text-decoration: underline; margin-bottom: 10px !important; }
</style>

<main class="cover-sheet report-page tu-sheet mx-auto my-6 shadow-md ring-1 ring-gray-200">
    <div>
        <h1 class="tu-uni">TRIBHUVAN UNIVERSITY</h1>
        @if ($report->tu_college_name)
            <div class="tu-college">{!! nl2br(e($report->tu_college_name)) !!}</div>
        @endif
    </div>

    <div class="tu-logo">
        <img src="{{ asset('images/tu/tulogo.png') }}" alt="Tribhuvan University">
    </div>

    <div class="tu-rule">
        <span></span><span></span><span></span>
    </div>

    @if ($report->title)
        <p class="tu-title">{{ $report->title }}</p>
    @endif

    <div class="tu-people">
        <div>
            <p class="tu-label">SUBMITTED BY:</p>
            <p><strong>Name:</strong> {{ $report->student_name }}</p>
            @if ($report->tu_roll_number)
                <p><strong>Roll No:</strong> {{ $report->tu_roll_number }}</p>
            @endif
            @if ($report->submission_date)
                <p><strong>Date:</strong> {{ $report->submission_date->format('Y-m-d') }}</p>
            @endif
        </div>
        <div>
            <p class="tu-label">SUBMITTED TO:</p>
            @if ($report->submitted_to)
                <p><strong>{{ $report->submitted_to }}</strong></p>
            @endif
            @if ($report->tu_submitted_to_position)
                <p><strong>{{ $report->tu_submitted_to_position }}</strong></p>
            @endif
        </div>
    </div>
</main>
