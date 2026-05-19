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

        // Called once from the blade via x-init.
        mountEditor() {
            const el = this.$refs.content

            if (!el) {
                return
            }

            const html = (config.initialContent || '').trim()
            el.innerHTML = html !== '' ? html : '<p><br></p>'

            // Make Enter produce <p> blocks (consistent across browsers).
            try {
                document.execCommand('defaultParagraphSeparator', false, 'p')
            } catch (error) {
                /* not supported everywhere — harmless */
            }

            el.addEventListener('input', () => {
                this.dirty = true
            })

            // Click an image to select it for resizing.
            el.addEventListener('click', (event) => this.selectImage(event))
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

        getHTML() {
            if (!this.$refs.content) {
                return ''
            }

            // The selection outline is editor-only — never save it.
            this.$refs.content
                .querySelectorAll('img.is-selected')
                .forEach((img) => img.classList.remove('is-selected'))

            return this.$refs.content.innerHTML
        },

        /** Persist the section through the given Livewire component. */
        async saveTo(wire, method = 'save') {
            await wire.call(method, this.getHTML())
            this.dirty = false
        },
    }))
}
