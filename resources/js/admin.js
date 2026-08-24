import Editor from '@toast-ui/editor';
import '@toast-ui/editor/dist/toastui-editor.css';
import '@fortawesome/fontawesome-free/css/all.min.css';
import '@fortawesome/fontawesome-free/css/v4-shims.min.css';
import '../css/admin.css';

document.querySelectorAll('textarea[data-markdown-editor]').forEach((textarea) => {
    const host = document.createElement('div');
    textarea.after(host);
    textarea.hidden = true;
    const editor = new Editor({
        el: host,
        initialValue: textarea.value,
        initialEditType: 'wysiwyg',
        previewStyle: 'vertical',
        height: `${Math.max(300, Number(textarea.rows || 8) * 30)}px`,
        usageStatistics: false,
        toolbarItems: [['heading', 'bold', 'italic', 'strike'], ['hr', 'quote'], ['ul', 'ol'], ['link'], ['scrollSync']],
    });
    textarea.form?.addEventListener('submit', () => { textarea.value = editor.getMarkdown(); });
});
