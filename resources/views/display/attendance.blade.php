<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Attendance — {{ $instituteName }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body {
            margin: 0;
            min-height: 100vh;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: radial-gradient(circle at top, #1e293b 0%, #0f172a 45%, #020617 100%);
            color: #f8fafc;
        }
        .screen {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 1.5rem;
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 1rem;
            min-width: 0;
        }
        .brand img {
            height: 3rem;
            width: auto;
            border-radius: 0.75rem;
            object-fit: contain;
            background: rgba(255,255,255,0.08);
        }
        .brand h1 {
            margin: 0;
            font-size: clamp(1.1rem, 2vw, 1.6rem);
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        .brand p {
            margin: 0.15rem 0 0;
            font-size: 0.85rem;
            color: #94a3b8;
        }
        .clock {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }
        .clock .time {
            font-size: clamp(1.5rem, 3vw, 2.25rem);
            font-weight: 800;
        }
        .clock .date {
            color: #94a3b8;
            font-size: 0.95rem;
        }
        .main {
            flex: 1;
            display: grid;
            place-items: center;
        }
        .card {
            width: min(920px, 100%);
            border-radius: 1.75rem;
            overflow: hidden;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            box-shadow: 0 30px 80px rgba(0,0,0,0.35);
            opacity: 0;
            transform: translateY(12px) scale(0.98);
            transition: opacity 0.45s ease, transform 0.45s ease;
        }
        .card.visible {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
        .card.in .banner { background: linear-gradient(90deg, #059669, #10b981); }
        .card.out .banner { background: linear-gradient(90deg, #be123c, #f43f5e); }
        .banner {
            padding: 0.85rem 1.25rem;
            font-size: 0.85rem;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }
        .body {
            display: grid;
            grid-template-columns: 220px 1fr;
            gap: 1.5rem;
            padding: 1.75rem;
        }
        @media (max-width: 640px) {
            .body { grid-template-columns: 1fr; text-align: center; }
        }
        .photo-wrap {
            aspect-ratio: 4/5;
            border-radius: 1.25rem;
            overflow: hidden;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            display: grid;
            place-items: center;
        }
        .photo-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .initials {
            font-size: 4rem;
            font-weight: 800;
            color: #64748b;
        }
        .details h2 {
            margin: 0;
            font-size: clamp(1.8rem, 4vw, 2.8rem);
            line-height: 1.1;
            letter-spacing: -0.03em;
        }
        .meta {
            margin-top: 1rem;
            display: grid;
            gap: 0.65rem;
        }
        .meta-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem 1rem;
            align-items: baseline;
        }
        .label {
            color: #94a3b8;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            min-width: 5rem;
        }
        .value {
            font-size: 1.05rem;
            font-weight: 600;
        }
        .punch-time {
            margin-top: 1.25rem;
            font-size: clamp(2rem, 5vw, 3rem);
            font-weight: 800;
            font-variant-numeric: tabular-nums;
            color: #fbbf24;
        }
        .idle {
            text-align: center;
            max-width: 36rem;
            padding: 2rem;
        }
        .idle h2 {
            margin: 0 0 0.5rem;
            font-size: clamp(1.5rem, 3vw, 2rem);
        }
        .idle p {
            margin: 0;
            color: #94a3b8;
            line-height: 1.6;
        }
        .status-dot {
            display: inline-block;
            width: 0.55rem;
            height: 0.55rem;
            border-radius: 999px;
            background: #22c55e;
            margin-right: 0.35rem;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.35; }
        }
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
        <div class="clock">
            <div class="time" id="live-clock">--:--:--</div>
            <div class="date" id="live-date"></div>
        </div>
    </header>

    <main class="main">
        <div id="card-container"></div>
        <div class="idle" id="idle-screen">
            <h2>Waiting for attendance</h2>
            <p>Punch IN or OUT on the biometric device or mark manually in admin — the student card will appear here automatically.</p>
        </div>
    </main>
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
    let hideTimer = null;

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

    function renderCard(punch) {
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
                            ${punch.batch ? `<div class="meta-row"><span class="label">Batch</span><span class="value">${escapeHtml(punch.batch)}</span></div>` : ''}
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
            const card = container.querySelector('.card');
            if (card) card.classList.remove('visible');
            setTimeout(() => {
                container.innerHTML = '';
                idle.style.display = 'block';
            }, 450);
        }, cardMs);
    }

    function formatTime(time) {
        if (!time) return '—';
        const parts = String(time).split(':');
        if (parts.length < 2) return time;
        const d = new Date();
        d.setHours(parseInt(parts[0], 10), parseInt(parts[1], 10), parseInt(parts[2] || '0', 10));
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    }

    async function poll() {
        try {
            const response = await fetch(`${latestUrl}?since=${sinceId}`, {
                headers: { 'Accept': 'application/json' },
                cache: 'no-store',
            });

            if (!response.ok) return;

            const data = await response.json();
            const punches = data.punches || [];

            if (typeof data.max_id === 'number' && data.max_id > sinceId) {
                sinceId = data.max_id;
            }

            if (punches.length > 0) {
                renderCard(punches[punches.length - 1]);
            }
        } catch (e) {
            // Silent retry on next poll
        }
    }

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
