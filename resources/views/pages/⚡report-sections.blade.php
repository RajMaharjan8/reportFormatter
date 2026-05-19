<?php

use App\Models\Report;
use App\Models\Section;
use Livewire\Component;

new class extends Component
{
    public Report $report;

    public ?Section $activeSection = null;

    public string $newSectionTitle = '';

    public string $editTitle = '';

    public function mount(Report $report): void
    {
        $this->report = $report;

        $requestedId = (int) request()->query('section');

        $this->activeSection = ($requestedId
            ? $report->sections()->whereKey($requestedId)->first()
            : null) ?? $report->sections()->first();

        $this->syncEditorFromActive();
    }

    public function getSectionsProperty()
    {
        return $this->report->sections()->get();
    }

    /**
     * The 1-based position of the active section, used as the top heading number.
     */
    public function getActiveSectionNumberProperty(): int
    {
        if (! $this->activeSection) {
            return 0;
        }

        return (int) $this->report->sections()->pluck('id')->search($this->activeSection->id) + 1;
    }

    public function addSection(): void
    {
        $title = trim($this->newSectionTitle);

        if ($title === '') {
            return;
        }

        $order = ($this->report->sections()->max('order') ?? -1) + 1;

        $section = $this->report->sections()->create([
            'title' => $title,
            'order' => $order,
            'content' => null,
        ]);

        $this->newSectionTitle = '';
        $this->selectSection($section->id);
    }

    public function selectSection(int $sectionId): void
    {
        $section = $this->report->sections()->whereKey($sectionId)->first();

        if (! $section) {
            return;
        }

        $this->activeSection = $section;
        $this->syncEditorFromActive();
    }

    public function deleteSection(int $sectionId): void
    {
        $section = $this->report->sections()->whereKey($sectionId)->first();

        if (! $section) {
            return;
        }

        $wasActive = $this->activeSection && $this->activeSection->is($section);

        $section->delete();

        if ($wasActive) {
            $this->activeSection = $this->report->sections()->first();
            $this->syncEditorFromActive();
        }
    }

    /**
     * Move a section to a new position after a drag-and-drop sort.
     *
     * Livewire's wire:sort calls this with the dragged item's key and its
     * new index; every section's `order` is then rewritten 0..n.
     */
    public function reorder(mixed $item, int $position = 0): void
    {
        $itemId = (int) $item;

        $ids = $this->report->sections()->orderBy('order')->pluck('id')->all();
        $ids = array_values(array_filter($ids, fn ($id) => (int) $id !== $itemId));

        array_splice($ids, $position, 0, [$itemId]);

        foreach ($ids as $order => $id) {
            Section::whereKey($id)
                ->where('report_id', $this->report->id)
                ->update(['order' => $order]);
        }
    }

    public function save(?string $content = null)
    {
        if (! $this->activeSection) {
            return null;
        }

        $title = trim($this->editTitle);

        $this->activeSection->update([
            'title' => $title === '' ? $this->activeSection->title : $title,
            'content' => $content,
        ]);

        // Reload the page so the saved title and content are shown back to the user.
        return $this->redirectRoute('reports.sections', [
            'report' => $this->report,
            'section' => $this->activeSection->id,
        ], navigate: true);
    }

    protected function syncEditorFromActive(): void
    {
        $this->editTitle = $this->activeSection?->title ?? '';
    }
}; ?>

<div class="min-h-screen bg-gray-100">
    <header class="border-b border-gray-200 bg-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
            <div class="min-w-0">
                <a href="{{ route('reports.cover', ['report' => $report]) }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-500">&larr; Back to cover</a>
                <h1 class="truncate text-sm font-semibold text-gray-900">{{ $report->module_code }} &middot; {{ $report->module_title }}</h1>
            </div>
            <a href="{{ route('reports.output', ['report' => $report]) }}" class="shrink-0 rounded-md bg-gray-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-gray-700">
                View full report
            </a>
        </div>
    </header>

    <div class="mx-auto flex max-w-7xl gap-6 px-4 py-6 sm:px-6">
        <aside class="w-64 shrink-0">
            <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Sections</h2>
                <p class="mt-1 text-[11px] text-gray-400">Drag the handle to reorder.</p>

                <ul wire:sort="reorder" class="mt-3 space-y-1">
                    @forelse ($this->sections as $section)
                        <li
                            wire:key="section-{{ $section->id }}"
                            wire:sort:item="{{ $section->id }}"
                            class="group flex items-center gap-1 rounded-md px-1.5 py-1.5 text-sm {{ $activeSection?->is($section) ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"
                        >
                            <span wire:sort:handle class="cursor-grab select-none text-gray-300 hover:text-gray-500" title="Drag to reorder">⠿</span>
                            <button type="button" wire:click="selectSection({{ $section->id }})" class="flex-1 truncate text-left">
                                {{ $section->title }}
                            </button>
                            <button type="button" wire:click="deleteSection({{ $section->id }})" wire:confirm="Delete this section?" class="opacity-0 group-hover:opacity-100 text-xs text-red-600 hover:text-red-800">
                                &times;
                            </button>
                        </li>
                    @empty
                        <li class="px-2 py-1.5 text-xs text-gray-500">No sections yet</li>
                    @endforelse
                </ul>

                <form wire:submit="addSection" class="mt-4 border-t border-gray-200 pt-3">
                    <label for="newSectionTitle" class="block text-xs font-medium text-gray-700">Add section</label>
                    <div class="mt-1 flex gap-1">
                        <input type="text" id="newSectionTitle" wire:model="newSectionTitle" placeholder="e.g. Introduction" class="block w-full rounded-md px-2 py-1.5 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <button type="submit" class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white hover:bg-indigo-500">+</button>
                    </div>
                </form>
            </div>
        </aside>

        <main class="flex-1 min-w-0">
            @if ($activeSection)
                <div
                    wire:key="editor-{{ $activeSection->id }}"
                    x-data="editor({ initialContent: @js(\App\Support\SectionContent::toHtml($activeSection->content)) })"
                    x-init="$nextTick(() => mountEditor())"
                    style="counter-reset: section {{ $this->activeSectionNumber }}"
                    class="rounded-lg bg-white shadow-sm ring-1 ring-gray-200"
                >
                    {{-- Title + save --}}
                    <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 px-4 py-2">
                        <span class="inline-flex h-7 shrink-0 items-center rounded-md bg-indigo-50 px-2 text-sm font-semibold text-indigo-700" title="Section number">
                            {{ $this->activeSectionNumber }}
                        </span>
                        <input type="text" wire:model="editTitle" placeholder="Section title" class="min-w-50 flex-1 rounded-md px-2 py-1 text-sm font-semibold ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
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
                        <button type="button" x-on:mousedown.prevent x-on:click="saveSelection(); $refs.imageInput.click()" class="toolbar-btn font-medium text-indigo-600" title="Insert an image with a figure caption">Insert image</button>
                        <button type="button" x-on:mousedown.prevent x-on:click="insertTable()" class="toolbar-btn font-medium text-indigo-600" title="Insert a table with a name">Insert table</button>
                        <input type="file" x-ref="imageInput" accept="image/*" class="hidden" x-on:change="insertImage($event)">
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

                    {{-- Editable area --}}
                    <div wire:ignore>
                        <div x-ref="content" contenteditable="true" spellcheck="true" class="se-content"></div>
                    </div>
                </div>
            @else
                <div class="rounded-lg bg-white p-10 text-center shadow-sm ring-1 ring-gray-200">
                    <p class="text-sm text-gray-600">Add your first section from the sidebar to start writing.</p>
                </div>
            @endif
        </main>
    </div>
</div>
