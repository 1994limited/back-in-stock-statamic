<ul>
    <s:back_in_stock:alerts>
        <li>
            <a href="{{ $url }}">{!! $name !!}</a> ({!! $status_label !!})
            @if ($is_unconfirmed)
                Check your inbox to confirm.
            @endif
            <s:back_in_stock:cancel :alert="$id" redirect="/alerts">
                <button type="submit">Cancel</button>
            </s:back_in_stock:cancel>
        </li>
    </s:back_in_stock:alerts>
</ul>
