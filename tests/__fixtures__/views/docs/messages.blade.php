@if ($message = Statamic::tag('back_in_stock:success')->fetch())
    <p class="flash">{!! $message !!}</p>
@endif
@if ($error = Statamic::tag('back_in_stock:error')->fetch())
    <p class="flash">{!! $error !!}</p>
@endif
