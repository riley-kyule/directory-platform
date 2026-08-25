import Editor from '@toast-ui/editor';
import '@toast-ui/editor/dist/toastui-editor.css';
import '@fortawesome/fontawesome-free/css/all.min.css';
import '@fortawesome/fontawesome-free/css/v4-shims.min.css';
import '../css/admin.css';

document.querySelectorAll('textarea[data-markdown-editor]').forEach((textarea) => {
    const shell = document.createElement('div');
    shell.className = 'admin-wysiwyg';

    const header = document.createElement('div');
    header.className = 'admin-wysiwyg-header';
    header.innerHTML = `
        <div class="admin-wysiwyg-title">
            <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
            <span>Visual editor</span>
        </div>
        <span class="admin-wysiwyg-badge">Rich text</span>
    `;

    const host = document.createElement('div');
    host.className = 'admin-wysiwyg-editor';

    const footer = document.createElement('div');
    footer.className = 'admin-wysiwyg-footer';
    footer.innerHTML = `
        <span>Use the toolbar to format content. Changes are saved with the form.</span>
        <span class="admin-wysiwyg-count" aria-live="polite"></span>
    `;

    textarea.after(shell);
    shell.append(header, host, footer);
    textarea.hidden = true;

    const editor = new Editor({
        el: host,
        initialValue: textarea.value,
        initialEditType: 'wysiwyg',
        height: `${Math.max(360, Number(textarea.rows || 8) * 34)}px`,
        hideModeSwitch: true,
        usageStatistics: false,
        autofocus: false,
        placeholder: 'Start writing here…',
        toolbarItems: [
            ['heading', 'bold', 'italic', 'strike'],
            ['hr', 'quote'],
            ['ul', 'ol', 'task', 'indent', 'outdent'],
            ['table', 'link'],
            ['code', 'codeblock'],
        ],
    });

    const count = footer.querySelector('.admin-wysiwyg-count');
    const syncEditor = () => {
        const markdown = editor.getMarkdown();
        const words = markdown.trim() ? markdown.trim().split(/\s+/).length : 0;
        textarea.value = markdown;
        count.textContent = `${words.toLocaleString()} ${words === 1 ? 'word' : 'words'}`;
    };

    editor.on('change', syncEditor);
    textarea.form?.addEventListener('submit', syncEditor);
    syncEditor();
});
