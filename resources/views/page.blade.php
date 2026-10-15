{{--
    The page people land on after the links in the emails (confirm,
    unsubscribe), unless config('back-in-stock.pages') sends them to one of
    your own. Publish to edit: php artisan vendor:publish --tag=back-in-stock-views
--}}
<!doctype html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} – {{ $siteName }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f3f4f6; font-family: -apple-system, 'Segoe UI', Helvetica, Arial, sans-serif; color: #1f2937; }
        main { box-sizing: border-box; width: calc(100% - 32px); max-width: 460px; margin: 32px 16px; padding: 32px 28px; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; }
        h1 { margin: 0 0 12px; font-size: 22px; line-height: 1.3; color: #111827; }
        p { margin: 0 0 20px; font-size: 16px; line-height: 1.5; overflow-wrap: anywhere; }
        button { padding: 12px 18px; border: 0; border-radius: 6px; background: {{ $colour }}; color: {{ $onColour }}; font: inherit; font-weight: bold; cursor: pointer; }
        button:focus-visible, a:focus-visible { outline: 3px solid #2563eb; outline-offset: 2px; }
        a { color: #374151; }
        .back { margin: 0; font-size: 14px; }
    </style>
</head>
<body>
<main>
    <h1>{{ $title }}</h1>
    <p>{{ $message }}</p>
    @if($form)
        <form method="POST" action="{{ $form['action'] }}">
            <p><button type="submit">{{ $form['button'] }}</button></p>
        </form>
    @endif
    <p class="back"><a href="{{ $siteUrl }}">{{ __('back-in-stock::messages.page.back', ['site' => $siteName]) }}</a></p>
</main>
</body>
</html>
