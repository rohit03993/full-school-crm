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
        .screen { min-height: 100vh; display: flex; flex-direction: column; padding: 1rem 1.25rem 1.25rem; gap: 1rem; }
        .header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
        .brand { display: flex; align-items: center; gap: 0.85rem; min-width: 0; }
        .brand img { height: 2.75rem; width: auto; border-radius: 0.65rem; object-fit: contain; background: rgba(255,255,255,0.08); }
        .brand h1 { margin: 0; font-size: clamp(1rem, 2vw, 1.45rem); font-weight: 700; }
        .brand p { margin: 0.1rem 0 0; font-size: 0.78rem; color: #94a3b8; }
        .header-right { display: flex; align-items: flex-start; gap: 1rem; flex-wrap: wrap; }
        .filters { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .filters select {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            color: #f8fafc;
            border-radius: 0.75rem;
            padding: 0.45rem 0.75rem;
            font-size: 0.82rem;
            min-width: 8rem;
        }
        .clock { text-align: right; font-variant-numeric: tabular-nums; }
        .clock .time { font-size: clamp(1.25rem, 2.5vw, 1.85rem); font-weight: 800; }
        .clock .date { color: #94a3b8; font-size: 0.82rem; }
        .stats-row { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0.65rem; }
        @media (max-width: 900px) { .stats-row { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        .stat {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 1rem;
            padding: 0.85rem 1rem;
        }
        .stat .label { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; font-weight: 700; }
        .stat .value { margin-top: 0.25rem; font-size: clamp(1.35rem, 2.5vw, 1.85rem); font-weight: 800; }
        .stat.present .value { color: #34d399; }
        .stat.inside .value { color: #60a5fa; }
        .stat.out .value { color: #fb7185; }
        .stat.absent .value { color: #fbbf24; }
        .batch-row { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .batch-chip {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 999px;
            padding: 0.35rem 0.75rem;
            font-size: 0.78rem;
            color: #cbd5e1;
        }
        .batch-chip strong { color: #f8fafc; }
        .batch-chip .in { color: #34d399; }
        .content { flex: 1; display: grid; grid-template-columns: 1fr min(320px, 32%); gap: 1rem; min-height: 0; }
        @media (max-width: 960px) { .content { grid-template-columns: 1fr; } }
        .hero { display: grid; place-items: center; min-height: 18rem; }
        .card {
            width: min(760px, 100%);
            border-radius: 1.5rem;
            overflow: hidden;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            box-shadow: 0 24px 60px rgba(0,0,0,0.35);
            opacity: 0;
            transform: translateY(10px) scale(0.98);
            transition: opacity 0.4s ease, transform 0.4s ease;
        }
        .card.visible { opacity: 1; transform: translateY(0) scale(1); }
        .card.in .banner { background: linear-gradient(90deg, #059669, #10b981); }
        .card.out .banner { background: linear-gradient(90deg, #be123c, #f43f5e); }
        .banner { padding: 0.7rem 1rem; font-size: 0.78rem; font-weight: 800; letter-spacing: 0.12em; text-transform: uppercase; }
        .body { display: grid; grid-template-columns: 180px 1fr; gap: 1.25rem; padding: 1.35rem; }
        @media (max-width: 640px) { .body { grid-template-columns: 1fr; text-align: center; } }
        .photo-wrap {
            aspect-ratio: 4/5; border-radius: 1rem; overflow: hidden;
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.08);
            display: grid; place-items: center;
        }
        .photo-wrap img { width: 100%; height: 100%; object-fit: cover; }
        .initials { font-size: 3rem; font-weight: 800; color: #64748b; }
        .details h2 { margin: 0; font-size: clamp(1.5rem, 3vw, 2.2rem); line-height: 1.1; }
        .meta { margin-top: 0.75rem; display: grid; gap: 0.45rem; font-size: 0.92rem; }
        .meta-row { display: flex; flex-wrap: wrap; gap: 0.35rem 0.75rem; }
        .meta .label { color: #94a3b8; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.06em; min-width: 3.5rem; }
        .punch-time { margin-top: 0.85rem; font-size: clamp(1.6rem, 4vw, 2.4rem); font-weight: 800; color: #fbbf24; font-variant-numeric: tabular-nums; }
        .idle { text-align: center; max-width: 28rem; padding: 1rem; }
        .idle h2 { margin: 0 0 0.35rem; font-size: 1.35rem; }
        .idle p { margin: 0; color: #94a3b8; line-height: 1.5; font-size: 0.92rem; }
        .recent-panel {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 1.25rem;
            display: flex; flex-direction: column;
            min-height: 18rem; overflow: hidden;
        }
        .recent-panel h3 {
            margin: 0; padding: 0.85rem 1rem;
            font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.08em;
            color: #94a3b8; border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .recent-list { flex: 1; overflow: auto; padding: 0.5rem; display: grid; gap: 0.45rem; }
        .snippet {
            display: grid; grid-template-columns: 40px 1fr auto; gap: 0.55rem; align-items: center;
            padding: 0.45rem 0.55rem; border-radius: 0.75rem;
            background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);
        }
        .snippet.active { border-color: rgba(251,191,36,0.45); background: rgba(251,191,36,0.08); }
        .snippet-thumb {
            width: 40px; height: 40px; border-radius: 0.55rem; overflow: hidden;
            background: rgba(255,255,255,0.06); display: grid; place-items: center;
            font-size: 0.75rem; font-weight: 800; color: #64748b;
        }
        .snippet-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .snippet-name { font-size: 0.82rem; font-weight: 700; line-height: 1.2; }
        .snippet-meta { font-size: 0.68rem; color: #94a3b8; margin-top: 0.1rem; }
        .snippet-state {
            font-size: 0.62rem; font-weight: 800; letter-spacing: 0.06em;
            padding: 0.2rem 0.45rem; border-radius: 999px; text-align: center;
        }
        .snippet-state.in { background: rgba(16,185,129,0.18); color: #6ee7b7; }
        .snippet-state.out { background: rgba(244,63,94,0.18); color: #fda4af; }
        .snippet-time { font-size: 0.68rem; color: #cbd5e1; font-variant-numeric: tabular-nums; margin-top: 0.15rem; text-align: right; }
        .empty-recent { padding: 1rem; color: #64748b; font-size: 0.85rem; text-align: center; }
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
            <div class="filters">
                <select id="filter-batch" aria-label="Filter by class">
                    <option value="">All classes</option>
                    @foreach ($batchOptions as $batch)
                        <option value="{{ $batch['id'] }}" @selected($initialBatchId === (int) $batch['id'])>{{ $batch['name'] }}</option>
                    @endforeach
                </select>
                <select id="filter-state" aria-label="Filter by punch type">
                    <option value="" @selected($initialState === null)>All punches</option>
                    <option value="IN" @selected($initialState === 'IN')>IN only</option>
                    <option value="OUT" @selected($initialState === 'OUT')>OUT only</option>
                </select>
            </div>
            <div class="clock">
                <div class="time" id="live-clock">--:--:--</div>
                <div class="date" id="live-date"></div>
            </div>
        </div>
    </header>

    <section class="stats-row" id="stats-row">
        <div class="stat present">
            <div class="label">Present today</div>
            <div class="value" id="stat-present">{{ $initialSummary['present_today'] ?? 0 }}</div>
        </div>
        <div class="stat inside">
            <div class="label">Inside now</div>
            <div class="value" id="stat-inside">{{ $initialSummary['inside_now'] ?? 0 }}</div>
        </div>
        <div class="stat out">
            <div class="label">Checked out</div>
            <div class="value" id="stat-out">{{ $initialSummary['checked_out'] ?? 0 }}</div>
        </div>
        <div class="stat absent">
            <div class="label">Absent</div>
            <div class="value" id="stat-absent">{{ $initialSummary['absent'] ?? 0 }}</div>
        </div>
    </section>

    <section class="batch-row" id="batch-row">
        @forelse ($initialSummary['by_batch'] ?? [] as $batch)
            <span class="batch-chip">
                <strong>{{ $batch['batch_name'] }}</strong>:
                <span class="in">{{ $batch['present'] }}/{{ $batch['total'] }} present</span>
                · {{ $batch['inside'] }} inside
            </span>
        @empty
            <span class="batch-chip">No active classes</span>
        @endforelse
    </section>

    <div class="content">
        <section class="hero">
            <div id="card-container"></div>
            <div class="idle" id="idle-screen">
                <h2>Waiting for attendance</h2>
                <p>Punch IN or OUT on the biometric device or mark manually in admin — the student card will appear here.</p>
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
    const filterBatch = document.getElementById('filter-batch');
    const filterState = document.getElementById('filter-state');
    let hideTimer = null;
    let activeSnippetId = null;

    function filterParams() {
        const params = new URLSearchParams();
        params.set('since', String(sinceId));
        if (filterBatch.value) params.set('batch_id', filterBatch.value);
        if (filterState.value) params.set('state', filterState.value);
        return params;
    }

    function syncUrlFilters() {
        const params = new URLSearchParams(window.location.search);
        if (filterBatch.value) params.set('batch_id', filterBatch.value); else params.delete('batch_id');
        if (filterState.value) params.set('state', filterState.value); else params.delete('state');
        const qs = params.toString();
        history.replaceState({}, '', qs ? `${window.location.pathname}?${qs}` : window.location.pathname);
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
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatTime(time) {
        if (!time) return '—';
        const parts = String(time).split(':');
        if (parts.length < 2) return time;
        const d = new Date();
        d.setHours(parseInt(parts[0], 10), parseInt(parts[1], 10), parseInt(parts[2] || '0', 10));
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: true });
    }

    function renderStats(summary) {
        if (!summary) return;
        document.getElementById('stat-present').textContent = summary.present_today ?? 0;
        document.getElementById('stat-inside').textContent = summary.inside_now ?? 0;
        document.getElementById('stat-out').textContent = summary.checked_out ?? 0;
        document.getElementById('stat-absent').textContent = summary.absent ?? 0;

        const batchRow = document.getElementById('batch-row');
        const batches = summary.by_batch || [];
        if (batches.length === 0) {
            batchRow.innerHTML = '<span class="batch-chip">No active classes</span>';
            return;
        }
        batchRow.innerHTML = batches.map(b => `
            <span class="batch-chip">
                <strong>${escapeHtml(b.batch_name)}</strong>:
                <span class="in">${b.present}/${b.total} present</span>
                · ${b.inside} inside
            </span>
        `).join('');
    }

    function renderRecent(recent) {
        if (!recent || recent.length === 0) {
            recentList.innerHTML = '<div class="empty-recent">No punches yet today.</div>';
            return;
        }
        recentList.innerHTML = recent.map(item => {
            const thumb = item.photo_url
                ? `<img src="${escapeHtml(item.photo_url)}" alt="">`
                : escapeHtml(item.initials || '?');
            const isActive = activeSnippetId === item.id ? ' active' : '';
            return `
                <div class="snippet${isActive}" data-id="${item.id}">
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
                            <div class="meta-row"><span class="label">Roll</span><span>${escapeHtml(punch.roll)}</span></div>
                            ${punch.batch ? `<div class="meta-row"><span class="label">Class</span><span>${escapeHtml(punch.batch)}</span></div>` : ''}
                            ${punch.course ? `<div class="meta-row"><span class="label">Course</span><span>${escapeHtml(punch.course)}</span></div>` : ''}
                            <div class="meta-row"><span class="label">Source</span><span>${escapeHtml(punch.source)}</span></div>
                        </div>
                        <div class="punch-time">${escapeHtml(formatTime(punch.time))}</div>
                    </div>
                </div>
            </article>`;

        idle.style.display = 'none';

        if (hideTimer) clearTimeout(hideTimer);
        hideTimer = setTimeout(() => {
            const card = container.querySelector('.card');
            if (card) card.classList.remove('visible');
            setTimeout(() => {
                container.innerHTML = '';
                idle.style.display = 'block';
                activeSnippetId = null;
                renderRecent(window.__lastRecent || []);
            }, 400);
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
            if (typeof data.max_id === 'number' && data.max_id > sinceId) {
                sinceId = data.max_id;
            }

            if (punches.length > 0) {
                renderCard(punches[punches.length - 1]);
            }
        } catch (e) {
            // Retry on next poll
        }
    }

    function onFilterChange() {
        syncUrlFilters();
        poll();
    }

    filterBatch.addEventListener('change', onFilterChange);
    filterState.addEventListener('change', onFilterChange);

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
