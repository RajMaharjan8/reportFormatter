import { registerEditorComponent } from './editor'

document.addEventListener('alpine:init', () => {
    registerEditorComponent(window.Alpine)
})
