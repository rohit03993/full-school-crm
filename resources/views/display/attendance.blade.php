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
            border-radius: 0.85rem; padding: 0.5rem 0.65rem 0.6rem;
        }
        .classes-head {
            display: flex; align-items: center; justify-content: space-between;
            gap: 0.5rem; margin-bottom: 0.45rem; flex-wrap: wrap;
        }
        .classes-head h2 {
            margin: 0; font-size: 0.68rem; text-transform: uppercase;
            letter-spacing: 0.08em; color: #94a3b8; font-weight: 800;
        }
        .classes-head p { margin: 0; font-size: 0.65rem; color: #64748b; }
        .clear-filter {
            border: 1px solid rgba(148,163,184,0.3); background: transparent;
            color: #cbd5e1; border-radius: 999px; padding: 0.15rem 0.5rem;
            font-size: 0.65rem; font-weight: 600; cursor: pointer;
        }
        .clear-filter:hover { border-color: #fbbf24; color: #fbbf24; }
        .classes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(118px, 1fr));
            gap: 0.35rem;
        }
        @media (min-width: 1400px) {
            .classes-grid { grid-template-columns: repeat(10, minmax(0, 1fr)); }
        }
        @media (min-width: 1200px) and (max-width: 1399px) {
            .classes-grid { grid-template-columns: repeat(8, minmax(0, 1fr)); }
        }
        .class-card {
            background: #1e293b; border: 1px solid rgba(148,163,184,0.18);
            border-radius: 0.55rem; padding: 0.38rem 0.45rem 0.42rem;
            cursor: pointer; transition: border-color 0.15s, background 0.15s;
            text-align: left; width: 100%; min-height: 0;
        }
        .class-card:hover { border-color: rgba(251,191,36,0.45); background: #243044; }
        .class-card.selected {
            border-color: #3b82f6; background: rgba(59,130,246,0.14);
            box-shadow: inset 0 0 0 1px rgba(59,130,246,0.3);
        }
        .class-name {
            font-size: 0.64rem; font-weight: 700; color: #e2e8f0; line-height: 1.25;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .class-line {
            margin-top: 0.2rem;
            display: flex; align-items: center; justify-content: space-between; gap: 0.2rem;
        }
        .class-present {
            font-size: 0.78rem; font-weight: 800; color: #34d399;
            font-variant-numeric: tabular-nums; line-height: 1;
        }
        .class-present span { font-size: 0.62rem; font-weight: 600; color: #64748b; }
        .class-in { font-size: 0.58rem; font-weight: 700; color: #60a5fa; white-space: nowrap; }
        .class-bar {
            margin-top: 0.28rem; height: 3px; border-radius: 999px;
            background: rgba(148,163,184,0.18); overflow: hidden;
        }
        .class-bar-fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg, #059669, #34d399); }
        .class-pct { margin-top: 0.15rem; font-size: 0.55rem; color: #64748b; font-variant-numeric: tabular-nums; }

        .content { flex: 1; display: grid; grid-template-columns: 1fr min(380px, 36%); gap: 0.85rem; min-height: 0; align-items: stretch; }
        @media (max-width: 1100px) { .content { grid-template-columns: 1fr; } }
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
            background: linear-gradient(180deg, rgba(30,41,59,0.95) 0%, rgba(15,23,42,0.98) 100%);
            border: 1px solid rgba(148,163,184,0.18);
            border-radius: 1.1rem;
            display: flex; flex-direction: column;
            min-height: 20rem; max-height: calc(100vh - 15rem);
            overflow: hidden;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.04);
        }
        .recent-head {
            display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;
            padding: 0.85rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.07);
            background: rgba(0,0,0,0.15);
        }
        .recent-head h3 {
            margin: 0; font-size: 0.72rem; text-transform: uppercase;
            letter-spacing: 0.1em; color: #cbd5e1; font-weight: 800;
        }
        .recent-count {
            font-size: 0.68rem; font-weight: 700; color: #94a3b8;
            background: rgba(255,255,255,0.06); border-radius: 999px;
            padding: 0.2rem 0.55rem; font-variant-numeric: tabular-nums;
        }
        .recent-list {
            flex: 1; overflow-y: auto; overflow-x: hidden;
            padding: 0.55rem; display: flex; flex-direction: column; gap: 0.5rem;
        }
        .recent-list::-webkit-scrollbar { width: 5px; }
        .recent-list::-webkit-scrollbar-thumb { background: rgba(148,163,184,0.35); border-radius: 999px; }
        .feed-item {
            position: relative;
            display: grid;
            grid-template-columns: 52px 1fr auto;
            gap: 0.65rem;
            align-items: center;
            padding: 0.65rem 0.7rem 0.65rem 0.85rem;
            border-radius: 0.85rem;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.06);
            overflow: hidden;
            transition: transform 0.2s ease, border-color 0.2s ease, background 0.2s ease;
        }
        .feed-item::before {
            content: '';
            position: absolute; left: 0; top: 0; bottom: 0; width: 4px;
        }
        .feed-item.in::before { background: linear-gradient(180deg, #10b981, #059669); }
        .feed-item.out::before { background: linear-gradient(180deg, #fb7185, #e11d48); }
        .feed-item:first-child {
            background: rgba(255,255,255,0.06);
            border-color: rgba(251,191,36,0.28);
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        .feed-item.active {
            border-color: rgba(251,191,36,0.55);
            background: rgba(251,191,36,0.1);
        }
        .feed-item.new-item { animation: feedSlideIn 0.45s ease; }
        @keyframes feedSlideIn {
            from { opacity: 0; transform: translateX(12px); }
            to { opacity: 1; transform: translateX(0); }
        }
        .feed-photo {
            width: 52px; height: 52px; border-radius: 0.75rem; overflow: hidden;
            background: linear-gradient(135deg, #334155, #1e293b);
            border: 2px solid rgba(148,163,184,0.25);
            display: grid; place-items: center;
            font-size: 0.85rem; font-weight: 800; color: #94a3b8;
            flex-shrink: 0;
        }
        .feed-item.in .feed-photo { border-color: rgba(16,185,129,0.45); }
        .feed-item.out .feed-photo { border-color: rgba(244,63,94,0.45); }
        .feed-photo img { width: 100%; height: 100%; object-fit: cover; }
        .feed-body { min-width: 0; }
        .feed-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 0.35rem; }
        .feed-name {
            font-size: 0.88rem; font-weight: 800; color: #f8fafc; line-height: 1.25;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .feed-time {
            font-size: 0.82rem; font-weight: 800; color: #fbbf24;
            font-variant-numeric: tabular-nums; white-space: nowrap; flex-shrink: 0;
        }
        .feed-roll {
            display: inline-block; margin-top: 0.2rem;
            font-size: 0.65rem; font-weight: 700; font-family: ui-monospace, monospace;
            color: #94a3b8; background: rgba(255,255,255,0.06);
            padding: 0.12rem 0.4rem; border-radius: 0.35rem;
        }
        .feed-class {
            margin-top: 0.25rem; font-size: 0.72rem; color: #64748b; line-height: 1.3;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
        }
        .feed-side { display: flex; flex-direction: column; align-items: flex-end; gap: 0.25rem; flex-shrink: 0; }
        .feed-badge {
            font-size: 0.62rem; font-weight: 900; letter-spacing: 0.08em;
            padding: 0.28rem 0.5rem; border-radius: 999px; min-width: 2.4rem; text-align: center;
        }
        .feed-badge.in { background: rgba(16,185,129,0.22); color: #6ee7b7; border: 1px solid rgba(16,185,129,0.35); }
        .feed-badge.out { background: rgba(244,63,94,0.2); color: #fda4af; border: 1px solid rgba(244,63,94,0.35); }
        .feed-ago { font-size: 0.62rem; color: #64748b; font-variant-numeric: tabular-nums; }
        .empty-recent {
            padding: 2rem 1rem; color: #64748b; font-size: 0.85rem; text-align: center; line-height: 1.5;
        }
        .empty-recent svg { width: 2.5rem; height: 2.5rem; margin: 0 auto 0.65rem; opacity: 0.45; display: block; }
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
     data-summary-poll-ms="{{ $summaryPollIntervalMs }}"
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
                <h2>Class-wise today · <span id="class-count">{{ count($initialSummary['by_batch'] ?? []) }}</span> classes</h2>
                <p>Click a tile to filter · hover for full name</p>
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
            <div class="recent-head">
                <h3>Latest punches</h3>
                <span class="recent-count" id="recent-count">0 today</span>
            </div>
            <div class="recent-list" id="recent-list"></div>
        </aside>
    </div>
</div>

<script>
(function () {
    const app = document.getElementById('app');
    const latestUrl = app.dataset.latestUrl;
    let sinceId = parseInt(app.dataset.since || '0', 10);
    const pollMs = parseInt(app.dataset.pollMs || '2000', 10);
    const summaryPollMs = parseInt(app.dataset.summaryPollMs || '15000', 10);
    const cardMs = parseInt(app.dataset.cardMs || '10000', 10);
    const container = document.getElementById('card-container');
    const idle = document.getElementById('idle-screen');
    const recentList = document.getElementById('recent-list');
    const clearFilterBtn = document.getElementById('clear-filter');

    let filterBatch = '';
    let filterState = '';
    let hideTimer = null;
    let activeSnippetId = null;
    let knownRecentIds = new Set();
    let firstRecentRender = true;
    let lastRecentSignature = '';
    let lastShownPunchId = @json($latestPunch['id'] ?? 0);
    let lastSummaryAt = 0;
    let lastSummaryJson = '';
    let pollInFlight = false;

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

    function filterParams(includeSummary) {
        const params = new URLSearchParams();
        params.set('since', String(sinceId));
        params.set('sections', includeSummary ? 'live,summary' : 'live');
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

    function formatTimeFull(time) {
        if (!time) return '—';
        const parts = String(time).split(':');
        if (parts.length < 2) return time;
        const d = new Date();
        d.setHours(parseInt(parts[0], 10), parseInt(parts[1], 10), parseInt(parts[2] || '0', 10));
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: true });
    }

    function timeAgo(time) {
        if (!time) return '';
        const parts = String(time).split(':');
        if (parts.length < 2) return '';
        const d = new Date();
        d.setHours(parseInt(parts[0], 10), parseInt(parts[1], 10), parseInt(parts[2] || '0', 10));
        const diffSec = Math.round((Date.now() - d.getTime()) / 1000);
        if (diffSec < 45) return 'Just now';
        if (diffSec < 3600) return Math.floor(diffSec / 60) + 'm ago';
        return '';
    }

    function shortBatch(name) {
        if (!name) return '';
        const n = String(name);
        return n.length > 36 ? n.slice(0, 34) + '…' : n;
    }

    function recentSignature(recent) {
        if (!recent || recent.length === 0) return '';
        return recent.map(item => `${item.id}:${item.state}:${item.time}:${item.roll}`).join('|');
    }

    function syncActiveRecentHighlight() {
        recentList.querySelectorAll('.feed-item').forEach(el => {
            const id = parseInt(el.dataset.id, 10);
            el.classList.toggle('active', activeSnippetId !== null && id === activeSnippetId);
        });
    }

    function setFeedPhoto(photoWrap, item) {
        const photoId = item.photo_id ? String(item.photo_id) : '';
        const existingImg = photoWrap.querySelector('img');

        if (item.photo_url) {
            if (existingImg && (existingImg.dataset.photoId === photoId || existingImg.src === item.photo_url)) {
                return;
            }
            photoWrap.textContent = '';
            const img = document.createElement('img');
            img.src = item.photo_url;
            img.alt = '';
            if (photoId) img.dataset.photoId = photoId;
            photoWrap.appendChild(img);
            return;
        }

        if (existingImg) {
            photoWrap.textContent = '';
        }
        if (photoWrap.textContent !== (item.initials || '?')) {
            photoWrap.textContent = item.initials || '?';
        }
    }

    function updateFeedItem(el, item, index, isNew) {
        const stateClass = item.state === 'IN' ? 'in' : 'out';
        el.className = `feed-item ${stateClass}${activeSnippetId === item.id ? ' active' : ''}${isNew ? ' new-item' : ''}${index === 0 ? ' latest' : ''}`;
        el.dataset.id = String(item.id);

        setFeedPhoto(el.querySelector('.feed-photo'), item);

        const nameEl = el.querySelector('.feed-name');
        if (nameEl) {
            nameEl.textContent = item.name || '';
            nameEl.title = item.name || '';
        }

        const timeEl = el.querySelector('.feed-time');
        if (timeEl) timeEl.textContent = formatTimeFull(item.time);

        const rollEl = el.querySelector('.feed-roll');
        if (rollEl) rollEl.textContent = item.roll || '';

        const classEl = el.querySelector('.feed-class');
        if (item.batch) {
            if (!classEl) {
                const body = el.querySelector('.feed-body');
                if (body) {
                    const div = document.createElement('div');
                    div.className = 'feed-class';
                    body.appendChild(div);
                }
            }
            const target = el.querySelector('.feed-class');
            if (target) {
                target.textContent = shortBatch(item.batch);
                target.title = item.batch;
            }
        } else if (classEl) {
            classEl.remove();
        }

        const badgeEl = el.querySelector('.feed-badge');
        if (badgeEl) {
            badgeEl.textContent = item.state;
            badgeEl.className = `feed-badge ${stateClass}`;
        }

        const agoEl = el.querySelector('.feed-ago');
        const ago = timeAgo(item.time);
        if (ago) {
            if (!agoEl) {
                const side = el.querySelector('.feed-side');
                if (side) {
                    const span = document.createElement('span');
                    span.className = 'feed-ago';
                    side.appendChild(span);
                }
            }
            const target = el.querySelector('.feed-ago');
            if (target) target.textContent = ago;
        } else if (agoEl) {
            agoEl.remove();
        }
    }

    function createFeedItem(item, index, isNew) {
        const el = document.createElement('article');
        el.innerHTML = `
            <div class="feed-photo"></div>
            <div class="feed-body">
                <div class="feed-top">
                    <div class="feed-name"></div>
                    <div class="feed-time"></div>
                </div>
                <span class="feed-roll"></span>
            </div>
            <div class="feed-side">
                <span class="feed-badge"></span>
            </div>`;
        updateFeedItem(el, item, index, isNew);
        return el;
    }

    function renderRecent(recent, force = false) {
        const countEl = document.getElementById('recent-count');
        const total = recent?.length || 0;
        countEl.textContent = total ? `${total} shown` : '0 today';

        if (!recent || recent.length === 0) {
            if (!recentList.querySelector('.empty-recent')) {
                recentList.innerHTML = `
                    <div class="empty-recent">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.21a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/></svg>
                        No punches yet today.<br>New check-ins will appear here instantly.
                    </div>`;
            }
            knownRecentIds = new Set();
            lastRecentSignature = '';
            firstRecentRender = true;
            return;
        }

        const signature = recentSignature(recent);
        if (!force && signature === lastRecentSignature) {
            syncActiveRecentHighlight();
            return;
        }

        const incomingIds = new Set(recent.map(item => item.id));
        const existingById = new Map();

        recentList.querySelectorAll('.feed-item').forEach(el => {
            const id = parseInt(el.dataset.id, 10);
            if (!incomingIds.has(id)) {
                el.remove();
            } else {
                existingById.set(id, el);
            }
        });

        const emptyState = recentList.querySelector('.empty-recent');
        if (emptyState) emptyState.remove();

        recent.forEach((item, index) => {
            const isNew = !firstRecentRender && !knownRecentIds.has(item.id);
            let el = existingById.get(item.id);
            if (el) {
                updateFeedItem(el, item, index, isNew);
            } else {
                el = createFeedItem(item, index, isNew);
            }
            recentList.appendChild(el);
        });

        knownRecentIds = new Set(recent.map(item => item.id));
        lastRecentSignature = signature;
        firstRecentRender = false;
    }

    function shortClassLabel(name) {
        if (!name) return '';
        let n = String(name).trim();
        n = n.replace(/\s*\(\d{4}[-–]\d{2,4}\)\s*$/i, '');
        n = n.replace(/\s*\(\d{4}\)\s*$/i, '');
        return n.length > 22 ? n.slice(0, 20) + '…' : n;
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
            const label = shortClassLabel(b.batch_name);
            return `
                <button type="button" class="class-card${selected}" data-batch-id="${b.batch_id}" title="${escapeHtml(b.batch_name)} — ${present}/${total} present, ${b.inside} inside">
                    <div class="class-name">${escapeHtml(label)}</div>
                    <div class="class-line">
                        <div class="class-present">${present}<span>/${total}</span></div>
                        <div class="class-in">${b.inside} in</div>
                    </div>
                    <div class="class-bar"><div class="class-bar-fill" style="width:${pct}%"></div></div>
                    <div class="class-pct">${pct}% · ${b.absent} abs</div>
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
        const countEl = document.getElementById('class-count');
        if (countEl) countEl.textContent = String((summary.by_batch || []).length);
        syncUrlFilters();
    }

    function renderCard(punch) {
        activeSnippetId = punch.id;
        syncActiveRecentHighlight();
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
                        <div class="punch-time">${escapeHtml(formatTimeFull(punch.time))}</div>
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
                syncActiveRecentHighlight();
            }, 350);
        }, cardMs);
    }

    async function poll(forceSummary = false) {
        if (pollInFlight) return;
        pollInFlight = true;

        const wantSummary = forceSummary || (Date.now() - lastSummaryAt >= summaryPollMs);

        try {
            const response = await fetch(`${latestUrl}?${filterParams(wantSummary)}`, {
                headers: { 'Accept': 'application/json' },
                cache: 'no-store',
            });
            if (!response.ok) return;
            const data = await response.json();

            if (data.summary) {
                const summaryJson = JSON.stringify(data.summary);
                if (summaryJson !== lastSummaryJson) {
                    renderStats(data.summary);
                    lastSummaryJson = summaryJson;
                }
                lastSummaryAt = Date.now();
            }

            window.__lastRecent = data.recent || [];
            const recentSig = recentSignature(window.__lastRecent);
            if (recentSig !== lastRecentSignature) {
                renderRecent(window.__lastRecent);
            } else {
                syncActiveRecentHighlight();
            }

            if (typeof data.max_id === 'number' && data.max_id > sinceId) {
                sinceId = data.max_id;
            }

            const latest = data.latest || null;
            if (latest && latest.id > lastShownPunchId) {
                lastShownPunchId = latest.id;
                renderCard(latest);
                return;
            }

            const punches = data.punches || [];
            if (punches.length > 0) {
                const punch = punches[punches.length - 1];
                if (punch.id > lastShownPunchId) {
                    lastShownPunchId = punch.id;
                    renderCard(punch);
                }
            }
        } catch (e) { /* retry */ }
        finally {
            pollInFlight = false;
        }
    }

    function onFilterChange() {
        syncUrlFilters();
        syncPickerHighlight();
        lastSummaryAt = 0;
        lastRecentSignature = '';
        firstRecentRender = true;
        poll(true);
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
    renderRecent(window.__lastRecent, true);
    @if ($latestPunch)
    lastShownPunchId = @json($latestPunch['id']);
    renderCard(@json($latestPunch));
    @endif

    updateClock();
    setInterval(updateClock, 1000);
    poll(true);
    setInterval(() => poll(false), pollMs);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') poll(true);
    });
})();
</script>
</body>
</html>
