<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Migrated Application</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, sans-serif; background: #0f172a; color: #e2e8f0; }
        .wrap { max-width: 960px; margin: 0 auto; padding: 4rem 1.5rem; }
        h1 { font-size: 2rem; font-weight: 700; letter-spacing: -0.02em; margin: 0 0 .25rem; }
        p.sub { color: #94a3b8; margin: 0 0 2rem; }
        .db { display: inline-block; font-size: .8rem; background: #1e293b; border: 1px solid #334155; color: #a5b4fc; padding: .35rem .6rem; border-radius: .5rem; margin-bottom: 2rem; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: .75rem; padding: 1.25rem; }
        .card h2 { margin: 0 0 .75rem; font-size: 1.1rem; color: #f1f5f9; }
        .stat { display: flex; justify-content: space-between; font-size: .85rem; color: #cbd5e1; padding: .15rem 0; }
        .stat span:last-child { color: #34d399; font-variant-numeric: tabular-nums; }
        footer { margin-top: 2.5rem; color: #64748b; font-size: .8rem; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>Migrated Application</h1>
        <p class="sub">Generated from the legacy Microsoft estate and deployed to the destination stack.</p>
        <div class="db">Database: {{ $database }}</div>
        <div class="grid">
            @foreach ($modules as $name => $counts)
                <div class="card">
                    <h2>{{ $name }}</h2>
                    <div class="stat"><span>PHP classes</span><span>{{ $counts['php'] }}</span></div>
                    <div class="stat"><span>Vue pages</span><span>{{ $counts['vue'] }}</span></div>
                </div>
            @endforeach
        </div>
        <footer>Served by bcmtol-webserver-dest · backed by postgres-dest</footer>
    </div>
</body>
</html>