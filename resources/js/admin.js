import Editor from '@toast-ui/editor';
import '@toast-ui/editor/dist/toastui-editor.css';
import '@fortawesome/fontawesome-free/css/all.min.css';
import '@fortawesome/fontawesome-free/css/v4-shims.min.css';
import '../css/admin.css';

document.querySelectorAll('textarea[data-html-editor], textarea[name="bottom_content"]').forEach((textarea) => {
    if (textarea.dataset.editorInitialized === 'true') {
        return;
    }
    textarea.dataset.editorInitialized = 'true';

    const shell = document.createElement('div');
    shell.className = 'admin-wysiwyg';

    const header = document.createElement('div');
    header.className = 'admin-wysiwyg-header';
    header.innerHTML = `
        <div class="admin-wysiwyg-title">
            <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
            <span>Content editor</span>
        </div>
        <div class="admin-wysiwyg-tabs" role="tablist" aria-label="Editor mode">
            <button type="button" class="admin-wysiwyg-tab is-active" data-editor-mode="visual" role="tab" aria-selected="true">
                <i class="fa-solid fa-eye" aria-hidden="true"></i>
                <span>Visual</span>
            </button>
            <button type="button" class="admin-wysiwyg-tab" data-editor-mode="code" role="tab" aria-selected="false">
                <i class="fa-solid fa-code" aria-hidden="true"></i>
                <span>Code</span>
            </button>
        </div>
        <span class="admin-wysiwyg-badge">HTML</span>
    `;

    const host = document.createElement('div');
    host.className = 'admin-wysiwyg-editor';

    const codeEditor = document.createElement('textarea');
    codeEditor.className = 'admin-wysiwyg-code';
    codeEditor.hidden = true;
    codeEditor.setAttribute('aria-label', 'HTML source');
    codeEditor.spellcheck = false;

    const footer = document.createElement('div');
    footer.className = 'admin-wysiwyg-footer';
    footer.innerHTML = `
        <span>Use Visual for rich text or Code to edit the HTML source.</span>
        <span class="admin-wysiwyg-count" aria-live="polite"></span>
    `;

    textarea.after(shell);
    shell.append(header, host, codeEditor, footer);
    textarea.hidden = true;

    const editor = new Editor({
        el: host,
        initialValue: '',
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

    if (textarea.value.trim() !== '') {
        editor.setHTML(textarea.value);
    }

    const count = footer.querySelector('.admin-wysiwyg-count');
    const updateCount = (html) => {
        const documentFragment = document.createElement('div');
        documentFragment.innerHTML = html;
        const text = documentFragment.textContent.trim();
        const words = text ? text.split(/\s+/).length : 0;
        count.textContent = `${words.toLocaleString()} ${words === 1 ? 'word' : 'words'}`;
    };
    const syncVisualEditor = () => {
        textarea.value = editor.getHTML();
        updateCount(textarea.value);
    };
    const syncCodeEditor = () => {
        textarea.value = codeEditor.value;
        updateCount(textarea.value);
    };
    const syncActiveEditor = () => codeEditor.hidden ? syncVisualEditor() : syncCodeEditor();

    editor.on('change', syncVisualEditor);
    codeEditor.addEventListener('input', syncCodeEditor);
    textarea.form?.addEventListener('submit', syncActiveEditor);

    let activeMode = 'visual';
    header.querySelectorAll('[data-editor-mode]').forEach((tab) => {
        tab.addEventListener('click', () => {
            const mode = tab.dataset.editorMode;
            if (mode === activeMode) {
                return;
            }

            if (mode === 'code') {
                codeEditor.value = editor.getHTML();
                host.hidden = true;
                codeEditor.hidden = false;
                codeEditor.focus();
                syncCodeEditor();
            } else {
                editor.setHTML(codeEditor.value);
                codeEditor.hidden = true;
                host.hidden = false;
                editor.focus();
                syncVisualEditor();
            }
            activeMode = mode;

            header.querySelectorAll('[data-editor-mode]').forEach((candidate) => {
                const active = candidate === tab;
                candidate.classList.toggle('is-active', active);
                candidate.setAttribute('aria-selected', active ? 'true' : 'false');
            });
        });
    });

    syncVisualEditor();
});
