<s:back_in_stock :product="$id">
    @if ($success)
        <p>{!! $success !!}</p>
    @else
        <input type="email" name="email" value="{!! $email !!}" placeholder="Your email" required>
        <label><input type="checkbox" name="consent" value="1" required> {!! $consent_text !!}</label>
        <button>Email me when it's back</button>
    @endif
</s:back_in_stock>
