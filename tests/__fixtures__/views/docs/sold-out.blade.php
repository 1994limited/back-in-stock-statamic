@if (Statamic::tag('back_in_stock:sold_out')->param('product', $id)->fetch())
    <span class="tag">Sold out</span>
    <span>{{ Statamic::tag('back_in_stock:count')->param('product', $id)->fetch() }} waiting</span>
@endif
