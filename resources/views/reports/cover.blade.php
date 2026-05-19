<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cover Page &mdash; {{ $report->student_name }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @page { size: A4; margin: 0; }
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .cover-sheet { box-shadow: none !important; margin: 0 !important; }
        }
        summary { list-style: none; }
        summary::-webkit-details-marker { display: none; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="no-print mx-auto max-w-[210mm] px-4 py-4">
        @if (session('cover-saved'))
            <div class="mb-3 rounded-md bg-green-50 px-4 py-2 text-sm font-medium text-green-800 ring-1 ring-green-200">
                {{ session('cover-saved') }}
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('reports.create') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">&larr; Create another</a>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('reports.edit', ['report' => $report]) }}" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50">Edit cover</a>

                {{-- Add / edit abstract --}}
                <details class="relative">
                    <summary class="cursor-pointer rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50">
                        {{ filled($report->abstract) ? 'Edit Abstract' : 'Add Abstract' }}
                    </summary>
                    <div class="absolute right-0 z-20 mt-2 w-80 rounded-md bg-white p-4 text-left shadow-lg ring-1 ring-gray-200">
                        <form method="POST" action="{{ route('reports.cover.settings', ['report' => $report]) }}">
                            @csrf
                            <label for="abstract" class="block text-xs font-semibold text-gray-700">Abstract</label>
                            <p class="mb-1 text-[11px] text-gray-500">Optional. Appears on its own page before the contents.</p>
                            <textarea id="abstract" name="abstract" rows="6" placeholder="A short summary of the report…" class="block w-full rounded-md px-2 py-1.5 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">{{ $report->abstract }}</textarea>
                            <button type="submit" class="mt-2 w-full rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-500">Save abstract</button>
                        </form>
                    </div>
                </details>

                {{-- Heading 1 label --}}
                <details class="relative">
                    <summary class="cursor-pointer rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50">
                        Heading 1 Edit
                    </summary>
                    <div class="absolute right-0 z-20 mt-2 w-80 rounded-md bg-white p-4 text-left shadow-lg ring-1 ring-gray-200">
                        <form method="POST" action="{{ route('reports.cover.settings', ['report' => $report]) }}">
                            @csrf
                            <label for="section_label" class="block text-xs font-semibold text-gray-700">Heading 1 word</label>
                            <p class="mb-1 text-[11px] text-gray-500">Leave blank for <strong>1.</strong>, <strong>2.</strong> &hellip; Type a word like <strong>Chapter</strong> to get <strong>Chapter 1</strong>. Only Heading 1 changes &mdash; 1.1 and 1.1.1 stay numeric.</p>
                            <input type="text" id="section_label" name="section_label" value="{{ $report->section_label }}" placeholder="e.g. Chapter" class="block w-full rounded-md px-2 py-1.5 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <button type="submit" class="mt-2 w-full rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-500">Save heading</button>
                        </form>
                    </div>
                </details>

                {{-- Page number position --}}
                <details class="relative">
                    <summary class="cursor-pointer rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50">
                        Page number
                    </summary>
                    <div class="absolute right-0 z-20 mt-2 w-64 rounded-md bg-white p-4 text-left shadow-lg ring-1 ring-gray-200">
                        <form method="POST" action="{{ route('reports.cover.settings', ['report' => $report]) }}">
                            @csrf
                            <label for="page_number_align" class="block text-xs font-semibold text-gray-700">Page number position</label>
                            <select id="page_number_align" name="page_number_align" class="mt-1 block w-full rounded-md px-2 py-1.5 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="left" @selected($report->page_number_align === 'left')>Left</option>
                                <option value="center" @selected($report->page_number_align === 'center')>Center</option>
                                <option value="right" @selected($report->page_number_align !== 'left' && $report->page_number_align !== 'center')>Right</option>
                            </select>
                            <button type="submit" class="mt-2 w-full rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-500">Save</button>
                        </form>
                    </div>
                </details>

                <a href="{{ route('reports.sections', ['report' => $report]) }}" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50">Write content</a>
                <a href="{{ route('reports.output', ['report' => $report]) }}" class="rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-700">Full report</a>
                <button type="button" onclick="window.print()" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Print / Save as PDF</button>
            </div>
        </div>
    </div>

    @include('reports.partials.cover-sheet')
</body>
</html>
