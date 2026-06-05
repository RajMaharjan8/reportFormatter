{{-- The rich section editor (unchanged behaviour) shown inside the active
     chapter card. It mirrors its content into the shared `preview` Alpine
     store as the user types so the preview pane updates live, and tells the
     preview to scroll to this section's page when opened or focused. --}}
@php($activeKey = ($activeSection->isFrontPage() ? 'front-' : 'sec-').$activeSection->id)
<div
    wire:key="editor-{{ $activeSection->id }}"
    x-data="editor({
        initialContent: @js(\App\Support\SectionContent::toHtml($activeSection->content)),
        references: @js($this->referencesPayload),
        citationFormat: @js($report->citationFormat()),
    })"
    x-init="$nextTick(() => { mountEditor(); $store.preview.html = $refs.content.innerHTML; $store.preview.title = @js($activeSection->title); $dispatch('preview-goto', { key: @js($activeKey) }) })"
    @references-updated.window="onReferencesUpdated($event.detail)"
    style="counter-reset: section {{ $this->activeSectionNumber }}"
    class="border-t border-gray-200 bg-white"
>
    {{-- Title + save --}}
    <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 px-4 py-2">
        <input type="text" wire:model="editTitle" x-on:input="$store.preview.title = $event.target.value" x-on:focus="$dispatch('preview-goto', { key: @js($activeKey) })" placeholder="Section title" class="min-w-50 flex-1 rounded-md px-2 py-1 text-sm font-semibold ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <div class="ml-auto flex items-center gap-2">
            <span x-show="dirty" class="text-xs text-amber-600">Unsaved changes</span>
            <button type="button" x-on:click="saveTo($wire)" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-500">
                <span wire:loading.remove wire:target="save">Save</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
        </div>
    </div>

    {{-- Formatting toolbar --}}
    <div class="se-toolbar">
        <button type="button" x-on:mousedown.prevent x-on:click="setBlock('p')" class="toolbar-btn">Normal</button>
        <button type="button" x-on:mousedown.prevent x-on:click="setBlock('h2')" class="toolbar-btn font-semibold" title="Heading 2 — numbered 1.1">Heading 2</button>
        <button type="button" x-on:mousedown.prevent x-on:click="setBlock('h3')" class="toolbar-btn font-semibold" title="Heading 3 — numbered 1.1.1">Heading 3</button>
        <span class="toolbar-divider"></span>
        <button type="button" x-on:mousedown.prevent x-on:click="run('bold')" class="toolbar-btn font-bold">B</button>
        <button type="button" x-on:mousedown.prevent x-on:click="run('italic')" class="toolbar-btn italic">I</button>
        <button type="button" x-on:mousedown.prevent x-on:click="run('underline')" class="toolbar-btn underline">U</button>
        <span class="toolbar-divider"></span>
        <button type="button" x-on:mousedown.prevent x-on:click="run('justifyLeft')" class="toolbar-btn" title="Align left">Left</button>
        <button type="button" x-on:mousedown.prevent x-on:click="run('justifyCenter')" class="toolbar-btn" title="Align center">Center</button>
        <button type="button" x-on:mousedown.prevent x-on:click="run('justifyRight')" class="toolbar-btn" title="Align right">Right</button>
        <button type="button" x-on:mousedown.prevent x-on:click="run('justifyFull')" class="toolbar-btn" title="Justify">Justify</button>
        <span class="toolbar-divider"></span>
        <button type="button" x-on:mousedown.prevent x-on:click="run('insertUnorderedList')" class="toolbar-btn">&bull; List</button>
        <button type="button" x-on:mousedown.prevent x-on:click="run('insertOrderedList')" class="toolbar-btn">1. List</button>
        <span class="toolbar-divider"></span>
        <button type="button" x-on:mousedown.prevent x-on:click="saveSelection(); $refs.imageInput.click()" class="toolbar-btn text-indigo-600" title="Insert an image with a figure caption" aria-label="Insert image">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" /></svg>
        </button>
        <button type="button" x-on:mousedown.prevent x-on:click="insertTable()" class="toolbar-btn text-indigo-600" title="Insert a table with a name" aria-label="Insert table">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 01-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0112 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m0 0h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m12-9.75v9.75m0-9.75c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m1.125-3.75H12m9.75 0c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m0 0h-7.5" /></svg>
        </button>
        <input type="file" x-ref="imageInput" accept="image/*" class="hidden" x-on:change="insertImage($event)">
        <span class="toolbar-divider"></span>
        <button type="button" x-on:mousedown.prevent x-on:click="openCitePicker()" class="toolbar-btn font-medium text-indigo-600" title="Insert a citation (ref here) — pick which reference to use">Cite</button>
        <button type="button" x-on:mousedown.prevent x-on:click="insertReferencesList()" class="toolbar-btn font-medium text-indigo-600" title="Insert the auto-generated references list — lists only the references used in this report">References list</button>
        <span class="toolbar-divider"></span>
        <span class="se-group-label">Table:</span>
        <button type="button" x-on:mousedown.prevent x-on:click="addRow()" class="toolbar-btn" title="Add a row below the cursor">+ Row</button>
        <button type="button" x-on:mousedown.prevent x-on:click="deleteRow()" class="toolbar-btn" title="Delete the current row">&minus; Row</button>
        <button type="button" x-on:mousedown.prevent x-on:click="addColumn()" class="toolbar-btn" title="Add a column right of the cursor">+ Col</button>
        <button type="button" x-on:mousedown.prevent x-on:click="deleteColumn()" class="toolbar-btn" title="Delete the current column">&minus; Col</button>
        <button type="button" x-on:mousedown.prevent x-on:click="resizeColumn(6)" class="toolbar-btn" title="Make the current column wider">Col wider</button>
        <button type="button" x-on:mousedown.prevent x-on:click="resizeColumn(-6)" class="toolbar-btn" title="Make the current column narrower">Col narrower</button>
        <span class="toolbar-divider"></span>
        <span class="se-group-label">Image:</span>
        <button type="button" x-on:mousedown.prevent x-on:click="resizeImage(-10)" class="toolbar-btn" title="Click an image, then shrink it">Smaller</button>
        <button type="button" x-on:mousedown.prevent x-on:click="resizeImage(10)" class="toolbar-btn" title="Click an image, then enlarge it">Larger</button>
    </div>

    {{-- Citation picker --}}
    <div x-show="citePickerOpen" x-cloak x-on:click.outside="closeCitePicker()" class="border-b border-indigo-100 bg-indigo-50 px-4 py-3">
        <div class="flex items-center justify-between">
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-700">Insert citation</p>
            <button type="button" x-on:click="closeCitePicker()" class="text-xs text-indigo-700 hover:text-indigo-900">Close</button>
        </div>
        <template x-if="references.length === 0">
            <p class="mt-2 text-xs text-gray-600">No references yet — add one via <em>Manage References</em>.</p>
        </template>
        <ul class="mt-2 max-h-48 space-y-1 overflow-y-auto">
            <template x-for="reference in references" :key="reference.id">
                <li>
                    <button
                        type="button"
                        x-on:mousedown.prevent
                        x-on:click="insertCitation(reference.id)"
                        class="flex w-full items-center justify-between rounded-md bg-white px-3 py-1.5 text-left text-xs ring-1 ring-indigo-200 hover:bg-indigo-100"
                    >
                        <span x-text="reference.label" class="truncate pr-3"></span>
                        <span class="shrink-0 font-mono text-[11px] text-indigo-700" x-text="reference.inline[citationFormat] || ''"></span>
                    </button>
                </li>
            </template>
        </ul>
    </div>

    {{-- Editable area. The figure/table counters start from the number of
         figures/tables in earlier sections so the editor matches the report's
         continuous numbering. --}}
    <div wire:ignore>
        <div x-ref="content" contenteditable="true" spellcheck="true"
            x-on:input="$store.preview.html = $event.target.innerHTML"
            x-on:focus="$dispatch('preview-goto', { key: @js($activeKey) })"
            style="counter-reset: h2 0 figure {{ $this->figureTableOffset['figures'] }} table {{ $this->figureTableOffset['tables'] }}"
            class="se-content {{ $activeSection->isFrontPage() ? 'se-front-page' : '' }}"></div>
    </div>

    {{-- Transient notice (replaces window.alert) --}}
    <div x-show="notice" x-transition x-cloak class="pointer-events-none fixed inset-x-0 bottom-6 z-50 flex justify-center">
        <div class="pointer-events-auto rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white shadow-lg" x-text="notice"></div>
    </div>

    {{-- Image too large warning --}}
    <div x-show="imageError" x-transition x-cloak class="fixed inset-x-0 top-6 z-50 flex justify-center px-4">
        <div class="flex max-w-md items-start gap-3 rounded-md bg-red-50 px-4 py-3 text-sm font-medium text-red-800 shadow-lg ring-1 ring-red-200">
            <span aria-hidden="true" class="mt-0.5">&#9888;</span>
            <span class="flex-1" x-text="imageError"></span>
            <button type="button" x-on:click="imageError = ''" class="-mr-1 text-red-500 hover:text-red-700" title="Dismiss">&times;</button>
        </div>
    </div>

    {{-- Image caption modal (replaces window.prompt) --}}
    <div x-show="imageModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" x-on:keydown.escape.window="cancelImage()">
        <div class="w-full max-w-md rounded-lg bg-white p-5 shadow-xl" x-on:click.outside="cancelImage()">
            <h3 class="text-base font-semibold text-gray-900">Add a figure caption</h3>
            <p class="mt-1 text-xs text-gray-500">Describe the image, e.g. <em>OMR answer sheet</em>. The <strong>Figure&nbsp;number</strong> is added automatically &mdash; don't type &ldquo;Figure 1&rdquo;. Leave blank for no caption.</p>
            <input
                type="text"
                x-ref="imageCaptionInput"
                x-model="imageCaption"
                x-on:keydown.enter.prevent="confirmImage()"
                placeholder="e.g. OMR answer sheet"
                class="mt-3 block w-full rounded-md px-3 py-2 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" x-on:click="cancelImage()" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50">Cancel</button>
                <button type="button" x-on:click="confirmImage()" class="rounded-md bg-indigo-600 px-4 py-1.5 text-sm font-semibold text-white hover:bg-indigo-500">Insert image</button>
            </div>
        </div>
    </div>
</div>
