<?php

namespace NineteenNinetyFour\BackInStock\Widgets;

use NineteenNinetyFour\BackInStock\AlertStatus;
use NineteenNinetyFour\BackInStock\Models\Item;
use Statamic\Facades\User;
use Statamic\Widgets\VueComponent;
use Statamic\Widgets\Widget;

/**
 * The dashboard's "Wanted while sold out" widget: the sold-out products and
 * variants with the most people waiting. Add it to config/statamic/cp.php:
 *
 *     'widgets' => [
 *         ['type' => 'back_in_stock', 'width' => 50],
 *     ],
 *
 * Options: title, limit (5).
 */
class BackInStockWidget extends Widget
{
    protected static $handle = 'back_in_stock';

    public function component()
    {
        if (! User::current()?->can('view back in stock')) {
            return;
        }

        $limit = max(1, (int) $this->config('limit', 5));

        $rows = Item::query()
            ->withCount(['alerts as waiting_count' => fn ($q) => $q->where('status', AlertStatus::Waiting->value)])
            ->whereHas('waiting')
            ->orderByDesc('waiting_count')
            ->limit($limit * 3)
            ->get()
            ->map(fn (Item $item) => [$item, $item->product()])
            // Sold out right now; anything back in stock is being emailed.
            ->filter(fn (array $pair) => $pair[1] && $pair[1]->isSoldOut())
            ->take($limit)
            ->map(fn (array $pair) => [
                'id' => $pair[0]->id,
                'name' => $pair[1]->name(),
                'waiting' => (int) $pair[0]->waiting_count,
                'url' => cp_route('back-in-stock.show', $pair[0]->id),
            ])
            ->values()
            ->all();

        return VueComponent::render('back-in-stock-widget', [
            'title' => $this->config('title', __('back-in-stock::messages.cp.widget')),
            'rows' => $rows,
            'url' => cp_route('back-in-stock.index'),
        ]);
    }
}
