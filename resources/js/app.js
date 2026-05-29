import { registerEditorComponent } from './editor'

document.addEventListener('alpine:init', () => {
    registerEditorComponent(window.Alpine)
})

// Register the PWA service worker so the app is installable and works offline.
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {})
    })
}
