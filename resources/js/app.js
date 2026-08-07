import Alpine from 'alpinejs';
import EasyMDE from 'easymde';
import 'easymde/dist/easymde.min.css';

window.Alpine = Alpine;

Alpine.start();

document.querySelectorAll('textarea[data-markdown-editor]').forEach((textarea) => {
    new EasyMDE({
        element: textarea,
        spellChecker: false,
        status: false,
        toolbar: ['bold', 'italic', 'heading', '|', 'quote', 'unordered-list', 'ordered-list', 'link', '|', 'preview', 'guide'],
    });
});
