{{--
    The frame both emails share. Tables and inline styles, so it looks
    right in every mail app. Publish to edit: php artisan vendor:publish --tag=back-in-stock-views
--}}
<!doctype html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $heading }}</title>
</head>
<body style="margin:0; padding:0; background:#f3f4f6; font-family:-apple-system, 'Segoe UI', Helvetica, Arial, sans-serif; color:#1f2937;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;">
    <tr>
        <td align="center" style="padding:24px 12px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e5e7eb;">
                <tr>
                    <td style="background:{{ $colour }}; padding:20px 28px; color:{{ $onColour }};">
                        @if($logo)
                            <img src="{{ $logo }}" alt="{{ $siteName }}" style="max-height:44px; max-width:180px; display:block; border:0;">
                        @else
                            <strong style="font-size:17px;">{{ $siteName }}</strong>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px;">
                        @yield('body')
                    </td>
                </tr>
            </table>
            @yield('after')
            <p style="margin:16px 0 0; font-size:12px; color:#9ca3af;"><a href="{{ $siteUrl }}" style="color:#6b7280;">{{ $siteName }}</a></p>
        </td>
    </tr>
</table>
</body>
</html>
