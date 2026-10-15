# Templates

Back in Stock gives you tags, not a theme: the markup and styles are yours. The form works without JavaScript, posting to `/!/back-in-stock/subscribe` and coming back to the page with a message. One optional script makes a variant product's form follow the variant chosen on the page.

The examples are from [the demo shop](demo.md), trimmed down.

> **Escaping.** The `{{ back_in_stock }}` tags escape everything they give you, including product titles and anything shoppers typed.

## Antlers or Blade

Every example below is Antlers, with the same thing in Blade under **Blade**: click it to open. Inside a tag pair, the tag's variables are Blade variables (`$email`, `$consent_text`, `$errors`…). The tags escape what they give you (Antlers doesn't), so print their values with `{!! !!}`: with `{{ }}` they'd be escaped twice, and an apostrophe would show as `&#039;`. Your own values still go in `{{ }}` as usual.

## The form

On a product's page (the entry is in context, or pass `product="…"`). It shows only while the product is sold out, so you can leave it in the template:

```antlers
{{ back_in_stock class="notify" }}
    {{ if success }}
        <p>{{ success }}</p>
    {{ else }}
        <h2>Tell me when it's back</h2>

        <label for="notify-email">Your email</label>
        <input type="email" id="notify-email" name="email" value="{{ email }}" autocomplete="email" required>
        {{ if errors:email }}<p class="error">{{ errors:email }}</p>{{ /if }}

        <label>
            <input type="checkbox" name="consent" value="1" {{ if consent_checked }}checked{{ /if }} required>
            {{ consent_text }}
        </label>
        {{ if errors:consent }}<p class="error">{{ errors:consent }}</p>{{ /if }}

        <button type="submit">Email me</button>
    {{ /if }}
{{ /back_in_stock }}
```

<details>
<summary>Blade</summary>

```blade
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
```

</details>

What happens:

1. The shopper enters their email, ticks the box and sends. The tick box is required: the request keeps the wording they agreed to (`{{ consent_text }}`, set in the [settings](configuration.md)).
2. They're emailed a link. The link opens a page with a "Yes, email me" button. Only the button confirms, so mail apps and virus checkers that open links in emails can't confirm for them. Nobody can sign someone else up.
3. When it's back in stock, they get one email. See [Restocks](restocks.md).

The answer is the same whether or not that address had asked before, so the form can't be used to find out who's waiting for what.

**Signed in?** `{{ email }}` is filled in with the customer's email, and `{{ signed_in }}` is true. They still confirm by email. A signed-in customer can leave the email field out altogether: their account's email is used.

**Spam.** The form has a hidden field that people can't see (named `website`; see [Configuration](configuration.md)). A form that fills it in is thrown away, and told it worked. Each visitor can send 10 requests a minute, each address gets at most 5 confirmation emails an hour, and an address can have 50 open requests.

**Where it goes after.** Back to the page it was on, with the message in `{{ success }}`. To go elsewhere, add `redirect="/thanks"` (and `error_redirect="…"` for errors). Only addresses on your own site are followed.

**Several forms on a page** (a shelf of products, say): each shows its own message and errors, and only its own.

## Variants

On a product with variants, give the variant: `{{ back_in_stock variant="Hardback" }}`. Or leave it out, and `{{ variants }}` lists the sold-out ones to choose from (the form shows while any variant is sold out).

Most product pages already have a variant choice, in the basket form. Add `follow="variant"` and `{{ back_in_stock:scripts }}`, and the form follows it: it asks about the variant chosen there, and shows only while that one is sold out. Mark the form's own choice with `data-back-in-stock-choose`: it's hidden when the script runs, and used without JavaScript.

```antlers
{{ back_in_stock follow="variant" }}
    {{ if success }}
        <p>{{ success }}</p>
    {{ else }}
        {{ if has_variants }}
            <label data-back-in-stock-choose>
                Which edition?
                <select name="variant">
                    {{ variants }}
                        <option value="{{ key }}" {{ if selected }}selected{{ /if }}>{{ name }}</option>
                    {{ /variants }}
                </select>
            </label>
        {{ /if }}

        <input type="email" name="email" value="{{ email }}" aria-label="Your email" required>
        <label><input type="checkbox" name="consent" value="1" required> {{ consent_text }}</label>
        <button type="submit">Email me</button>
    {{ /if }}
{{ /back_in_stock }}

{{ back_in_stock:scripts }}
```

<details>
<summary>Blade</summary>

```blade
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
```

</details>

The page's variant choice is the radio buttons (or select) named `variant` outside the form: Cargo's `{{ cart:add }}` form uses that name. `follow="edition"` follows another name. For shoppers to choose a sold-out variant there, don't disable its radio button: the demo marks it `data-sold-out` instead, and its own script turns the basket button into "Sold out".

The script is small, has no dependencies, and is the same for everyone, so it's fine on statically cached pages.

## Sold out, and how many are waiting

```antlers
{{ if {back_in_stock:sold_out} }}
    <span class="tag">Sold out</span>
    <span>{{ back_in_stock:count }} waiting</span>
{{ /if }}
```

<details>
<summary>Blade</summary>

```blade
@if (Statamic::tag('back_in_stock:sold_out')->param('product', $id)->fetch())
    <span class="tag">Sold out</span>
    <span>{{ Statamic::tag('back_in_stock:count')->param('product', $id)->fetch() }} waiting</span>
@endif
```

</details>

`{{ back_in_stock:sold_out }}` takes `product="…"` and `variant="…"` like the form. On a product with variants and no variant given, it's true when any of them is sold out. `{{ back_in_stock:count }}` counts confirmed requests. Inside the form, `{{ waiting }}` is the same number.

## Messages

After confirming or unsubscribing, people land on the addon's own page, or on one of yours (see `pages` in [Configuration](configuration.md)), with the message in `{{ back_in_stock:success }}`. Put it in your layout:

```antlers
{{ if {back_in_stock:success} }}
    <p class="flash">{{ back_in_stock:success }}</p>
{{ /if }}
{{ if {back_in_stock:error} }}
    <p class="flash">{{ back_in_stock:error }}</p>
{{ /if }}
```

<details>
<summary>Blade</summary>

```blade
@if ($message = Statamic::tag('back_in_stock:success')->fetch())
    <p class="flash">{!! $message !!}</p>
@endif
@if ($error = Statamic::tag('back_in_stock:error')->fetch())
    <p class="flash">{!! $error !!}</p>
@endif
```

</details>

## A customer's alerts

For a signed-in customer's account page: what they've asked about, and a button to cancel each.

```antlers
<ul>
    {{ back_in_stock:alerts }}
        <li>
            <a href="{{ url }}">{{ name }}</a> ({{ status_label }})
            {{ if is_unconfirmed }}
                Check your inbox to confirm.
            {{ /if }}
            {{ back_in_stock:cancel :alert="id" redirect="/alerts" }}
                <button type="submit">Cancel</button>
            {{ /back_in_stock:cancel }}
        </li>
    {{ /back_in_stock:alerts }}
</ul>
```

<details>
<summary>Blade</summary>

```blade
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
```

</details>

Requests are matched to the customer by their account, or by their email. `{{ if {back_in_stock:subscribed} }}` says whether they've asked about this product (or `variant="…"`).

## Static caching

The form works on statically cached pages:

- Statamic swaps in a fresh CSRF token on cached pages by itself (with its JavaScript, on the full measure).
- Nothing personal is put in the page. On a statically cached site the tags leave out the signed-in customer's email, `{{ back_in_stock:alerts }}` and `{{ back_in_stock:subscribed }}`, as a cached page would show them to everyone. To show them, put the tag inside `{{ nocache }}` and add `personal="true"`.
- The message after sending is in the session, so it shows only if the page isn't served from the cache. Wrap the form in `{{ nocache }}`, send it to an uncached page with `redirect="…"`, or send it with `fetch()` (see the [HTTP API](http-api.md)).

```antlers
{{ nocache }}
    {{ back_in_stock personal="true" }} … {{ /back_in_stock }}
{{ /nocache }}
```

## Sending it with JavaScript

The form answers JSON when asked (`Accept: application/json`), so you can send it without leaving the page. See the [HTTP API](http-api.md).
