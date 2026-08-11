<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $homework->title }} — {{ $instituteName }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, Segoe UI, sans-serif; background: #f8fafc; color: #0f172a; }
        .wrap { max-width: 52rem; margin: 0 auto; padding: 1.25rem 1rem 2.5rem; }
        .brand { font-size: 0.8rem; font-weight: 600; color: #64748b; letter-spacing: 0.02em; text-transform: uppercase; }
        h1 { margin: 0.35rem 0 0.25rem; font-size: 1.5rem; line-height: 1.25; }
        .meta { margin: 0; color: #64748b; font-size: 0.9rem; }
        .card { margin-top: 1rem; background: #fff; border: 1px solid #e2e8f0; border-radius: 1rem; padding: 1rem 1.1rem; }
        .desc { white-space: pre-wrap; line-height: 1.55; color: #1e293b; font-size: 0.95rem; }
        .row { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem; }
        .label { margin: 0; font-size: 0.9rem; font-weight: 600; }
        .actions { display: flex; flex-wrap: wrap; gap: 0.5rem; }
        a.btn { display: inline-flex; align-items: center; justify-content: center; text-decoration: none; border-radius: 0.75rem; padding: 0.65rem 1rem; font-size: 0.9rem; font-weight: 600; }
        a.btn-primary { background: #ea580c; color: #fff; }
        a.btn-secondary { background: #fff; color: #0f172a; border: 1px solid #cbd5e1; }
        iframe, img { width: 100%; margin-top: 0.9rem; border-radius: 0.75rem; border: 1px solid #e2e8f0; background: #f1f5f9; }
        iframe { height: 65vh; }
        img { max-height: 70vh; object-fit: contain; display: block; }
        .hint { margin: 0.75rem 0 0; font-size: 0.8rem; color: #64748b; text-align: center; }
        .hint a { color: #c2410c; font-weight: 600; }
    </style>
</head>
<body>
    <div class="wrap">
        <p class="brand">{{ $instituteName }}</p>
        <h1>{{ $homework->title }}</h1>
        <p class="meta">
            {{ $homework->batch?->name }}
            @if ($homework->published_at)
                · {{ $homework->published_at->format('d M Y') }}
            @endif
        </p>

        @if (filled($homework->description))
            <section class="card">
                <div class="desc">{{ $homework->description }}</div>
            </section>
        @endif

        @if ($homework->hasFile() && $homework->isPreviewable())
            @php
                $viewUrl = $homework->publicViewUrl();
                $downloadUrl = $homework->publicDownloadUrl();
                $isPdf = $homework->content_type === \App\Enums\HomeworkContentType::Pdf;
            @endphp
            <section class="card">
                <div class="row">
                    <p class="label">Attachment ({{ $homework->content_type->label() }})</p>
                    <div class="actions">
                        <a class="btn btn-secondary" href="{{ $viewUrl }}" target="_blank" rel="noopener">Open</a>
                        <a class="btn btn-primary" href="{{ $downloadUrl }}">Download</a>
                    </div>
                </div>

                @if ($isPdf)
                    <iframe src="{{ $viewUrl }}" title="{{ $homework->title }}"></iframe>
                    <p class="hint">PDF not showing? <a href="{{ $viewUrl }}" target="_blank" rel="noopener">Open it here</a></p>
                @else
                    <a href="{{ $viewUrl }}" target="_blank" rel="noopener">
                        <img src="{{ $viewUrl }}" alt="{{ $homework->title }}">
                    </a>
                @endif
            </section>
        @elseif ($homework->hasFile())
            <section class="card">
                <a class="btn btn-primary" href="{{ $homework->publicDownloadUrl() }}">Download attachment</a>
            </section>
        @endif
    </div>
</body>
</html>
