<s:back_in_stock :product="$id" follow="variant">
    @if ($success)
        <p>{!! $success !!}</p>
    @else
        @if ($has_variants)
            <label data-back-in-stock-choose>
                Which edition?
                <select name="variant">
                    @foreach ($variants as $variant)
                        <option value="{!! $variant['key'] !!}" @selected($variant['selected'])>{!! $variant['name'] !!}</option>
                    @endforeach
                </select>
            </label>
        @endif

        <input type="email" name="email" value="{!! $email !!}" aria-label="Your email" required>
        <label><input type="checkbox" name="consent" value="1" required> {!! $consent_text !!}</label>
        <button type="submit">Email me</button>
    @endif
</s:back_in_stock>

<s:back_in_stock:scripts />
