{{ $heading }}

{{ $intro }}

{{ $productName }}@if($price) – {{ $price }}@endif


{{ $button }}: {!! $productUrl !!}

{{ $footer }}

{{ __('back-in-stock::messages.email.unsubscribe') }}: {!! $unsubscribeUrl !!}

{{ $siteName }}
