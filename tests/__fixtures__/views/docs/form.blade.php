<s:back_in_stock :product="$id" class="notify">
    @if ($success)
        <p>{!! $success !!}</p>
    @else
        <h2>Tell me when it's back</h2>

        <label for="notify-email">Your email</label>
        <input type="email" id="notify-email" name="email" value="{!! $email !!}" autocomplete="email" required>
        @isset ($errors['email'])<p class="error">{!! $errors['email'] !!}</p>@endisset

        <label>
            <input type="checkbox" name="consent" value="1" @checked($consent_checked) required>
            {!! $consent_text !!}
        </label>
        @isset ($errors['consent'])<p class="error">{!! $errors['consent'] !!}</p>@endisset

        <button type="submit">Email me</button>
    @endif
</s:back_in_stock>
