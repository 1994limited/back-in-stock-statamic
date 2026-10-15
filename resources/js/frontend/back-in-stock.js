/*
 * Back in Stock's optional script, added with {{ back_in_stock:scripts }}.
 *
 * A form with data-back-in-stock-follow (the tag's follow="variant") follows
 * the page's own variant choice: radio buttons, or a select, with that name
 * outside the form (the basket form's, say). It asks about the variant chosen
 * there, and shows only while that variant is sold out. Anything inside it
 * marked data-back-in-stock-choose (the form's own choice, for browsers
 * without JavaScript) is hidden.
 */
(function () {
    function setup(form) {
        var name = form.getAttribute('data-back-in-stock-follow') || 'variant';
        var soldOut = [];
        var posted = form.getAttribute('data-back-in-stock-posted');

        try {
            soldOut = JSON.parse(form.getAttribute('data-back-in-stock-sold-out') || '[]');
        } catch (e) {}

        var field = form.querySelector('[name="variant"]');

        if (!field || field.tagName === 'SELECT') {
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'variant';
            if (field) field.removeAttribute('name');
            form.appendChild(hidden);
            field = hidden;
        }

        form.querySelectorAll('[data-back-in-stock-choose]').forEach(function (el) {
            el.hidden = true;
        });

        function choices() {
            return Array.prototype.filter.call(document.getElementsByName(name), function (el) {
                return !form.contains(el);
            });
        }

        function chosen() {
            var value = null;

            choices().forEach(function (el) {
                if (el.type === 'radio' || el.type === 'checkbox') {
                    if (el.checked) value = el.value;
                } else {
                    value = el.value;
                }
            });

            return value;
        }

        function update() {
            var value = chosen();
            var out = value !== null && soldOut.indexOf(value) !== -1;

            form.hidden = !out;
            if (out) field.value = value;
        }

        // After posting, show the answer for the variant asked about.
        if (posted) {
            choices().forEach(function (el) {
                if (el.value !== posted) return;
                if (el.type === 'radio' || el.type === 'checkbox') el.checked = true;
                else el.value = posted;
                // So the page's own scripts see the choice too.
                el.dispatchEvent(new Event('change', { bubbles: true }));
            });
        }

        document.addEventListener('change', function (event) {
            if (event.target.name === name && !form.contains(event.target)) update();
        });

        update();
    }

    function start() {
        document.querySelectorAll('form[data-back-in-stock-follow]').forEach(setup);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
