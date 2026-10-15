{{--
    "It's back in stock". Publish to edit: php artisan vendor:publish --tag=back-in-stock-views
    $product is the product (or variant): $product->entry is Cargo's product entry,
    so its other fields (an image, say) are $product->entry->value('handle').
--}}
@extends('back-in-stock::emails.layout')

@section('body')
    <h1 style="margin:0 0 12px; font-size:22px; line-height:1.3; color:#111827;">{{ $heading }}</h1>
    <p style="margin:0 0 20px; font-size:16px; line-height:1.5;">{{ $intro }}</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px; border:1px solid #e5e7eb; border-radius:8px;">
        <tr>
            <td style="padding:14px 16px;">
                <p style="margin:0; font-size:17px; font-weight:bold; color:#111827;">{{ $product->title() }}</p>
                @if($product->variantName())
                    <p style="margin:2px 0 0; font-size:15px; color:#4b5563;">{{ $product->variantName() }}</p>
                @endif
            </td>
            @if($price)
                <td align="right" style="padding:14px 16px; font-size:17px; font-weight:bold; white-space:nowrap; color:#111827;">{{ $price }}</td>
            @endif
        </tr>
    </table>

    <table role="presentation" cellpadding="0" cellspacing="0">
        <tr>
            <td style="border-radius:6px; background:{{ $colour }};">
                <a href="{{ $productUrl }}" style="display:inline-block; padding:12px 18px; color:{{ $onColour }}; font-size:15px; font-weight:bold; text-decoration:none;">{{ $button }}</a>
            </td>
        </tr>
    </table>

    <p style="margin:24px 0 0; font-size:13px; line-height:1.5; color:#6b7280;">{{ $footer }}</p>
@endsection

@section('after')
    <p style="margin:16px 0 0; font-size:12px; color:#9ca3af;"><a href="{{ $unsubscribeUrl }}" style="color:#6b7280;">{{ __('back-in-stock::messages.email.unsubscribe') }}</a></p>
@endsection
