<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    @isset($description)<meta name="description" content="{{ $description }}">@endisset
    <link rel="canonical" href="{{ $canonical }}">
    @isset($markdown)<link rel="alternate" type="text/markdown" href="{{ $markdown }}">@endisset
    @isset($jsonLd)<script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>@endisset
    <style>
        :root{--bg:#fff;--fg:#14161a;--muted:#5a6070;--line:#e6e7e9;--soft:#f5f6f7;--accent:#14161a}
        @media (prefers-color-scheme:dark){:root{--bg:#121316;--fg:#ececee;--muted:#a0a5b1;--line:#2a2c31;--soft:#1b1d21;--accent:#ececee}}
        *{box-sizing:border-box}
        body{margin:0;background:var(--bg);color:var(--fg);font:16px/1.65 system-ui,-apple-system,"Segoe UI",sans-serif}
        a{color:inherit}
        header{border-bottom:1px solid var(--line)}
        .bar{max-width:1080px;margin:0 auto;padding:14px 16px;display:flex;gap:12px;align-items:center;justify-content:space-between}
        .brand{font-weight:700;text-decoration:none}
        .wrap{max-width:1080px;margin:0 auto;padding:24px 16px 64px;display:grid;grid-template-columns:220px minmax(0,1fr);gap:40px}
        @media (max-width:760px){.wrap{grid-template-columns:1fr;gap:16px}nav.side{order:2;border-top:1px solid var(--line);padding-top:16px}}
        nav.side ul{list-style:none;margin:0;padding:0}
        nav.side li a{display:block;padding:5px 8px;border-radius:6px;text-decoration:none;color:var(--muted);font-size:14px}
        nav.side li a:hover,nav.side li a[aria-current]{background:var(--soft);color:var(--fg)}
        article{min-width:0;max-width:72ch}
        h1{font-size:2rem;line-height:1.2;margin:0 0 8px}
        .lede{color:var(--muted);margin:0 0 24px}
        article h2{margin-top:2em;font-size:1.3rem}
        article h3{margin-top:1.6em;font-size:1.1rem}
        code{font:.88em ui-monospace,SFMono-Regular,Menlo,monospace;background:var(--soft);padding:.1em .35em;border-radius:4px}
        pre{background:var(--soft);border:1px solid var(--line);border-radius:8px;padding:12px 14px;overflow-x:auto}
        pre code{background:none;padding:0}
        table{border-collapse:collapse;display:block;overflow-x:auto}
        th,td{border:1px solid var(--line);padding:6px 10px;text-align:left}
        details{border:1px solid var(--line);border-radius:10px;padding:10px 14px;margin:10px 0}
        summary{cursor:pointer;font-weight:600}
        .cards{list-style:none;padding:0;margin:0;display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px}
        .cards a{display:block;height:100%;border:1px solid var(--line);border-radius:12px;padding:14px 16px;text-decoration:none}
        .cards a:hover{border-color:var(--fg)}
        .cards b{display:block;margin-bottom:4px}
        .cards span{color:var(--muted);font-size:14px}
        .find{display:flex;gap:8px;margin:0 0 28px}
        .find input{flex:1;min-width:0;font:inherit;padding:10px 14px;border:1px solid var(--line);border-radius:10px;background:var(--bg);color:var(--fg)}
        .find input:focus{outline:2px solid var(--accent);outline-offset:1px}
        .find button{font:600 15px system-ui,sans-serif;padding:0 18px;border:0;border-radius:10px;background:var(--accent);color:var(--bg);cursor:pointer}
        .results{font-size:1rem;margin:0 0 12px;color:var(--muted)}
        .hit{display:block;border:1px solid var(--line);border-radius:12px;padding:12px 16px;margin:0 0 10px;text-decoration:none}
        .hit:hover{border-color:var(--fg)}
        .hit b{display:block;margin-bottom:4px}
        .hit span{color:var(--muted);font-size:14px}
        .meta{color:var(--muted);font-size:13px;margin-top:40px;border-top:1px solid var(--line);padding-top:12px}
    </style>
</head>
<body>
    <header><div class="bar"><a class="brand" href="{{ route('support.public.home') }}">{{ $app }} help</a><a href="{{ url('/') }}" style="font-size:14px;color:var(--muted)">{{ $app }} ↗</a></div></header>
    <div class="wrap">
        @yield('content')
    </div>
</body>
</html>
