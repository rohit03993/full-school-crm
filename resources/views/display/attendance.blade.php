<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Live Attendance — {{ $instituteName }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body {
            margin: 0;
            min-height: 100vh;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: radial-gradient(circle at top, #1e293b 0%, #0f172a 45%, #020617 100%);
            color: #f8fafc;
        }
        .screen { min-height: 100vh; display: flex; flex-direction: column; padding: 1rem 1.25rem; gap: 0.85rem; }
        .header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
        .brand { display: flex; align-items: center; gap: 0.85rem; min-width: 0; }
        .brand img { height: 2.75rem; width: auto; border-radius: 0.65rem; object-fit: contain; background: rgba(255,255,255,0.08); }
        .brand h1 { margin: 0; font-size: clamp(1rem, 2vw, 1.45rem); font-weight: 700; }
        .brand p { margin: 0.1rem 0 0; font-size: 0.78rem; color: #94a3b8; }
        .header-right { display: flex; align-items: flex-start; gap: 0.75rem; flex-wrap: wrap; }
        .clock { text-align: right; font-variant-numeric: tabular-nums; }
        .clock .time { font-size: clamp(1.25rem, 2.5vw, 1.85rem); font-weight: 800; }
        .clock .date { color: #94a3b8; font-size: 0.82rem; }

        /* Custom dropdown — fixes native select white-on-white on Windows */
        .pickers { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .picker { position: relative; min-width: 11rem; }
        .picker-btn {
            width: 100%;
            display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;
            background: #1e293b; border: 1px solid rgba(148,163,184,0.35);
            color: #f8fafc; border-radius: 0.75rem; padding: 0.55rem 0.75rem;
            font-size: 0.84rem; font-weight: 600; cursor: pointer; text-align: left;
        }
        .picker-btn:hover { border-color: rgba(251,191,36,0.5); background: #243044; }
        .picker-btn svg { width: 1rem; height: 1rem; flex-shrink: 0; color: #94a3b8; }
        .picker-menu {
            display: none; position: absolute; top: calc(100% + 0.35rem); left: 0; right: 0;
            z-index: 50; max-height: 16rem; overflow: auto;
            background: #0f172a; border: 1px solid rgba(148,163,184,0.35);
            border-radius: 0.75rem; box-shadow: 0 16px 40px rgba(0,0,0,0.45);
            padding: 0.35rem;
        }
        .picker.open .picker-menu { display: block; }
        .picker-option {
            display: block; width: 100%; border: none; background: transparent;
            color: #e2e8f0; text-align: left; padding: 0.55rem 0.65rem;
            border-radius: 0.5rem; font-size: 0.82rem; line-height: 1.35;
            cursor: pointer; font-weight: 500;
        }
        .picker-option:hover { background: rgba(251,191,36,0.12); color: #fff; }
        .picker-option.active { background: rgba(59,130,246,0.22); color: #93c5fd; font-weight: 700; }

        .stats-row { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0.65rem; }
        @media (max-width: 900px) { .stats-row { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        .stat {
            background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);
            border-radius: 1rem; padding: 0.75rem 1rem;
        }
        .stat .label { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; font-weight: 700; }
        .stat .value { margin-top: 0.2rem; font-size: clamp(1.35rem, 2.5vw, 1.75rem); font-weight: 800; }
        .stat.present .value { color: #34d399; }
        .stat.inside .value { color: #60a5fa; }
        .stat.out .value { color: #fb7185; }
        .stat.absent .value { color: #fbbf24; }

        .classes-panel {
            background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08);
            border-radius: 1rem; padding: 0.75rem 0.85rem;
        }
        .classes-head {
            display: flex; align-items: center; justify-content: space-between;
            gap: 0.75rem; margin-bottom: 0.65rem; flex-wrap: wrap;
        }
        .classes-head h2 {
            margin: 0; font-size: 0.78rem; text-transform: uppercase;
            letter-spacing: 0.08em; color: #94a3b8; font-weight: 800;
        }
        .classes-head p { margin: 0; font-size: 0.72rem; color: #64748b; }
        .clear-filter {
            border: 1px solid rgba(148,163,184,0.3); background: transparent;
            color: #cbd5e1; border-radius: 999px; padding: 0.25rem 0.65rem;
            font-size: 0.72rem; font-weight: 600; cursor: pointer;
        }
        .clear-filter:hover { border-color: #fbbf24; color: #fbbf24; }
        .classes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 0.55rem;
            max-height: 9.5rem;
            overflow-y: auto;
            padding-right: 0.15rem;
        }
        .classes-grid::-webkit-scrollbar { width: 6px; }
        .classes-grid::-webkit-scrollbar-thumb { background: rgba(148,163,184,0.35); border-radius: 999px; }
        .class-card {
            background: #1e293b; border: 1px solid rgba(148,163,184,0.2);
            border-radius: 0.75rem; padding: 0.65rem 0.75rem;
            cursor: pointer; transition: border-color 0.15s, background 0.15s;
            text-align: left; width: 100%;
        }
        .class-card:hover { border-color: rgba(251,191,36,0.45); background: #243044; }
        .class-card.selected { border-color: #3b82f6; background: rgba(59,130,246,0.12); box-shadow: inset 0 0 0 1px rgba(59,130,246,0.25); }
        .class-name {
            font-size: 0.82rem; font-weight: 700; color: #f1f5f9; line-height: 1.3;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
        }
        .class-stats {
            margin-top: 0.45rem; display: flex; align-items: baseline; justify-content: space-between; gap: 0.35rem;
        }
        .class-present { font-size: 1.15rem; font-weight: 800; color: #34d399; font-variant-numeric: tabular-nums; }
        .class-present span { font-size: 0.72rem; font-weight: 600; color: #94a3b8; }
        .class-meta { font-size: 0.68rem; color: #94a3b8; margin-top: 0.25rem; }
        .class-bar {
            margin-top: 0.4rem; height: 4px; border-radius: 999px;
            background: rgba(148,163,184,0.2); overflow: hidden;
        }
        .class-bar-fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg, #059669, #34d399); }

        .content { flex: 1; display: grid; grid-template-columns: 1fr min(300px, 30%); gap: 0.85rem; min-height: 0; }
        @media (max-width: 960px) { .content { grid-template-columns: 1fr; } }
        .hero { display: grid; place-items: center; min-height: 16rem; }
        .card {
            width: min(720px, 100%); border-radius: 1.35rem; overflow: hidden;
            background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);
            box-shadow: 0 20px 50px rgba(0,0,0,0.35);
            opacity: 0; transform: translateY(8px); transition: opacity 0.35s ease, transform 0.35s ease;
        }
        .card.visible { opacity: 1; transform: translateY(0); }
        .card.in .banner { background: linear-gradient(90deg, #059669, #10b981); }
        .card.out .banner { background: linear-gradient(90deg, #be123c, #f43f5e); }
        .banner { padding: 0.65rem 1rem; font-size: 0.75rem; font-weight: 800; letter-spacing: 0.12em; text-transform: uppercase; }
        .body { display: grid; grid-template-columns: 160px 1fr; gap: 1.1rem; padding: 1.2rem; }
        @media (max-width: 640px) { .body { grid-template-columns: 1fr; text-align: center; } }
        .photo-wrap {
            aspect-ratio: 4/5; border-radius: 0.85rem; overflow: hidden;
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.08);
            display: grid; place-items: center;
        }
        .photo-wrap img { width: 100%; height: 100%; object-fit: cover; }
        .initials { font-size: 2.5rem; font-weight: 800; color: #64748b; }
        .details h2 { margin: 0; font-size: clamp(1.35rem, 2.8vw, 2rem); line-height: 1.15; }
        .meta { margin-top: 0.65rem; display: grid; gap: 0.35rem; font-size: 0.88rem; }
        .meta-row { display: flex; flex-wrap: wrap; gap: 0.3rem 0.65rem; align-items: baseline; }
        .meta .label { color: #94a3b8; font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.06em; min-width: 3.25rem; }
        .meta .value { font-weight: 600; word-break: break-word; }
        .punch-time { margin-top: 0.75rem; font-size: clamp(1.5rem, 3.5vw, 2.2rem); font-weight: 800; color: #fbbf24; font-variant-numeric: tabular-nums; }
        .idle { text-align: center; max-width: 26rem; padding: 0.75rem; }
        .idle h2 { margin: 0 0 0.3rem; font-size: 1.2rem; }
        .idle p { margin: 0; color: #94a3b8; line-height: 1.45; font-size: 0.88rem; }
        .recent-panel {
            background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);
            border-radius: 1rem; display: flex; flex-direction: column; min-height: 16rem; overflow: hidden;
        }
        .recent-panel h3 {
            margin: 0; padding: 0.75rem 0.85rem; font-size: 0.72rem;
            text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .recent-list { flex: 1; overflow: auto; padding: 0.45rem; display: grid; gap: 0.4rem; }
        .snippet {
            display: grid; grid-template-columns: 36px 1fr auto; gap: 0.5rem; align-items: center;
            padding: 0.4rem 0.5rem; border-radius: 0.65rem;
            background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);
        }
        .snippet.active { border-color: rgba(251,191,36,0.45); background: rgba(251,191,36,0.08); }
        .snippet-thumb {
            width: 36px; height: 36px; border-radius: 0.5rem; overflow: hidden;
            background: rgba(255,255,255,0.06); display: grid; place-items: center;
            font-size: 0.7rem; font-weight: 800; color: #64748b;
        }
        .snippet-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .snippet-name { font-size: 0.78rem; font-weight: 700; line-height: 1.2; }
        .snippet-meta { font-size: 0.65rem; color: #94a3b8; margin-top: 0.08rem; }
        .snippet-state { font-size: 0.6rem; font-weight: 800; padding: 0.18rem 0.4rem; border-radius: 999px; text-align: center; }
        .snippet-state.in { background: rgba(16,185,129,0.18); color: #6ee7b7; }
        .snippet-state.out { background: rgba(244,63,94,0.18); color: #fda4af; }
        .snippet-time { font-size: 0.65rem; color: #cbd5e1; font-variant-numeric: tabular-nums; margin-top: 0.12rem; text-align: right; }
        .empty-recent { padding: 0.85rem; color: #64748b; font-size: 0.82rem; text-align: center; }
        .status-dot {
            display: inline-block; width: 0.5rem; height: 0.5rem; border-radius: 999px;
            background: #22c55e; margin-right: 0.3rem; animation: pulse 2s infinite;
        }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.35; } }
    </style>
</head>
<body>
<div class="screen" id="app"
     data-latest-url="{{ $latestUrl }}"
     data-poll-ms="{{ $pollIntervalMs }}"
     data-card-ms="{{ $cardDurationMs }}"
     data-since="{{ $maxPunchId }}">
    <header class="header">
        <div class="brand">
            @if ($instituteLogo)
                <img src="{{ $instituteLogo }}" alt="">
            @endif
            <div>
                <h1>{{ $instituteName }}</h1>
                <p><span class="status-dot"></span>Live attendance display</p>
            </div>
        </div>
        <div class="header-right">
            <div class="pickers">
                <div class="picker" id="picker-batch">
                    <button type="button" class="picker-btn" id="picker-batch-btn" aria-haspopup="listbox" aria-expanded="false">
                        <span id="picker-batch-label">All classes</span>
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 10.94l3.71-3.71a.75.75 0 1 1 1.06 1.06l-4.24 4.25a.75.75 0 0 1-1.06 0L5.21 8.29a.75.75 0 0 1 .02-1.08Z" clip-rule="evenodd"/></svg>
                    </button>
                    <div class="picker-menu" role="listbox" id="picker-batch-menu">
                        <button type="button" class="picker-option active" data-value="">All classes</button>
                        @foreach ($batchOptions as $batch)
                            <button type="button" class="picker-option" data-value="{{ $batch['id'] }}">{{ $batch['name'] }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="picker" id="picker-state">
                    <button type="button" class="picker-btn" id="picker-state-btn" aria-haspopup="listbox" aria-expanded="false">
                        <span id="picker-state-label">All punches</span>
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 10.94l3.71-3.71a.75.75 0 1 1 1.06 1.06l-4.24 4.25a.75.75 0 0 1-1.06 0L5.21 8.29a.75.75 0 0 1 .02-1.08Z" clip-rule="evenodd"/></svg>
                    </button>
                    <div class="picker-menu" role="listbox" id="picker-state-menu">
                        <button type="button" class="picker-option active" data-value="">All punches</button>
                        <button type="button" class="picker-option" data-value="IN">IN only (check-in)</button>
                        <button type="button" class="picker-option" data-value="OUT">OUT only (check-out)</button>
                    </div>
                </div>
            </div>
            <div class="clock">
                <div class="time" id="live-clock">--:--:--</div>
                <div class="date" id="live-date"></div>
            </div>
        </div>
    </header>

    <section class="stats-row">
        <div class="stat present"><div class="label">Present today</div><div class="value" id="stat-present">{{ $initialSummary['present_today'] ?? 0 }}</div></div>
        <div class="stat inside"><div class="label">Inside now</div><div class="value" id="stat-inside">{{ $initialSummary['inside_now'] ?? 0 }}</div></div>
        <div class="stat out"><div class="label">Checked out</div><div class="value" id="stat-out">{{ $initialSummary['checked_out'] ?? 0 }}</div></div>
        <div class="stat absent"><div class="label">Absent</div><div class="value" id="stat-absent">{{ $initialSummary['absent'] ?? 0 }}</div></div>
    </section>

    <section class="classes-panel">
        <div class="classes-head">
            <div>
                <h2>Class-wise today</h2>
                <p>Click a class to filter the screen</p>
            </div>
            <button type="button" class="clear-filter" id="clear-filter" hidden>Show all classes</button>
        </div>
        <div class="classes-grid" id="batch-row"></div>
    </section>

    <div class="content">
        <section class="hero">
            <div id="card-container"></div>
            <div class="idle" id="idle-screen">
                <h2>Waiting for attendance</h2>
                <p>Punch IN or OUT on the biometric device or mark manually in admin.</p>
            </div>
        </section>
        <aside class="recent-panel">
            <h3>Latest 10 punches</h3>
            <div class="recent-list" id="recent-list"></div>
        </aside>
    </div>
</div>

<script>
(function () {
    const app = document.getElementById('app');
    const latestUrl = app.dataset.latestUrl;
    let sinceId = parseInt(app.dataset.since || '0', 10);
    const pollMs = parseInt(app.dataset.pollMs || '2500', 10);
    const cardMs = parseInt(app.dataset.cardMs || '10000', 10);
    const container = document.getElementById('card-container');
    const idle = document.getElementById('idle-screen');
    const recentList = document.getElementById('recent-list');
    const clearFilterBtn = document.getElementById('clear-filter');

    let filterBatch = '';
    let filterState = '';
    let hideTimer = null;
    let activeSnippetId = null;

    const initialBatch = @json($initialBatchId);
    const initialState = @json($initialState);
    if (initialBatch) filterBatch = String(initialBatch);
    if (initialState) filterState = initialState;

    function setupPicker(pickerId, labelId, onSelect) {
        const picker = document.getElementById(pickerId);
        const btn = picker.querySelector('.picker-btn');
        const menu = picker.querySelector('.picker-menu');
        const label = document.getElementById(labelId);

        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            document.querySelectorAll('.picker.open').forEach(p => { if (p !== picker) p.classList.remove('open'); });
            picker.classList.toggle('open');
            btn.setAttribute('aria-expanded', picker.classList.contains('open') ? 'true' : 'false');
        });

        menu.querySelectorAll('.picker-option').forEach(opt => {
            opt.addEventListener('click', () => {
                menu.querySelectorAll('.picker-option').forEach(o => o.classList.remove('active'));
                opt.classList.add('active');
                label.textContent = opt.textContent.trim();
                picker.classList.remove('open');
                btn.setAttribute('aria-expanded', 'false');
                onSelect(opt.dataset.value || '');
            });
        });
    }

    document.addEventListener('click', () => {
        document.querySelectorAll('.picker.open').forEach(p => {
            p.classList.remove('open');
            p.querySelector('.picker-btn')?.setAttribute('aria-expanded', 'false');
        });
    });

    setupPicker('picker-batch', 'picker-batch-label', (value) => {
        filterBatch = value;
        syncPickerHighlight();
        onFilterChange();
    });

    setupPicker('picker-state', 'picker-state-label', (value) => {
        filterState = value;
        onFilterChange();
    });

    function applyInitialPickerLabels() {
        if (filterBatch) {
            const opt = document.querySelector(`#picker-batch-menu .picker-option[data-value="${filterBatch}"]`);
            if (opt) {
                document.querySelectorAll('#picker-batch-menu .picker-option').forEach(o => o.classList.remove('active'));
                opt.classList.add('active');
                document.getElementById('picker-batch-label').textContent = opt.textContent.trim();
            }
        }
        if (filterState) {
            const opt = document.querySelector(`#picker-state-menu .picker-option[data-value="${filterState}"]`);
            if (opt) {
                document.querySelectorAll('#picker-state-menu .picker-option').forEach(o => o.classList.remove('active'));
                opt.classList.add('active');
                document.getElementById('picker-state-label').textContent = opt.textContent.trim();
            }
        }
    }

    function filterParams() {
        const params = new URLSearchParams();
        params.set('since', String(sinceId));
        if (filterBatch) params.set('batch_id', filterBatch);
        if (filterState) params.set('state', filterState);
        return params;
    }

    function syncUrlFilters() {
        const params = new URLSearchParams(window.location.search);
        if (filterBatch) params.set('batch_id', filterBatch); else params.delete('batch_id');
        if (filterState) params.set('state', filterState); else params.delete('state');
        const qs = params.toString();
        history.replaceState({}, '', qs ? `${window.location.pathname}?${qs}` : window.location.pathname);
        clearFilterBtn.hidden = !filterBatch;
    }

    function syncPickerHighlight() {
        document.querySelectorAll('#batch-row .class-card').forEach(card => {
            card.classList.toggle('selected', filterBatch && card.dataset.batchId === filterBatch);
        });
    }

    function updateClock() {
        const now = new Date();
        document.getElementById('live-clock').textContent = now.toLocaleTimeString([], {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        document.getElementById('live-date').textContent = now.toLocaleDateString([], {
            weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'
        });
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function formatTime(time) {
        if (!time) return '—';
        const parts = String(time).split(':');
        if (parts.length < 2) return time;
        const d = new Date();
        d.setHours(parseInt(parts[0], 10), parseInt(parts[1], 10), parseInt(parts[2] || '0', 10));
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: true });
    }

    function renderClassGrid(batches) {
        const batchRow = document.getElementById('batch-row');
        if (!batches || batches.length === 0) {
            batchRow.innerHTML = '<div class="empty-recent">No active classes</div>';
            return;
        }
        batchRow.innerHTML = batches.map(b => {
            const total = b.total || 0;
            const present = b.present || 0;
            const pct = total > 0 ? Math.round((present / total) * 100) : 0;
            const selected = filterBatch && String(b.batch_id) === filterBatch ? ' selected' : '';
            return `
                <button type="button" class="class-card${selected}" data-batch-id="${b.batch_id}" title="${escapeHtml(b.batch_name)}">
                    <div class="class-name">${escapeHtml(b.batch_name)}</div>
                    <div class="class-stats">
                        <div class="class-present">${present}<span> / ${total}</span></div>
                        <div class="class-meta">${b.inside} inside</div>
                    </div>
                    <div class="class-bar"><div class="class-bar-fill" style="width:${pct}%"></div></div>
                    <div class="class-meta">${b.absent} absent · ${pct}% present</div>
                </button>`;
        }).join('');

        batchRow.querySelectorAll('.class-card').forEach(card => {
            card.addEventListener('click', () => {
                const id = card.dataset.batchId;
                filterBatch = filterBatch === id ? '' : id;
                applyInitialPickerLabels();
                if (!filterBatch) {
                    document.querySelectorAll('#picker-batch-menu .picker-option').forEach(o => o.classList.remove('active'));
                    document.querySelector('#picker-batch-menu .picker-option[data-value=""]')?.classList.add('active');
                    document.getElementById('picker-batch-label').textContent = 'All classes';
                } else {
                    const opt = document.querySelector(`#picker-batch-menu .picker-option[data-value="${id}"]`);
                    if (opt) {
                        document.querySelectorAll('#picker-batch-menu .picker-option').forEach(o => o.classList.remove('active'));
                        opt.classList.add('active');
                        document.getElementById('picker-batch-label').textContent = opt.textContent.trim();
                    }
                }
                onFilterChange();
            });
        });
    }

    function renderStats(summary) {
        if (!summary) return;
        document.getElementById('stat-present').textContent = summary.present_today ?? 0;
        document.getElementById('stat-inside').textContent = summary.inside_now ?? 0;
        document.getElementById('stat-out').textContent = summary.checked_out ?? 0;
        document.getElementById('stat-absent').textContent = summary.absent ?? 0;
        renderClassGrid(summary.by_batch || []);
        syncUrlFilters();
    }

    function renderRecent(recent) {
        if (!recent || recent.length === 0) {
            recentList.innerHTML = '<div class="empty-recent">No punches yet today.</div>';
            return;
        }
        recentList.innerHTML = recent.map(item => {
            const isActive = activeSnippetId === item.id ? ' active' : '';
            const thumb = item.photo_url
                ? `<img src="${escapeHtml(item.photo_url)}" alt="">`
                : escapeHtml(item.initials || '?');
            return `
                <div class="snippet${isActive}">
                    <div class="snippet-thumb">${item.photo_url ? `<img src="${escapeHtml(item.photo_url)}" alt="">` : thumb}</div>
                    <div>
                        <div class="snippet-name">${escapeHtml(item.name)}</div>
                        <div class="snippet-meta">${escapeHtml(item.roll)}${item.batch ? ' · ' + escapeHtml(item.batch) : ''}</div>
                    </div>
                    <div>
                        <div class="snippet-state ${item.state === 'IN' ? 'in' : 'out'}">${item.state}</div>
                        <div class="snippet-time">${escapeHtml(formatTime(item.time))}</div>
                    </div>
                </div>`;
        }).join('');
    }

    function renderCard(punch) {
        activeSnippetId = punch.id;
        renderRecent(window.__lastRecent || []);
        const isIn = punch.state === 'IN';
        const photoHtml = punch.photo_url
            ? `<img src="${escapeHtml(punch.photo_url)}" alt="">`
            : `<div class="initials">${escapeHtml(punch.initials || '?')}</div>`;

        container.innerHTML = `
            <article class="card ${isIn ? 'in' : 'out'} visible">
                <div class="banner">${isIn ? 'Checked in' : 'Checked out'}</div>
                <div class="body">
                    <div class="photo-wrap">${photoHtml}</div>
                    <div class="details">
                        <h2>${escapeHtml(punch.name)}</h2>
                        <div class="meta">
                            <div class="meta-row"><span class="label">Roll</span><span class="value">${escapeHtml(punch.roll)}</span></div>
                            ${punch.batch ? `<div class="meta-row"><span class="label">Class</span><span class="value">${escapeHtml(punch.batch)}</span></div>` : ''}
                            ${punch.course ? `<div class="meta-row"><span class="label">Course</span><span class="value">${escapeHtml(punch.course)}</span></div>` : ''}
                            <div class="meta-row"><span class="label">Source</span><span class="value">${escapeHtml(punch.source)}</span></div>
                        </div>
                        <div class="punch-time">${escapeHtml(formatTime(punch.time))}</div>
                    </div>
                </div>
            </article>`;
        idle.style.display = 'none';
        if (hideTimer) clearTimeout(hideTimer);
        hideTimer = setTimeout(() => {
            container.querySelector('.card')?.classList.remove('visible');
            setTimeout(() => {
                container.innerHTML = '';
                idle.style.display = 'block';
                activeSnippetId = null;
                renderRecent(window.__lastRecent || []);
            }, 350);
        }, cardMs);
    }

    async function poll() {
        try {
            const response = await fetch(`${latestUrl}?${filterParams()}`, {
                headers: { 'Accept': 'application/json' },
                cache: 'no-store',
            });
            if (!response.ok) return;
            const data = await response.json();
            window.__lastRecent = data.recent || [];
            renderStats(data.summary);
            renderRecent(window.__lastRecent);
            const punches = data.punches || [];
            if (typeof data.max_id === 'number' && data.max_id > sinceId) sinceId = data.max_id;
            if (punches.length > 0) renderCard(punches[punches.length - 1]);
        } catch (e) { /* retry */ }
    }

    function onFilterChange() {
        syncUrlFilters();
        syncPickerHighlight();
        poll();
    }

    clearFilterBtn.addEventListener('click', () => {
        filterBatch = '';
        document.querySelectorAll('#picker-batch-menu .picker-option').forEach(o => o.classList.remove('active'));
        document.querySelector('#picker-batch-menu .picker-option[data-value=""]')?.classList.add('active');
        document.getElementById('picker-batch-label').textContent = 'All classes';
        onFilterChange();
    });

    applyInitialPickerLabels();
    window.__lastRecent = @json($initialRecent);
    renderStats(@json($initialSummary));
    renderRecent(window.__lastRecent);
    @if ($latestPunch)
    renderCard(@json($latestPunch));
    @endif

    updateClock();
    setInterval(updateClock, 1000);
    setInterval(poll, pollMs);
})();
</script>
</body>
</html>
