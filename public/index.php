<?php

declare(strict_types=1);

use TalkToExcel\Security;

require dirname(__DIR__) . '/bootstrap.php';

$csrfToken = Security::csrfToken();
?>
<!doctype html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="theme-color" content="#0f172a">
    <title>Talk to your Excel (BETA)</title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body class="min-h-full bg-slate-50 text-slate-900 antialiased transition-colors duration-300 dark:bg-slate-950 dark:text-slate-100">
<div class="min-h-screen overflow-hidden">
    <div class="pointer-events-none fixed inset-0 -z-10">
        <div class="absolute left-[-12rem] top-[-10rem] h-96 w-96 rounded-full bg-emerald-300/20 blur-3xl dark:bg-emerald-500/10"></div>
        <div class="absolute bottom-[-12rem] right-[-10rem] h-[28rem] w-[28rem] rounded-full bg-sky-300/20 blur-3xl dark:bg-sky-500/10"></div>
    </div>

    <header class="border-b border-slate-200/80 bg-white/75 backdrop-blur-xl dark:border-slate-800 dark:bg-slate-950/75">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
            <div class="flex items-center gap-3">
                <div class="grid h-11 w-11 place-items-center rounded-2xl bg-emerald-500 text-white shadow-lg shadow-emerald-500/20">
                    <svg viewBox="0 0 24 24" aria-hidden="true" class="h-6 w-6 fill-none stroke-current" stroke-width="1.8">
                        <path d="M4 4h16v12H8l-4 4V4Z"></path>
                        <path d="m9 8 6 4m0-4-6 4"></path>
                    </svg>
                </div>
                <div>
                    <h1 class="text-lg font-semibold tracking-tight sm:text-xl">Talk to your Excel (BETA)</h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Private, focused answers from your workbook</p>
                </div>
            </div>

            <button id="themeToggle" type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:-translate-y-0.5 hover:text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:text-white" aria-label="Toggle theme">
                <svg id="sunIcon" viewBox="0 0 24 24" class="hidden h-5 w-5 fill-none stroke-current" stroke-width="1.8" aria-hidden="true">
                    <circle cx="12" cy="12" r="4"></circle><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"></path>
                </svg>
                <svg id="moonIcon" viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" stroke-width="1.8" aria-hidden="true">
                    <path d="M20.5 14.5A8 8 0 0 1 9.5 3.5a8.5 8.5 0 1 0 11 11Z"></path>
                </svg>
            </button>
        </div>
    </header>

    <main class="mx-auto grid max-w-7xl gap-6 px-4 py-6 sm:px-6 lg:grid-cols-[360px_minmax(0,1fr)] lg:px-8 lg:py-8">
        <aside class="space-y-5">
            <section class="rounded-3xl border border-slate-200/80 bg-white/90 p-5 shadow-xl shadow-slate-200/30 backdrop-blur dark:border-slate-800 dark:bg-slate-900/80 dark:shadow-black/10">
                <div class="mb-4 flex items-start justify-between gap-4">
                    <div>
                        <h2 class="font-semibold">Upload workbook</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">CSV, up to 5,000 rows.</p>
                    </div>
                    <span id="uploadBadge" class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">Not uploaded</span>
                </div>

                <form id="uploadForm" novalidate>
                    <label id="dropZone" for="excelFile" class="group flex min-h-48 cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50/80 p-6 text-center transition hover:border-emerald-400 hover:bg-emerald-50/70 dark:border-slate-700 dark:bg-slate-950/50 dark:hover:border-emerald-500 dark:hover:bg-emerald-950/20">
                        <span class="mb-4 grid h-14 w-14 place-items-center rounded-2xl bg-white text-emerald-600 shadow-sm ring-1 ring-slate-200 transition group-hover:-translate-y-1 dark:bg-slate-900 dark:text-emerald-400 dark:ring-slate-700">
                            <svg viewBox="0 0 24 24" class="h-7 w-7 fill-none stroke-current" stroke-width="1.7" aria-hidden="true"><path d="M12 16V4m0 0L7.5 8.5M12 4l4.5 4.5"></path><path d="M5 14v4a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-4"></path></svg>
                        </span>
                        <span class="font-medium">Drop your spreadsheet here</span>
                        <span class="mt-1 text-sm text-slate-500 dark:text-slate-400">or click to browse</span>
                        <span class="mt-3 rounded-full bg-white px-3 py-1 text-xs text-slate-500 ring-1 ring-slate-200 dark:bg-slate-900 dark:text-slate-400 dark:ring-slate-700">.csv only, .xlsx support in development</span>
                    </label>
                    <input id="excelFile" name="excel_file" type="file" class="sr-only" accept=".csv,text/csv">

                    <div id="filePreview" class="mt-4 hidden rounded-2xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-950/60">
                        <div class="flex items-center gap-3">
                            <div class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                <svg viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" stroke-width="1.8" aria-hidden="true"><path d="M7 3h7l4 4v14H7z"></path><path d="M14 3v5h5M10 13h5m-5 4h5"></path></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p id="fileName" class="truncate text-sm font-medium"></p>
                                <p id="fileSize" class="text-xs text-slate-500 dark:text-slate-400"></p>
                            </div>
                            <button id="clearFile" type="button" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-200 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200" aria-label="Remove selected file">×</button>
                        </div>
                    </div>

                    <button id="uploadButton" type="submit" disabled class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-emerald-500 px-4 py-3 font-semibold text-white shadow-lg shadow-emerald-500/20 transition hover:bg-emerald-600 disabled:cursor-not-allowed disabled:opacity-50">
                        <span id="uploadButtonText">Upload and analyse</span>
                        <span id="uploadSpinner" class="hidden h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                    </button>
                </form>
            </section>

            <section class="rounded-3xl border border-slate-200/80 bg-white/90 p-5 shadow-lg shadow-slate-200/20 dark:border-slate-800 dark:bg-slate-900/80 dark:shadow-black/10">
                <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Usage</h3>
                <div class="mt-4 grid grid-cols-2 gap-3">
                    <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-950/60">
                        <p class="text-2xl font-semibold" id="uploadsRemaining">1</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Upload remaining</p>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-950/60">
                        <p class="text-2xl font-semibold" id="questionsRemaining">10</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Questions remaining</p>
                    </div>
                </div>
                <div id="workbookMeta" class="mt-4 hidden space-y-2 rounded-2xl border border-slate-200 p-4 text-sm dark:border-slate-700">
                    <div class="flex justify-between gap-4"><span class="text-slate-500 dark:text-slate-400">Workbook</span><span id="metaName" class="max-w-48 truncate font-medium"></span></div>
                    <div class="flex justify-between"><span class="text-slate-500 dark:text-slate-400">Rows loaded</span><span id="metaRows" class="font-medium"></span></div>
                    <div class="flex justify-between"><span class="text-slate-500 dark:text-slate-400">Sheets</span><span id="metaSheets" class="font-medium"></span></div>
                </div>
            </section>
        </aside>

        <section class="flex min-h-[660px] flex-col overflow-hidden rounded-3xl border border-slate-200/80 bg-white/90 shadow-xl shadow-slate-200/30 backdrop-blur dark:border-slate-800 dark:bg-slate-900/80 dark:shadow-black/10">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800 sm:px-6">
                <div>
                    <h2 class="font-semibold">Data chat</h2>
                    <p id="chatStatus" class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Upload a workbook to begin</p>
                </div>
                <span id="statusDot" class="h-2.5 w-2.5 rounded-full bg-slate-300 dark:bg-slate-600"></span>
            </div>

            <div id="chatMessages" class="flex-1 space-y-5 overflow-y-auto p-5 sm:p-6" aria-live="polite">
                <div class="flex gap-3">
                    <div class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">AI</div>
                    <div class="max-w-2xl rounded-2xl rounded-tl-md bg-slate-100 px-4 py-3 text-sm leading-6 text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        Upload your workbook, then ask questions such as “What are total sales?”, “Show sales by city”, or “Which product performed best?”
                    </div>
                </div>
            </div>

            <div class="border-t border-slate-200 p-4 dark:border-slate-800 sm:p-5">
                <form id="chatForm" class="relative">
                    <textarea id="questionInput" rows="2" maxlength="1000" disabled placeholder="Upload a workbook before asking a question…" class="min-h-14 w-full resize-none rounded-2xl border border-slate-300 bg-white py-3 pl-4 pr-16 text-sm outline-none transition placeholder:text-slate-400 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 disabled:cursor-not-allowed disabled:bg-slate-100 dark:border-slate-700 dark:bg-slate-950 dark:placeholder:text-slate-500 dark:disabled:bg-slate-900"></textarea>
                    <button id="sendButton" type="submit" disabled class="absolute bottom-2.5 right-2.5 grid h-10 w-10 place-items-center rounded-xl bg-emerald-500 text-white shadow-md transition hover:bg-emerald-600 disabled:cursor-not-allowed disabled:opacity-40" aria-label="Send question">
                        <svg id="sendIcon" viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" stroke-width="1.8" aria-hidden="true"><path d="m4 4 16 8-16 8 3-8z"></path><path d="M7 12h13"></path></svg>
                        <span id="sendSpinner" class="hidden h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                    </button>
                </form>
                <p class="mt-2 text-center text-xs text-slate-400">Answers are limited to the uploaded workbook. Verify critical business decisions.</p>
            </div>
        </section>
    </main>
</div>

<div id="toast" class="pointer-events-none fixed bottom-5 left-1/2 z-50 hidden -translate-x-1/2 rounded-2xl bg-slate-900 px-4 py-3 text-sm text-white shadow-2xl dark:bg-white dark:text-slate-900"></div>
<script src="assets/app.js" defer></script>
</body>
</html>
