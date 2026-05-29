<?php

use App\Models\Report;
use App\Models\Section;
use App\Support\CitationFormatter;
use Livewire\Component;

new class extends Component
{
    public Report $report;

    public ?Section $activeSection = null;

    public string $newSectionTitle = '';

    public string $newFrontPageTitle = '';

    public string $editTitle = '';

    public function mount(Report $report): void
    {
        $this->authorize('update', $report);

        $this->report = $report;

        $requestedId = (int) request()->query('section');

        $this->activeSection = ($requestedId
            ? $report->sections()->whereKey($requestedId)->first()
            : null) ?? $report->sections()->first();

        $this->syncEditorFromActive();
    }

    /**
     * Custom front-matter pages, shown before the contents.
     */
    public function getFrontPagesProperty()
    {
        return $this->report->sections()->where('placement', 'front')->orderBy('order')->get();
    }

    /**
     * Numbered body sections (1, 2, 3 …).
     */
    public function getBodySectionsProperty()
    {
        return $this->report->sections()->where('placement', 'body')->orderBy('order')->get();
    }

    /**
     * The 1-based position of the active body section, used as its heading
     * number. Front-matter pages are unnumbered and return 0.
     */
    public function getActiveSectionNumberProperty(): int
    {
        if (! $this->activeSection || $this->activeSection->isFrontPage()) {
            return 0;
        }

        return (int) $this->report->sections()
            ->where('placement', 'body')
            ->orderBy('order')
            ->pluck('id')
            ->search($this->activeSection->id) + 1;
    }

    public function addSection(): void
    {
        if ($this->createPage($this->newSectionTitle, 'body')) {
            $this->newSectionTitle = '';
        }
    }

    public function addFrontPage(): void
    {
        if ($this->createPage($this->newFrontPageTitle, 'front')) {
            $this->newFrontPageTitle = '';
        }
    }

    /**
     * Create a body section or front-matter page, then open it in the editor.
     */
    protected function createPage(string $title, string $placement): bool
    {
        $title = trim($title);

        if ($title === '') {
            return false;
        }

        $order = ($this->report->sections()->max('order') ?? -1) + 1;

        $section = $this->report->sections()->create([
            'placement' => $placement,
            'title' => $title,
            'order' => $order,
            'content' => null,
        ]);

        $this->selectSection($section->id);

        return true;
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

        $moved = $this->report->sections()->whereKey($itemId)->first();

        if (! $moved) {
            return;
        }

        $front = $this->report->sections()->where('placement', 'front')->orderBy('order')->pluck('id')->all();
        $body = $this->report->sections()->where('placement', 'body')->orderBy('order')->pluck('id')->all();

        $isFront = $moved->isFrontPage();
        $ids = $isFront ? $front : $body;
        $ids = array_values(array_filter($ids, fn ($id) => (int) $id !== $itemId));

        array_splice($ids, $position, 0, [$itemId]);

        if ($isFront) {
            $front = $ids;
        } else {
            $body = $ids;
        }

        foreach (array_merge($front, $body) as $order => $id) {
            Section::whereKey($id)
                ->where('report_id', $this->report->id)
                ->update(['order' => $order]);
        }
    }

    /**
     * Build a JSON-friendly map of every reference belonging to this report,
     * with the inline citation pre-rendered in each supported format. Handed
     * to the editor so the citation picker can render the correct inline text
     * without a server round-trip.
     *
     * @return list<array{id: int, type: string, label: string, inline: array<string, string>}>
     */
    public function getReferencesPayloadProperty(): array
    {
        $references = $this->report->references()->get();
        $payload = [];

        foreach (CitationFormatter::FORMATS as $format) {
            $formatter = new CitationFormatter($format, $references);

            foreach ($references as $reference) {
                $payload[$reference->id]['id'] = (int) $reference->id;
                $payload[$reference->id]['type'] = $reference->type;
                $payload[$reference->id]['label'] = $this->referenceLabel($reference);
                $payload[$reference->id]['inline'][$format] = $formatter->inline($reference);
            }
        }

        return array_values($payload);
    }

    protected function referenceLabel(\App\Models\Reference $reference): string
    {
        $authors = trim((string) $reference->field('authors', ''));
        $year = trim((string) $reference->field('year', ''));
        $title = trim((string) $reference->field('title', $reference->field('site_name', '')));

        $head = trim(($authors !== '' ? $authors : '').($year !== '' ? " ({$year})" : ''));

        return $head === '' ? ($title !== '' ? $title : 'Untitled reference') : "{$head} — {$title}";
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
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <div class="min-w-0">
                <a href="{{ route('reports.cover', ['report' => $report]) }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-500">&larr; Back to cover</a>
                <h1 class="truncate text-sm font-semibold text-gray-900">{{ $report->module_code }} &middot; {{ $report->module_title }}</h1>
            </div>
            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <livewire:manage-references :report="$report" />
                <a href="{{ route('reports.output', ['report' => $report]) }}" class="rounded-md bg-gray-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-gray-700">
                    View full report
                </a>
            </div>
        </div>
    </header>

    <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-6 sm:px-6 lg:flex-row">
        <aside class="w-full space-y-4 lg:w-64 lg:shrink-0">
            {{-- Front-matter pages — shown after the cover, before the contents --}}
            <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Front pages</h2>
                <p class="mt-1 text-[11px] text-gray-400">Extra pages after the cover, before the contents. Unnumbered.</p>

                <ul wire:sort="reorder" class="mt-3 space-y-1">
                    @forelse ($this->frontPages as $section)
                        <li
                            wire:key="section-{{ $section->id }}"
                            wire:sort:item="{{ $section->id }}"
                            class="group flex items-center gap-1 rounded-md px-1.5 py-1.5 text-sm {{ $activeSection?->is($section) ? 'bg-amber-50 text-amber-800' : 'text-gray-700 hover:bg-gray-50' }}"
                        >
                            <span wire:sort:handle class="cursor-grab select-none text-gray-300 hover:text-gray-500" title="Drag to reorder">⠿</span>
                            <button type="button" wire:click="selectSection({{ $section->id }})" class="flex-1 truncate text-left">
                                {{ $section->title }}
                            </button>
                            <button type="button" wire:click="deleteSection({{ $section->id }})" wire:confirm="Delete this page?" class="opacity-100 lg:opacity-0 lg:group-hover:opacity-100 text-xs text-red-600 hover:text-red-800">
                                &times;
                            </button>
                        </li>
                    @empty
                        <li class="px-2 py-1.5 text-xs text-gray-500">No front pages yet</li>
                    @endforelse
                </ul>

                <form wire:submit="addFrontPage" class="mt-4 border-t border-gray-200 pt-3">
                    <label for="newFrontPageTitle" class="block text-xs font-medium text-gray-700">Add front page</label>
                    <div class="mt-1 flex gap-1">
                        <input type="text" id="newFrontPageTitle" wire:model="newFrontPageTitle" placeholder="e.g. Acknowledgements" class="block w-full rounded-md px-2 py-1.5 text-sm ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-amber-500">
                        <button type="submit" class="rounded-md bg-amber-600 px-2.5 py-1.5 text-sm font-semibold text-white hover:bg-amber-500">+</button>
                    </div>
                </form>
            </div>

            {{-- Numbered body sections --}}
            <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Sections</h2>
                <p class="mt-1 text-[11px] text-gray-400">Drag the handle to reorder.</p>

                <ul wire:sort="reorder" class="mt-3 space-y-1">
                    @forelse ($this->bodySections as $section)
                        <li
                            wire:key="section-{{ $section->id }}"
                            wire:sort:item="{{ $section->id }}"
                            class="group flex items-center gap-1 rounded-md px-1.5 py-1.5 text-sm {{ $activeSection?->is($section) ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"
                        >
                            <span wire:sort:handle class="cursor-grab select-none text-gray-300 hover:text-gray-500" title="Drag to reorder">⠿</span>
                            <button type="button" wire:click="selectSection({{ $section->id }})" class="flex-1 truncate text-left">
                                {{ $section->title }}
                            </button>
                            <button type="button" wire:click="deleteSection({{ $section->id }})" wire:confirm="Delete this section?" class="opacity-100 lg:opacity-0 lg:group-hover:opacity-100 text-xs text-red-600 hover:text-red-800">
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
                    x-data="editor({
                        initialContent: @js(\App\Support\SectionContent::toHtml($activeSection->content)),
                        references: @js($this->referencesPayload),
                        citationFormat: @js($report->citationFormat()),
                    })"
                    x-init="$nextTick(() => mountEditor())"
                    @references-updated.window="onReferencesUpdated($event.detail)"
                    style="counter-reset: section {{ $this->activeSectionNumber }}"
                    class="rounded-lg bg-white shadow-sm ring-1 ring-gray-200"
                >
                    {{-- Title + save --}}
                    <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 px-4 py-2">
                        @if ($activeSection->isFrontPage())
                            <span class="inline-flex h-7 shrink-0 items-center rounded-md bg-amber-50 px-2 text-xs font-semibold text-amber-800" title="Front-matter page — shown before the contents">
                                Front page
                            </span>
                        @else
                            <span class="inline-flex h-7 shrink-0 items-center rounded-md bg-indigo-50 px-2 text-sm font-semibold text-indigo-700" title="Section number">
                                {{ $this->activeSectionNumber }}
                            </span>
                        @endif
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
                        <button type="button" x-on:mousedown.prevent x-on:click="openCitePicker()" class="toolbar-btn font-medium text-purple-700" title="Insert a citation (ref here) — pick which reference to use">Cite</button>
                        <button type="button" x-on:mousedown.prevent x-on:click="insertReferencesList()" class="toolbar-btn font-medium text-purple-700" title="Insert the auto-generated references list — lists only the references used in this report">References list</button>
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
                    <div x-show="citePickerOpen" x-cloak x-on:click.outside="closeCitePicker()" class="border-b border-purple-100 bg-purple-50 px-4 py-3">
                        <div class="flex items-center justify-between">
                            <p class="text-xs font-semibold uppercase tracking-wide text-purple-700">Insert citation</p>
                            <button type="button" x-on:click="closeCitePicker()" class="text-xs text-purple-700 hover:text-purple-900">Close</button>
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
                                        class="flex w-full items-center justify-between rounded-md bg-white px-3 py-1.5 text-left text-xs ring-1 ring-purple-200 hover:bg-purple-100"
                                    >
                                        <span x-text="reference.label" class="truncate pr-3"></span>
                                        <span class="shrink-0 font-mono text-[11px] text-purple-700" x-text="reference.inline[citationFormat] || ''"></span>
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </div>

                    {{-- Editable area --}}
                    <div wire:ignore>
                        <div x-ref="content" contenteditable="true" spellcheck="true" class="se-content {{ $activeSection->isFrontPage() ? 'se-front-page' : '' }}"></div>
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
