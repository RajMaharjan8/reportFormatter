<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $report->title ?: $report->module_title }}</title>

    @vite(['resources/js/report.js'])

    @php
        $align = in_array($report->page_number_align, ['left', 'center', 'right'], true)
            ? $report->page_number_align
            : 'right';
        $margins = $report->pageMargins();
        $lineSpacing = $report->lineSpacing();
    @endphp

    <script>
        /* Only the hand-written print stylesheet is handed to Paged.js — its
           CSS parser cannot digest the compiled Tailwind bundle. Page numbers
           are applied after pagination by report.js. */
        window.reportStylesheets = [
            @json(asset('css/report.css').'?v='.filemtime(public_path('css/report.css'))),
        ];
        window.reportPageAlign = @json($align);
        window.reportPageMargins = @json($margins);
        /* Per-report margin + line-spacing rules — picked up by report.js which
           turns this string into a Blob URL and appends it to the stylesheet
           list passed to Paged.js, so it layers on top of report.css. */
        window.reportInlineCss = `
            @page {
                margin:
                    {{ number_format($margins['top'], 2) }}in
                    {{ number_format($margins['right'], 2) }}in
                    {{ number_format($margins['bottom'], 2) }}in
                    {{ number_format($margins['left'], 2) }}in;
            }
            .report-doc { line-height: {{ number_format($lineSpacing, 2) }}; }
            .report-content p { line-height: {{ number_format($lineSpacing, 2) }}; }
        `;
    </script>

    <style>
        body { margin: 0; background: #ffffff; padding-top: 56px; font-family: system-ui, sans-serif; }

        /* Outline each rendered page so it reads as a sheet on the white background */
        #report-render .pagedjs_page { box-shadow: 0 0 0 1px #e5e7eb; }

        .report-toolbar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 50;
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px; padding: 10px 20px;
            background: #fff; border-bottom: 1px solid #d1d5db;
        }
        .report-toolbar a { color: #4f46e5; text-decoration: none; font-size: 14px; font-weight: 600; }
        .report-actions { display: flex; align-items: center; gap: 10px; }
        .report-toolbar button {
            background: #4f46e5; color: #fff; border: 0; border-radius: 6px;
            padding: 8px 16px; font-size: 14px; font-weight: 600; cursor: pointer;
        }
        .report-toolbar button:hover { background: #4338ca; }
        .report-toolbar .report-download {
            border: 1px solid #4f46e5; border-radius: 6px;
            padding: 7px 16px; font-size: 14px; font-weight: 600;
        }
        .report-toolbar .report-download:hover { background: #eef2ff; }

        .report-loading { padding: 80px 20px; text-align: center; color: #6b7280; font-size: 14px; }
        body.is-paginated .report-loading { display: none; }

        #report-render { padding: 24px 0; }

        @media print {
            body { background: #fff; padding-top: 0; }
            .report-toolbar, .report-loading { display: none !important; }
            #report-render { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="report-toolbar">
        <a href="{{ route('reports.sections', ['report' => $report]) }}">&larr; Back to editor</a>
        <div class="report-actions">
            {{-- Word download hidden for now --}}
            {{-- <a href="{{ route('reports.docx', ['report' => $report]) }}" class="report-download">Download Word</a> --}}
            <button type="button" onclick="window.print()">Print / Save as PDF</button>
        </div>
    </div>

    <div class="report-loading">Preparing your report&hellip;</div>

    <div id="report-render"></div>

    <template id="report-source">
        <div class="report-doc">
            {{-- Cover page (plain CSS so Paged.js needs no Tailwind) --}}
            @if ($report->cover_format === 'tu')
            <div class="report-cover">
                <div class="cover-sheet-plain tu-cover">
                    <div>
                        <h1 class="tu-cover-uni">TRIBHUVAN UNIVERSITY</h1>
                        @if ($report->tu_college_name)
                            <div class="tu-cover-college">{!! nl2br(e($report->tu_college_name)) !!}</div>
                        @endif
                    </div>

                    <div class="tu-cover-logo">
                        <img src="{{ asset('images/tu/tulogo.png') }}" alt="Tribhuvan University">
                    </div>

                    <div class="tu-cover-rule">
                        <span></span><span></span><span></span>
                    </div>

                    @if ($report->title)
                        <p class="tu-cover-title">{{ $report->title }}</p>
                    @endif

                    <div class="tu-cover-people">
                        <div>
                            <p class="tu-cover-label">SUBMITTED BY:</p>
                            <p><strong>Name:</strong> {{ $report->student_name }}</p>
                            @if ($report->tu_roll_number)
                                <p><strong>Roll No:</strong> {{ $report->tu_roll_number }}</p>
                            @endif
                            @if ($report->submission_date)
                                <p><strong>Date:</strong> {{ $report->submission_date->format('Y-m-d') }}</p>
                            @endif
                        </div>
                        <div>
                            <p class="tu-cover-label">SUBMITTED TO:</p>
                            @if ($report->submitted_to)
                                <p><strong>{{ $report->submitted_to }}</strong></p>
                            @endif
                            @if ($report->tu_submitted_to_position)
                                <p><strong>{{ $report->tu_submitted_to_position }}</strong></p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @else
            <div class="report-cover">
                <div class="cover-sheet-plain">
                    <div class="cover-logo-row">
                        <img src="{{ asset('images/london-met-logo.png') }}" alt="London Metropolitan University">
                    </div>

                    <div class="cover-logo-college">
                        <img src="{{ asset('images/islington-logo.png') }}" alt="Islington College">
                    </div>

                    <div class="cover-block">
                        <p>Module Code &amp; Module Title</p>
                        <p>{{ trim($report->module_code.' '.$report->module_title) }}</p>
                    </div>

                    <div class="cover-block">
                        <p>{{ $report->assessment_type ?: 'Assessment Type' }}</p>
                        <p>Semester</p>
                        <p>{{ trim(($report->academic_year ?? '').' '.($report->semester ?? '')) ?: 'Semester' }}</p>
                    </div>

                    <div class="cover-block">
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

                    <div class="cover-confirm">
                        <p>
                            I confirm that I understand my coursework needs to be submitted online via Google Classroom under
                            the relevant module page before the deadline in order for my assignment to be accepted and marked.
                            I am fully aware that late submissions will be treated as non-submission and a mark of zero will be
                            awarded.
                        </p>
                    </div>
                </div>
            </div>
            @endif

            {{-- Title page --}}
            <section class="report-frontmatter page-break report-title-page">
                <h1 class="doc-title">{{ $report->title ?: $report->module_title }}</h1>
            </section>

            {{-- Custom front pages (before the contents, unnumbered) --}}
            @foreach ($compiler->frontMatter() as $page)
                <section class="report-frontmatter page-break">
                    @if (filled($page['title']))
                        <h2 class="frontmatter-heading">{{ $page['title'] }}</h2>
                    @endif
                    <div class="report-content">
                        @if (trim($page['html']) !== '')
                            {!! $page['html'] !!}
                        @else
                            <p>No content yet.</p>
                        @endif
                    </div>
                </section>
            @endforeach

            {{-- Table of Contents --}}
            <section class="report-frontmatter page-break">
                <h2 class="frontmatter-heading">Table of Contents</h2>
                <ul class="toc">
                    @forelse ($compiler->contents() as $entry)
                        <li class="toc-entry toc-level-{{ $entry['level'] }}">
                            <a href="#{{ $entry['id'] }}"><span class="toc-label">{{ $entry['marker'] }}&nbsp; {{ $entry['label'] }}</span></a>
                        </li>
                    @empty
                        <li class="toc-empty">No sections yet &mdash; add sections in the editor.</li>
                    @endforelse
                </ul>
            </section>

            {{-- Table of Tables --}}
            @if ($compiler->hasTables())
                <section class="report-frontmatter page-break">
                    <h2 class="frontmatter-heading">Table of Tables</h2>
                    <ul class="toc">
                        @foreach ($compiler->tables() as $table)
                            <li class="toc-entry">
                                <a href="#{{ $table['id'] }}"><span class="toc-label">{{ $table['label'] }}</span></a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- Table of Figures --}}
            @if ($compiler->hasFigures())
                <section class="report-frontmatter page-break">
                    <h2 class="frontmatter-heading">Table of Figures</h2>
                    <ul class="toc">
                        @foreach ($compiler->figures() as $figure)
                            <li class="toc-entry">
                                <a href="#{{ $figure['id'] }}"><span class="toc-label">{{ $figure['label'] }}</span></a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- Abstract --}}
            @if (filled($report->abstract))
                <section class="report-frontmatter page-break">
                    <h2 class="frontmatter-heading">Abstract</h2>
                    <div class="abstract-text">{{ $report->abstract }}</div>
                </section>
            @endif

            {{-- Body --}}
            @forelse ($compiler->sections() as $section)
                <section class="report-bodymatter report-section page-break">
                    <h1 class="section-title" id="{{ $section['id'] }}">{{ $section['marker'] }}&nbsp; {{ $section['title'] }}</h1>
                    <div class="report-content">
                        @if (trim($section['html']) !== '')
                            {!! $section['html'] !!}
                        @else
                            <p>No content yet.</p>
                        @endif
                    </div>
                </section>
            @empty
                <section class="report-bodymatter report-section page-break">
                    <p>No sections yet. Add sections in the editor to build the report.</p>
                </section>
            @endforelse
        </div>
    </template>
</body>
</html>
