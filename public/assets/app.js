'use strict';

const $ = (selector) => document.querySelector(selector);
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const state = {
    ready: false,
    busy: false,
    file: null,
    uploadsRemaining: 1,
    questionsRemaining: 10,
};

const elements = {
    themeToggle: $('#themeToggle'),
    sunIcon: $('#sunIcon'),
    moonIcon: $('#moonIcon'),
    uploadForm: $('#uploadForm'),
    dropZone: $('#dropZone'),
    fileInput: $('#excelFile'),
    filePreview: $('#filePreview'),
    fileName: $('#fileName'),
    fileSize: $('#fileSize'),
    clearFile: $('#clearFile'),
    uploadButton: $('#uploadButton'),
    uploadButtonText: $('#uploadButtonText'),
    uploadSpinner: $('#uploadSpinner'),
    uploadBadge: $('#uploadBadge'),
    uploadsRemaining: $('#uploadsRemaining'),
    questionsRemaining: $('#questionsRemaining'),
    workbookMeta: $('#workbookMeta'),
    metaName: $('#metaName'),
    metaRows: $('#metaRows'),
    metaSheets: $('#metaSheets'),
    chatStatus: $('#chatStatus'),
    statusDot: $('#statusDot'),
    chatMessages: $('#chatMessages'),
    chatForm: $('#chatForm'),
    questionInput: $('#questionInput'),
    sendButton: $('#sendButton'),
    sendIcon: $('#sendIcon'),
    sendSpinner: $('#sendSpinner'),
    toast: $('#toast'),
};

function initializeTheme() {
    const saved = localStorage.getItem('tte-theme');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    applyTheme(saved ?? (prefersDark ? 'dark' : 'light'));
}

function applyTheme(theme) {
    const dark = theme === 'dark';
    document.documentElement.classList.toggle('dark', dark);
    elements.sunIcon.classList.toggle('hidden', !dark);
    elements.moonIcon.classList.toggle('hidden', dark);
    localStorage.setItem('tte-theme', theme);
}

function formatBytes(bytes) {
    if (!Number.isFinite(bytes) || bytes <= 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    return `${(bytes / 1024 ** index).toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
}

function showToast(message) {
    elements.toast.textContent = message;
    elements.toast.classList.remove('hidden');
    window.clearTimeout(showToast.timer);
    showToast.timer = window.setTimeout(() => elements.toast.classList.add('hidden'), 3500);
}

function selectFile(file) {
    if (!file) return;
    const extension = file.name.split('.').pop()?.toLowerCase();
    // if (!['xlsx', 'xls', 'csv'].includes(extension)) {
    //     showToast('Choose an .xlsx, .xls, or .csv file.');
    //     return;
    // }
if (extension !== 'csv') {
    showToast('Choose a CSV file only.');
    return;
}
    state.file = file;
    elements.fileName.textContent = file.name;
    elements.fileSize.textContent = formatBytes(file.size);
    elements.filePreview.classList.remove('hidden');
    updateControls();
}

function clearSelectedFile() {
    state.file = null;
    elements.fileInput.value = '';
    elements.filePreview.classList.add('hidden');
    updateControls();
}

function updateUsage(usage) {
    if (!usage) return;
    state.uploadsRemaining = Number(usage.uploads_remaining ?? state.uploadsRemaining);
    state.questionsRemaining = Number(usage.questions_remaining ?? state.questionsRemaining);
    elements.uploadsRemaining.textContent = String(state.uploadsRemaining);
    elements.questionsRemaining.textContent = String(state.questionsRemaining);
    updateControls();
}

function setWorkbook(upload) {
    if (!upload) return;
    state.ready = true;
    elements.uploadBadge.textContent = 'Ready';
    elements.uploadBadge.className = 'rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300';
    elements.workbookMeta.classList.remove('hidden');
    elements.metaName.textContent = upload.name;
    elements.metaRows.textContent = Number(upload.rows).toLocaleString();
    elements.metaSheets.textContent = String(upload.sheets);
    elements.chatStatus.textContent = upload.truncated ? 'Ready · large workbook safely truncated' : 'Workbook ready';
    elements.statusDot.className = 'h-2.5 w-2.5 rounded-full bg-emerald-500 shadow-[0_0_0_4px_rgba(16,185,129,0.12)]';
    clearSelectedFile();
    updateControls();
}

function clearWorkbookState() {
    state.ready = false;
    elements.uploadBadge.textContent = state.uploadsRemaining > 0 ? 'Not uploaded' : 'Limit used';
    elements.uploadBadge.className = 'rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300';
    elements.workbookMeta.classList.add('hidden');
    elements.chatStatus.textContent = state.uploadsRemaining > 0
        ? 'Upload a workbook to begin'
        : 'Upload allowance already used for this IP';
    elements.statusDot.className = 'h-2.5 w-2.5 rounded-full bg-slate-300 dark:bg-slate-600';
    updateControls();
}

function updateControls() {
    const canUpload = !state.ready && !state.busy && state.file && state.uploadsRemaining > 0;
    elements.uploadButton.disabled = !canUpload;
    elements.fileInput.disabled = state.ready || state.busy || state.uploadsRemaining <= 0;
    elements.dropZone.classList.toggle('pointer-events-none', elements.fileInput.disabled);
    elements.dropZone.classList.toggle('opacity-60', elements.fileInput.disabled);

    const canAsk = state.ready && !state.busy && state.questionsRemaining > 0;
    elements.questionInput.disabled = !canAsk;
    elements.sendButton.disabled = !canAsk || elements.questionInput.value.trim() === '';
    elements.questionInput.placeholder = state.ready
        ? (state.questionsRemaining > 0 ? 'Ask a question about your workbook…' : 'Question limit reached')
        : 'Upload a workbook before asking a question…';
}

function setBusy(type, busy) {
    state.busy = busy;
    if (type === 'upload') {
        elements.uploadSpinner.classList.toggle('hidden', !busy);
        elements.uploadButtonText.textContent = busy ? 'Reading workbook…' : 'Upload and analyse';
    }
    if (type === 'question') {
        elements.sendSpinner.classList.toggle('hidden', !busy);
        elements.sendIcon.classList.toggle('hidden', busy);
    }
    updateControls();
}

function addMessage(role, text, pending = false) {
    const row = document.createElement('div');
    row.className = role === 'user' ? 'flex justify-end gap-3' : 'flex gap-3';

    const avatar = document.createElement('div');
    avatar.className = role === 'user'
        ? 'order-2 grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-sky-100 text-xs font-semibold text-sky-700 dark:bg-sky-950 dark:text-sky-300'
        : 'grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-emerald-100 text-xs font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300';
    avatar.textContent = role === 'user' ? 'You' : 'AI';

    const bubble = document.createElement('div');
    bubble.className = role === 'user'
        ? 'max-w-2xl whitespace-pre-wrap rounded-2xl rounded-tr-md bg-sky-600 px-4 py-3 text-sm leading-6 text-white shadow-sm'
        : 'max-w-2xl whitespace-pre-wrap rounded-2xl rounded-tl-md bg-slate-100 px-4 py-3 text-sm leading-6 text-slate-700 dark:bg-slate-800 dark:text-slate-200';
    bubble.textContent = text;

    if (pending) {
        bubble.dataset.pending = 'true';
        bubble.innerHTML = '<span class="inline-flex items-center gap-1.5"><span class="h-1.5 w-1.5 animate-bounce rounded-full bg-current [animation-delay:-0.2s]"></span><span class="h-1.5 w-1.5 animate-bounce rounded-full bg-current [animation-delay:-0.1s]"></span><span class="h-1.5 w-1.5 animate-bounce rounded-full bg-current"></span></span>';
    }

    row.append(avatar, bubble);
    elements.chatMessages.appendChild(row);
    elements.chatMessages.scrollTo({ top: elements.chatMessages.scrollHeight, behavior: 'smooth' });
    return bubble;
}

async function requestJson(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            'X-CSRF-Token': csrfToken,
            ...(options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
            ...(options.headers ?? {}),
        },
        ...options,
    });

    let payload;
    try {
        payload = await response.json();
    } catch {
        throw new Error('The server returned an unreadable response.');
    }

    if (!response.ok || payload.ok === false) {
        throw new Error(payload?.error?.message ?? 'The request failed.');
    }
    return payload;
}

async function loadStatus() {
    try {
        const payload = await requestJson('status.php', { method: 'GET' });
        updateUsage(payload.usage);
        if (payload.ready && payload.upload) {
            setWorkbook(payload.upload);
        } else {
            clearWorkbookState();
        }
    } catch (error) {
        showToast(error.message);
    }
}

async function handleUpload(event) {
    event.preventDefault();
    if (!state.file || state.busy) return;

    const formData = new FormData();
    formData.append('excel_file', state.file);
    setBusy('upload', true);

    try {
        const payload = await requestJson('upload.php', { method: 'POST', body: formData });
        updateUsage(payload.usage);
        setWorkbook(payload.upload);
        addMessage('assistant', `I have loaded ${Number(payload.upload.rows).toLocaleString()} non-empty rows across ${payload.upload.sheets} sheet${payload.upload.sheets === 1 ? '' : 's'}. What would you like to know?`);
        if (payload.upload.truncated) {
            showToast('The workbook exceeded a safety limit, so only the permitted context was loaded.');
        }
        elements.questionInput.focus();
    } catch (error) {
        showToast(error.message);
    } finally {
        setBusy('upload', false);
    }
}

async function handleQuestion(event) {
    event.preventDefault();
    const question = elements.questionInput.value.trim();
    if (!question || state.busy || !state.ready) return;

    addMessage('user', question);
    elements.questionInput.value = '';
    const pendingBubble = addMessage('assistant', '', true);
    setBusy('question', true);

    try {
        const payload = await requestJson('ask.php', {
            method: 'POST',
            body: JSON.stringify({ question }),
        });
        pendingBubble.removeAttribute('data-pending');
        pendingBubble.textContent = payload.answer;
        updateUsage(payload.usage);
    } catch (error) {
        pendingBubble.textContent = `Sorry, I could not answer that question. ${error.message}`;
        showToast(error.message);
    } finally {
        setBusy('question', false);
        elements.questionInput.focus();
        elements.chatMessages.scrollTo({ top: elements.chatMessages.scrollHeight, behavior: 'smooth' });
    }
}

initializeTheme();
elements.themeToggle.addEventListener('click', () => applyTheme(document.documentElement.classList.contains('dark') ? 'light' : 'dark'));
elements.fileInput.addEventListener('change', (event) => selectFile(event.target.files?.[0]));
elements.clearFile.addEventListener('click', clearSelectedFile);
elements.uploadForm.addEventListener('submit', handleUpload);
elements.chatForm.addEventListener('submit', handleQuestion);
elements.questionInput.addEventListener('input', updateControls);
elements.questionInput.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        elements.chatForm.requestSubmit();
    }
});

['dragenter', 'dragover'].forEach((eventName) => {
    elements.dropZone.addEventListener(eventName, (event) => {
        event.preventDefault();
        if (!elements.fileInput.disabled) elements.dropZone.classList.add('border-emerald-500', 'bg-emerald-50', 'dark:bg-emerald-950/20');
    });
});
['dragleave', 'drop'].forEach((eventName) => {
    elements.dropZone.addEventListener(eventName, (event) => {
        event.preventDefault();
        elements.dropZone.classList.remove('border-emerald-500', 'bg-emerald-50', 'dark:bg-emerald-950/20');
    });
});
elements.dropZone.addEventListener('drop', (event) => {
    if (!elements.fileInput.disabled) selectFile(event.dataTransfer?.files?.[0]);
});
document.addEventListener('dragover', (event) => event.preventDefault());
document.addEventListener('drop', (event) => event.preventDefault());

updateControls();
loadStatus();

// Refresh allowance/session state so the upload control unlocks shortly
// after the one-hour window expires, without requiring a manual reload.
window.setInterval(() => {
    if (!state.busy) loadStatus();
}, 30000);
