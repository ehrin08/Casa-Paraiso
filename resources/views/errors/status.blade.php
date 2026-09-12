<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="turbo-visit-control" content="reload">
        <meta name="robots" content="noindex,nofollow">
        <title>{{ $content['title'] }} · {{ config('app.name', 'Casa Paraiso') }}</title>
        <style>
            :root { color-scheme: light; font-family: Arial, sans-serif; background: #f5f0e7; color: #232620; }
            body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; box-sizing: border-box; }
            main { width: min(100%, 42rem); box-sizing: border-box; background: #fffcf7; border: 1px solid #dcd2c2; border-radius: 20px; padding: clamp(24px, 6vw, 48px); box-shadow: 0 14px 36px rgba(35, 38, 32, .10); text-align: center; }
            img { width: min(210px, 70%); height: auto; }
            p { line-height: 1.6; color: #67675f; }
            .eyebrow { color: #7a3e14; font-size: .78rem; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
            h1 { margin: 14px 0 0; font-size: clamp(1.7rem, 5vw, 2.5rem); line-height: 1.12; }
            .actions { display: flex; flex-wrap: wrap; justify-content: center; gap: 12px; margin-top: 28px; }
            a, button { min-height: 44px; border-radius: 10px; padding: 0 18px; border: 1px solid #4f6a4e; font: inherit; font-weight: 700; cursor: pointer; text-decoration: none; display: inline-grid; place-items: center; box-sizing: border-box; }
            a { background: #4f6a4e; color: #fff; }
            button { background: transparent; color: #334736; }
            .reference { margin-top: 28px; border-top: 1px solid #dcd2c2; padding-top: 18px; font-size: .82rem; }
            code { color: #334736; font-weight: 700; overflow-wrap: anywhere; }
        </style>
    </head>
    <body>
        <main>
            <img src="{{ asset('images/casa_paraiso_logo.jpg') }}" alt="{{ config('app.name', 'Casa Paraiso') }}">
            <p class="eyebrow">{{ $content['eyebrow'] }}</p>
            <h1>{{ $content['title'] }}</h1>
            <p>{{ $content['message'] }}</p>
            <div class="actions">
                <button type="button" onclick="history.back()">Go back</button>
                <a href="{{ url('/') }}">Return home</a>
            </div>
            <p class="reference">Reference: <code>{{ $reference }}</code><br>Share this code with the Casa Paraiso administrator if you need help.</p>
        </main>
    </body>
</html>
