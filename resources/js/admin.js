import EasyMDE from 'easymde';
import 'easymde/dist/easymde.min.css';
import '@fortawesome/fontawesome-free/css/all.min.css';
import '@fortawesome/fontawesome-free/css/v4-shims.min.css';

document.querySelectorAll('textarea[data-markdown-editor]').forEach((textarea) => {
    new EasyMDE({
        element: textarea,
        spellChecker: false,
        status: false,
        autoDownloadFontAwesome: false,
        toolbar: ['bold', 'italic', 'heading', '|', 'quote', 'unordered-list', 'ordered-list', 'link', '|', 'preview', 'guide'],
    });
});
