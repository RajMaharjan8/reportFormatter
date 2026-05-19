import { Previewer } from 'pagedjs'

/**
 * Paginates the report output into A4 pages with Paged.js, then numbers the
 * pages directly: front matter in lower-roman (ii, iii, iv …) and the body
 * restarting at 1 from the first section. Paged.js cannot reliably restart its
 * own `page` counter mid-document, so the numbering is applied here instead.
 */
function toRoman(value) {
    const numerals = [[10, 'x'], [9, 'ix'], [5, 'v'], [4, 'iv'], [1, 'i']]
    let remaining = value
    let result = ''

    for (const [amount, symbol] of numerals) {
        while (remaining >= amount) {
            result += symbol
            remaining -= amount
        }
    }

    return result
}

function numberPages(container, align) {
    const pages = container.querySelectorAll('.pagedjs_page')

    let romanNumber = 1 // the cover counts as page i (never shown)
    let bodyNumber = 0

    pages.forEach((page) => {
        // The cover carries no page number.
        if (page.querySelector('.report-cover')) {
            return
        }

        const isBody = page.querySelector('.report-bodymatter, .report-section') !== null

        let label
        if (isBody) {
            bodyNumber += 1
            label = String(bodyNumber)
        } else {
            romanNumber += 1
            label = toRoman(romanNumber)
        }

        const box = page.querySelector('.pagedjs_pagebox') || page
        box.style.position = 'relative'

        const number = document.createElement('div')
        number.className = 'report-page-number'
        number.textContent = label
        number.style.cssText =
            'position:absolute;bottom:11mm;left:0;right:0;padding:0 22mm;'
            + `text-align:${align};font-family:"Times New Roman",Times,serif;`
            + 'font-size:11pt;color:#111;'
        box.appendChild(number)
    })
}

document.addEventListener('DOMContentLoaded', async () => {
    const source = document.getElementById('report-source')
    const target = document.getElementById('report-render')

    if (!source || !target) {
        return
    }

    document.body.classList.add('is-paginating')

    try {
        const previewer = new Previewer()
        await previewer.preview(source.innerHTML, window.reportStylesheets || [], target)
        numberPages(target, window.reportPageAlign || 'right')
    } catch (error) {
        console.error('Paged.js failed to render the report:', error)
        target.innerHTML =
            '<div style="max-width:640px;margin:60px auto;padding:24px;border:1px solid #fca5a5;'
            + 'background:#fef2f2;color:#991b1b;border-radius:8px;font-family:system-ui,sans-serif;font-size:14px">'
            + '<strong>The report could not be paginated.</strong>'
            + '<p style="margin:8px 0 0">' + (error && error.message ? error.message : String(error)) + '</p>'
            + '</div>'
    } finally {
        source.remove()
        document.body.classList.remove('is-paginating')
        document.body.classList.add('is-paginated')
    }
})
