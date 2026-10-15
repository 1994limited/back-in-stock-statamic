{{--
    "Confirm your back-in-stock alert". Publish to edit: php artisan vendor:publish --tag=back-in-stock-views
--}}
@extends('back-in-stock::emails.layout')

@section('body')
    <h1 style="margin:0 0 12px; font-size:22px; line-height:1.3; color:#111827;">{{ $heading }}</h1>
    <p style="margin:0 0 24px; font-size:16px; line-height:1.5;">{{ $intro }}</p>

    <table role="presentation" cellpadding="0" cellspacing="0">
        <tr>
            <td style="border-radius:6px; background:{{ $colour }};">
                <a href="{{ $confirmUrl }}" style="display:inline-block; padding:12px 18px; color:{{ $onColour }}; font-size:15px; font-weight:bold; text-decoration:none;">{{ $button }}</a>
            </td>
        </tr>
    </table>

    <p style="margin:24px 0 0; font-size:13px; line-height:1.5; color:#6b7280;">{{ __('back-in-stock::messages.email.link_expires', ['hours' => $hours]) }} {{ $ignore }}</p>
@endsection
