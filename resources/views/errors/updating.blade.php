<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0a1e24">
    <title>Updating | YCC Hub</title>
    {{-- Self-contained on purpose: shown while the database waits for an update, so it must not touch the database. --}}
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; }
        body {
            display: flex; align-items: center; justify-content: center;
            padding: 24px; background: #0a1e24; color: #d6e3de; text-align: center;
            font: 16px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }
        main { max-width: 22rem; }
        img { width: 96px; height: 96px; border-radius: 9999px; box-shadow: 0 0 0 6px rgba(217, 170, 46, .2); }
        h1 { margin: 24px 0 8px; color: #fff; font-size: 2rem; line-height: 1.1; letter-spacing: -.01em; }
        p { margin: 0 0 24px; color: #b9cec7; }
        button {
            min-height: 44px; padding: 10px 22px; border: 0; border-radius: 8px; cursor: pointer;
            background: #d9aa2e; color: #061418; font: 700 1rem system-ui, -apple-system, sans-serif;
        }
        button:active { transform: scale(.97); }
    </style>
</head>
<body>
    <main>
        <img src="{{ asset('images/ycc-crest-192.png') }}" alt="YCC crest">
        <h1>Back in a minute</h1>
        <p>YCC Hub is being updated with new features. This usually takes a minute or two. Your account and records are safe.</p>
        <button type="button" onclick="location.reload()">Try again</button>
        <p style="margin-top:20px;font-size:.85rem"><a href="{{ route('login') }}" style="color:#d9aa2e">MT login</a></p>
    </main>
</body>
</html>
