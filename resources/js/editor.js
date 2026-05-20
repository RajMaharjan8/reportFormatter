/**
 * A small, dependency-free rich-text editor for report sections.
 *
 * It is a plain `contenteditable` region driven by the browser's built-in
 * editing commands — no external library, no CDN, nothing to fail to load.
 * Registered as the Alpine `editor` component.
 */
function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
}

function clampInt(raw, fallback, min, max) {
    const value = parseInt(raw, 10)

    if (Number.isNaN(value)) {
        return fallback
    }

    return Math.min(max, Math.max(min, value))
}

export function registerEditorComponent(Alpine) {
    Alpine.data('editor', (config = {}) => ({
        dirty: false,
        savedRange: null,
        selectedImage: null,
        references: Array.isArray(config.references) ? config.references : [],
        citationFormat: config.citationFormat || 'london_met',
        citePickerOpen: false,

        // Called once from the blade via x-init.
        mountEditor() {
            const el = this.$refs.content

            if (!el) {
                return
            }

            const html = (config.initialContent || '').trim()
            el.innerHTML = html !== '' ? html : '<p><br></p>'

            this.refreshCitations()
            this.decorateReferencesPlaceholders()

            // Make Enter produce <p> blocks (consistent across browsers).
            try {
                document.execCommand('defaultParagraphSeparator', false, 'p')
            } catch (error) {
                /* not supported everywhere — harmless */
            }

            el.addEventListener('input', () => {
                this.dirty = true
            })

            el.addEventListener('click', (event) => {
                const remove = event.target.closest && event.target.closest('.references-list-remove')

                if (remove) {
                    const placeholder = remove.closest('[data-references-list]')

                    if (placeholder) {
                        placeholder.remove()
                        this.dirty = true
                    }

                    event.preventDefault()

                    return
                }

                this.selectImage(event)
            })
        },

        /**
         * Add a × remove button to every references-list placeholder. The
         * button is editor-only chrome — it's stripped back out before save.
         */
        decorateReferencesPlaceholders() {
            if (!this.$refs.content) {
                return
            }

            this.$refs.content.querySelectorAll('[data-references-list]').forEach((el) => {
                if (el.querySelector('.references-list-remove')) {
                    return
                }

                const button = document.createElement('button')
                button.type = 'button'
                button.className = 'references-list-remove'
                button.setAttribute('contenteditable', 'false')
                button.setAttribute('aria-label', 'Remove references list')
                button.title = 'Remove references list'
                button.textContent = '×'
                el.appendChild(button)
            })
        },

        /** Mark the clicked image as selected (so the resize buttons act on it). */
        selectImage(event) {
            this.$refs.content
                .querySelectorAll('img.is-selected')
                .forEach((img) => img.classList.remove('is-selected'))

            const img = event.target && event.target.tagName === 'IMG' ? event.target : null
            this.selectedImage = img

            if (img) {
                img.classList.add('is-selected')
            }
        },

        /** Resize the selected image by a percentage step (keeps proportions). */
        resizeImage(deltaPercent) {
            const img = this.selectedImage

            if (!img) {
                window.alert('Click an image first, then use the resize buttons.')

                return
            }

            const current = parseFloat(img.style.width) || 100
            const next = Math.min(100, Math.max(15, Math.round(current + deltaPercent)))

            img.style.width = `${next}%`
            img.style.height = 'auto'
            this.dirty = true
        },

        /** Turn the block at the cursor into a paragraph or heading. */
        setBlock(tag) {
            this.$refs.content.focus()
            document.execCommand('formatBlock', false, tag)
            this.dirty = true
        },

        /** Run an inline command: bold, italic, underline, lists, alignment. */
        run(command) {
            this.$refs.content.focus()
            document.execCommand(command, false, null)
            this.dirty = true
        },

        /** Remember where the cursor is before a dialog steals focus. */
        saveSelection() {
            const selection = window.getSelection()
            this.savedRange = selection && selection.rangeCount
                ? selection.getRangeAt(0)
                : null
        },

        /** Put the cursor back and insert HTML there. */
        insertAtCursor(html) {
            const el = this.$refs.content
            el.focus()

            if (this.savedRange) {
                const selection = window.getSelection()
                selection.removeAllRanges()
                selection.addRange(this.savedRange)
            }

            document.execCommand('insertHTML', false, html)
            this.dirty = true
        },

        /** Read the chosen image file and insert it as a captioned figure. */
        insertImage(event) {
            const file = event.target.files && event.target.files[0]
            event.target.value = ''

            if (!file) {
                return
            }

            const reader = new FileReader()

            reader.onload = () => {
                const caption = (window.prompt('Figure caption (shown in the Table of Figures):', '') || '').trim()
                const safe = escapeHtml(caption || 'Figure')

                this.insertAtCursor(
                    `<figure class="image" style="text-align:center">`
                    + `<img src="${reader.result}" alt="${safe}" style="display:block;margin:0 auto;max-width:100%;height:auto">`
                    + `<figcaption>${safe}</figcaption></figure><p><br></p>`,
                )
            }

            reader.readAsDataURL(file)
        },

        /** Insert an editable table with a name that feeds the Table of Tables. */
        insertTable() {
            this.saveSelection()

            const name = (window.prompt('Table name (shown in the Table of Tables):', '') || '').trim()
            const rows = clampInt(window.prompt('Number of rows:', '3'), 3, 1, 50)
            const cols = clampInt(window.prompt('Number of columns:', '3'), 3, 1, 12)
            const width = (100 / cols).toFixed(2)

            let html = `<table><caption>${escapeHtml(name || 'Table')}</caption><colgroup>`

            for (let c = 0; c < cols; c++) {
                html += `<col style="width:${width}%">`
            }

            html += '</colgroup><tbody>'

            for (let r = 0; r < rows; r++) {
                html += '<tr>'
                for (let c = 0; c < cols; c++) {
                    html += r === 0 ? '<th>Heading</th>' : '<td>&nbsp;</td>'
                }
                html += '</tr>'
            }

            html += '</tbody></table><p><br></p>'

            this.insertAtCursor(html)
        },

        // ---- Table editing -------------------------------------------------

        /** The table cell containing the cursor, or null. */
        currentCell() {
            const selection = window.getSelection()

            if (!selection || !selection.anchorNode) {
                return null
            }

            let node = selection.anchorNode

            if (node.nodeType === Node.TEXT_NODE) {
                node = node.parentElement
            }

            const cell = node && node.closest ? node.closest('td, th') : null

            return cell && this.$refs.content.contains(cell) ? cell : null
        },

        requireCell() {
            const cell = this.currentCell()

            if (!cell) {
                window.alert('Click inside a table cell first.')
            }

            return cell
        },

        /** Ensure the table has a <colgroup> with one <col> per column. */
        ensureColgroup(table) {
            const columns = table.rows[0] ? table.rows[0].cells.length : 0

            let colgroup = table.querySelector(':scope > colgroup')

            if (!colgroup) {
                colgroup = document.createElement('colgroup')
                const caption = table.querySelector(':scope > caption')

                if (caption) {
                    caption.insertAdjacentElement('afterend', colgroup)
                } else {
                    table.insertBefore(colgroup, table.firstChild)
                }
            }

            if (colgroup.children.length !== columns) {
                const width = (100 / columns).toFixed(2)
                colgroup.innerHTML = ''

                for (let i = 0; i < columns; i++) {
                    const col = document.createElement('col')
                    col.style.width = `${width}%`
                    colgroup.appendChild(col)
                }
            }

            return colgroup
        },

        /** Add an empty row below the current one. */
        addRow() {
            const cell = this.requireCell()
            if (!cell) {
                return
            }

            const row = cell.parentElement
            const table = cell.closest('table')
            const newRow = table.insertRow(row.rowIndex + 1)

            for (let i = 0; i < row.cells.length; i++) {
                newRow.insertCell(i).innerHTML = '&nbsp;'
            }

            this.dirty = true
        },

        /** Add a column to the right of the current one. */
        addColumn() {
            const cell = this.requireCell()
            if (!cell) {
                return
            }

            const table = cell.closest('table')
            const index = cell.cellIndex

            Array.from(table.rows).forEach((row) => {
                const reference = row.cells[index]
                const isHeader = reference && reference.tagName === 'TH'
                const created = document.createElement(isHeader ? 'th' : 'td')
                created.innerHTML = isHeader ? 'Heading' : '&nbsp;'

                if (reference) {
                    reference.insertAdjacentElement('afterend', created)
                } else {
                    row.appendChild(created)
                }
            })

            this.ensureColgroup(table)
            this.dirty = true
        },

        /** Delete the current row. */
        deleteRow() {
            const cell = this.requireCell()
            if (!cell) {
                return
            }

            const table = cell.closest('table')

            if (table.rows.length > 1) {
                table.deleteRow(cell.parentElement.rowIndex)
                this.dirty = true
            }
        },

        /** Delete the current column. */
        deleteColumn() {
            const cell = this.requireCell()
            if (!cell) {
                return
            }

            const table = cell.closest('table')
            const index = cell.cellIndex

            if (table.rows[0].cells.length <= 1) {
                return
            }

            Array.from(table.rows).forEach((row) => {
                if (row.cells[index]) {
                    row.deleteCell(index)
                }
            })

            this.ensureColgroup(table)
            this.dirty = true
        },

        /** Widen (delta > 0) or narrow the current column, balancing a neighbour. */
        resizeColumn(delta) {
            const cell = this.requireCell()
            if (!cell) {
                return
            }

            const table = cell.closest('table')
            const cols = this.ensureColgroup(table).children
            const i = cell.cellIndex
            const j = i < cols.length - 1 ? i + 1 : i - 1

            if (j < 0) {
                return
            }

            const fallback = 100 / cols.length
            const wi = parseFloat(cols[i].style.width) || fallback
            const wj = parseFloat(cols[j].style.width) || fallback

            let step = delta
            if (wi + step < 8) {
                step = 8 - wi
            }
            if (wj - step < 8) {
                step = wj - 8
            }

            cols[i].style.width = `${(wi + step).toFixed(2)}%`
            cols[j].style.width = `${(wj - step).toFixed(2)}%`
            this.dirty = true
        },

        // ---- Citations ------------------------------------------------------

        /** Toggle the picker that lists this report's references. */
        openCitePicker() {
            this.saveSelection()
            this.citePickerOpen = true
        },

        closeCitePicker() {
            this.citePickerOpen = false
        },

        /**
         * Insert a non-editable citation span at the cursor.
         *
         * Uses the Range API directly (not document.execCommand) so the inline
         * span lands at the saved cursor position even when the picker has
         * stolen focus, and never gets pushed into a new block by quirks in
         * how some browsers treat `contenteditable="false"` with insertHTML.
         */
        insertCitation(referenceId) {
            const reference = this.references.find((r) => r.id === referenceId)

            if (!reference) {
                return
            }

            const inline = (reference.inline && reference.inline[this.citationFormat]) || '[?]'

            const el = this.$refs.content
            el.focus()

            const selection = window.getSelection()
            let range = null

            if (this.savedRange && el.contains(this.savedRange.startContainer)) {
                range = this.savedRange
                selection.removeAllRanges()
                selection.addRange(range)
            } else if (selection.rangeCount && el.contains(selection.getRangeAt(0).startContainer)) {
                range = selection.getRangeAt(0)
            } else {
                // No usable cursor — drop the citation at the end of the last block.
                const lastBlock = el.querySelector(':scope > :last-child') || el
                range = document.createRange()
                range.selectNodeContents(lastBlock)
                range.collapse(false)
                selection.removeAllRanges()
                selection.addRange(range)
            }

            range.deleteContents()

            const span = document.createElement('span')
            span.className = 'ref-cite'
            span.setAttribute('data-ref-id', String(reference.id))
            span.setAttribute('contenteditable', 'false')
            span.textContent = inline

            const trailingSpace = document.createTextNode(' ')

            // Insert in reverse so they end up in order: span, then space.
            range.insertNode(trailingSpace)
            range.insertNode(span)

            // Place the cursor after the trailing space so typing continues inline.
            range.setStartAfter(trailingSpace)
            range.collapse(true)
            selection.removeAllRanges()
            selection.addRange(range)

            this.savedRange = range
            this.dirty = true
            this.closeCitePicker()
        },

        /** Insert the placeholder that becomes the references list on render. */
        insertReferencesList() {
            this.$refs.content.focus()
            const html =
                '<div class="references-list-placeholder" data-references-list contenteditable="false">'
                + 'References list (auto-generated — shows the references you actually cite)'
                + '</div><p><br></p>'

            document.execCommand('insertHTML', false, html)
            this.decorateReferencesPlaceholders()
            this.dirty = true
        },

        /**
         * Re-render every `.ref-cite` span using the current references map
         * and format. Runs on mount and whenever the manager dispatches a
         * `references-updated` event.
         */
        refreshCitations() {
            if (!this.$refs.content) {
                return
            }

            const lookup = new Map(this.references.map((r) => [String(r.id), r]))

            this.$refs.content.querySelectorAll('span.ref-cite').forEach((span) => {
                span.setAttribute('contenteditable', 'false')

                const id = span.getAttribute('data-ref-id')
                const reference = lookup.get(String(id))

                if (!reference) {
                    span.textContent = '[?]'
                    return
                }

                const inline = (reference.inline && reference.inline[this.citationFormat]) || '[?]'
                span.textContent = inline
            })
        },

        /**
         * Handle the Livewire event broadcast by the references manager — the
         * payload is `{ references, format }`. Updates local state and
         * rewrites all inline citations in place.
         */
        onReferencesUpdated(detail) {
            if (!detail) {
                return
            }

            if (Array.isArray(detail.references)) {
                this.references = detail.references
            }

            if (typeof detail.format === 'string' && detail.format !== '') {
                this.citationFormat = detail.format
            }

            this.refreshCitations()
        },

        getHTML() {
            if (!this.$refs.content) {
                return ''
            }

            // Editor-only chrome (selection outlines, remove buttons) is
            // stripped on a clone so the live DOM stays interactive while
            // the user waits for the save round-trip.
            const clone = this.$refs.content.cloneNode(true)

            clone.querySelectorAll('img.is-selected').forEach((img) => img.classList.remove('is-selected'))
            clone.querySelectorAll('.references-list-remove').forEach((button) => button.remove())

            return clone.innerHTML
        },

        /** Persist the section through the given Livewire component. */
        async saveTo(wire, method = 'save') {
            await wire.call(method, this.getHTML())
            this.dirty = false
        },
    }))
}
