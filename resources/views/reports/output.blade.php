<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $report->title ?: $report->module_title }}</title>

    @include('partials.pwa-head')

    @vite(['resources/js/report.js'])

    @php
        $align = in_array($report->page_number_align, ['left', 'center', 'right'], true)
            ? $report->page_number_align
            : 'right';
        $margins = $report->pageMargins();
        $lineSpacing = $report->lineSpacing();
        $headingAlign = $report->headingAlign();
        $headingTransform = $report->heading_uppercase ? 'uppercase' : 'none';
        // "Edit pages" mode: only owners (update ability) and only when ?edit=1.
        $canEdit = request()->user()?->can('update', $report) ?? false;
        $editing = $canEdit && request()->boolean('edit');
    @endphp

    @if ($editing)
        {{-- Edit mode renders the raw (un-paginated) sheets, so it needs the
             print stylesheet loaded directly rather than via Paged.js. --}}
        <link rel="stylesheet" href="{{ asset('css/report.css') }}?v={{ filemtime(public_path('css/report.css')) }}">
    @endif

    <script>
        /* Only the hand-written print stylesheet is handed to Paged.js — its
           CSS parser cannot digest the compiled Tailwind bundle. Page numbers
           are applied after pagination by report.js. */
        window.reportStylesheets = [
            @json(asset('css/report.css').'?v='.filemtime(public_path('css/report.css'))),
        ];
        window.reportPageAlign = @json($align);
        window.reportPageMargins = @json($margins);
        /* The roman numeral printed on the first front page. TU reports number
           their declaration page "i" (the cover is unnumbered and uncounted);
           every other format keeps the cover as page i and starts front matter
           at "ii". */
        window.reportRomanStart = @json($report->cover_format === 'tu' ? 1 : 2);
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
            .section-title { text-align: {{ $headingAlign }}; text-transform: {{ $headingTransform }}; }
            .toc-level-1 .toc-label { text-transform: {{ $headingTransform }}; }
        `;
    </script>

    <style>
        body { margin: 0; background: #ffffff; font-family: system-ui, sans-serif; }

        /* Outline each rendered page so it reads as a sheet on the white background */
        #report-render .pagedjs_page { box-shadow: 0 0 0 1px #e5e7eb; }
        /* Center the sheets in the available width instead of hugging the left. */
        #report-render .pagedjs_pages { display: flex; flex-direction: column; align-items: center; }
        #report-render .pagedjs_page { margin-left: auto; margin-right: auto; }

        .report-toolbar {
            position: sticky; top: 0; z-index: 50;
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 8px 12px; padding: 10px 20px;
            background: #fff; border-bottom: 1px solid #d1d5db;
        }
        .report-toolbar a { color: #4f46e5; text-decoration: none; font-size: 14px; font-weight: 600; }
        .report-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
        .report-toolbar button {
            background: #4f46e5; color: #fff; border: 0; border-radius: 6px;
            padding: 8px 16px; font-size: 14px; font-weight: 600; cursor: pointer;
        }
        .report-toolbar button:hover { background: #4338ca; }
        .report-toolbar .report-download {
            display: inline-flex; align-items: center;
            border: 1px solid #4f46e5; border-radius: 6px;
            padding: 7px 16px; font-size: 14px; font-weight: 600;
        }
        .report-toolbar .report-download:hover { background: #eef2ff; }

        .report-loading { padding: 80px 20px; text-align: center; color: #6b7280; font-size: 14px; }
        body.is-paginated .report-loading { display: none; }

        /* Scaled to fit the viewport on small screens by report.js (zoom). */
        #report-render { padding: 24px 0; }

        @media (max-width: 640px) {
            .report-toolbar { padding: 8px 12px; gap: 6px; }
            .report-toolbar a, .report-toolbar button, .report-toolbar .report-download { font-size: 13px; }
            .report-toolbar button, .report-toolbar .report-download { padding: 6px 12px; }
            .report-actions { gap: 6px; }
            #report-render { padding: 12px 0; }
        }

        @media print {
            body { background: #fff; padding-top: 0; }
            .report-toolbar, .report-loading { display: none !important; }
            #report-render { padding: 0; zoom: 1 !important; }
        }

        /* ---- Edit pages mode ---- */
        .edit-toolbar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 50;
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px; padding: 10px 16px; flex-wrap: wrap;
            background: #fff; border-bottom: 1px solid #d1d5db;
        }
        .edit-toolbar a { color: #4f46e5; text-decoration: none; font-size: 14px; font-weight: 600; }
        .edit-toolbar .edit-actions { display: flex; align-items: center; gap: 10px; }
        .edit-toolbar button { border-radius: 6px; padding: 8px 16px; font-size: 14px; font-weight: 600; cursor: pointer; border: 0; }
        .edit-toolbar .btn-save { background: #4f46e5; color: #fff; }
        .edit-toolbar .btn-save:hover { background: #4338ca; }
        .edit-toolbar .btn-reset { background: #fff; color: #b91c1c; border: 1px solid #fecaca; }
        .edit-toolbar .btn-reset:hover { background: #fef2f2; }

        .edit-hint { max-width: 210mm; margin: 8px auto -8px; padding: 0 8px; color: #6b7280; font-size: 13px; }
        .edit-flash { max-width: 210mm; margin: 8px auto; padding: 8px 14px; border-radius: 6px; background: #ecfdf5; color: #065f46; font-size: 14px; font-weight: 500; }

        .edit-doc { padding: 16px 12px 64px; }
        /* Each editable block looks like an A4 sheet. */
        .edit-doc .report-cover,
        .edit-doc .tu-frontpage {
            width: 210mm; min-height: 297mm; margin: 0 auto 24px; box-sizing: border-box;
            background: #fff; box-shadow: 0 0 0 1px #e5e7eb, 0 8px 24px rgba(0,0,0,.10);
        }
        /* Front pages get the page margins as padding (cover supplies its own). */
        .edit-doc .tu-frontpage { padding: 25.4mm 25.4mm 25.4mm 38.1mm; }
        .edit-doc .report-cover { height: auto; overflow: visible; }
        .edit-doc [contenteditable]:focus { outline: 2px solid #6366f1; outline-offset: 2px; }
        .edit-doc [contenteditable] { cursor: text; }
    </style>
</head>
<body>
@if ($editing)
    {{-- ============ EDIT PAGES MODE ============ --}}
    <div class="edit-toolbar">
        <a href="{{ route('reports.output', ['report' => $report]) }}">&larr; Done editing</a>
        <div class="edit-actions">
            <form method="POST" action="{{ route('reports.front-overrides.reset', ['report' => $report]) }}" onsubmit="return confirm('Reset all pages back to the generated template? Your edits will be lost.');">
                @csrf
                <button type="submit" class="btn-reset">Reset all</button>
            </form>
            <button type="button" class="btn-save" onclick="saveEdits()">Save changes</button>
        </div>
    </div>

    @if (session('cover-saved'))
        <div class="edit-flash">{{ session('cover-saved') }}</div>
    @endif

    <p class="edit-hint">Click any page and type to edit it &mdash; press Enter for new lines/spacing. Click <strong>Save changes</strong> when done, or <strong>Reset all</strong> to restore the generated pages.</p>

    <div class="edit-doc report-doc">
        @include('reports.partials.output-cover', ['editing' => true])

        @if ($report->cover_format === 'tu')
            @include('reports.partials.tu-front-matter', ['editable' => true])
        @endif
    </div>

    <form id="save-form" method="POST" action="{{ route('reports.front-overrides.save', ['report' => $report]) }}" style="display:none">
        @csrf
        @foreach ($report->editableFrontBlocks() as $block)
            <textarea name="blocks[{{ $block }}]" data-input="{{ $block }}"></textarea>
        @endforeach
    </form>

    <script>
        function saveEdits() {
            document.querySelectorAll('#save-form [data-input]').forEach(function (input) {
                var block = document.querySelector('[data-block="' + input.dataset.input + '"]');
                if (block) {
                    input.value = block.innerHTML;
                }
            });
            document.getElementById('save-form').submit();
        }
    </script>
@else
    {{-- ============ VIEW MODE ============ --}}
    <div class="report-toolbar">
        <a href="{{ route('reports.sections', ['report' => $report]) }}">&larr; Back to editor</a>
        <div class="report-actions">
            @if ($canEdit)
                <a href="{{ route('reports.output', ['report' => $report, 'edit' => 1]) }}" class="report-download">Edit pages</a>
            @endif
            <button type="button" onclick="window.print()">Print / Save as PDF</button>
        </div>
    </div>

    <div class="report-loading">Preparing your report&hellip;</div>

    <div id="report-render"></div>

    <template id="report-source">
        <div class="report-doc">
            {{-- Cover page (plain CSS so Paged.js needs no Tailwind) --}}
            @include('reports.partials.output-cover')

            @if ($report->cover_format === 'tu')
                {{-- Standard TU front matter: Declaration, Recommendation, Approval --}}
                @include('reports.partials.tu-front-matter')
            @else
                {{-- Title page --}}
                <section class="report-frontmatter page-break report-title-page">
                    <h1 class="doc-title">{{ $report->title ?: $report->module_title }}</h1>
                </section>
            @endif

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
@endif
</body>
</html>
