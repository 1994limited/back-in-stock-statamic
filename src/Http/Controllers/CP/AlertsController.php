<?php

namespace NineteenNinetyFour\BackInStock\Http\Controllers\CP;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use NineteenNinetyFour\BackInStock\AlertStatus;
use NineteenNinetyFour\BackInStock\BackInStock;
use NineteenNinetyFour\BackInStock\Models\Alert;
use NineteenNinetyFour\BackInStock\Models\Item;
use NineteenNinetyFour\BackInStock\Models\Restock;
use NineteenNinetyFour\BackInStock\Settings;
use Statamic\Facades\Addon;
use Statamic\Facades\CP\Toast;
use Statamic\Facades\Entry;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Store → Back in Stock: what people are waiting for (the demand for each
 * sold-out product), who's waiting, and what happened when it came back.
 */
class AlertsController extends CpController
{
    public function __construct(Request $request, protected BackInStock $backInStock)
    {
        parent::__construct($request);
    }

    public function index(Request $request): Response
    {
        $this->authorize('view back in stock');

        $search = $this->search($request);
        $show = $request->query('show') === 'all' ? 'all' : 'waiting';

        $paginator = $this->itemsQuery($search, $show)->paginate(25)->withQueryString();

        return Inertia::render('back-in-stock::Index', [
            'search' => $search,
            'show' => $show,
            'items' => collect($paginator->items())->map(fn (Item $item) => $this->itemRow($item))->all(),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
            'stats' => $this->stats(),
            'canExport' => User::current()->can('export back in stock'),
            'urls' => [
                'index' => cp_route('back-in-stock.index'),
                'export' => cp_route('back-in-stock.export'),
                'person' => cp_route('back-in-stock.person'),
                'settings' => $this->settingsUrl(),
            ],
        ]);
    }

    public function show(Request $request, Item $item): Response
    {
        $this->authorize('view back in stock');

        $search = $this->search($request);
        $status = AlertStatus::tryFrom((string) $request->query('status'));

        $alerts = $item->alerts()
            ->when($search, fn ($query) => $query->where('email', 'like', '%'.$this->escapeLike(mb_strtolower($search)).'%'))
            ->when($status, fn ($query) => $query->where('status', $status->value))
            ->orderByRaw("case status when 'waiting' then 0 when 'sending' then 1 when 'unconfirmed' then 2 else 3 end")
            ->orderBy('created_at')
            ->paginate(50)
            ->withQueryString();

        $product = $item->product();

        return Inertia::render('back-in-stock::Show', [
            'item' => $this->itemRow($item->loadCount($this->counts())),
            'inStock' => $product !== null && $product->isAvailable() && $product->isInStock(),
            'search' => $search,
            'status' => $status?->value,
            'statuses' => $this->statuses(),
            'alerts' => collect($alerts->items())->map(fn (Alert $alert) => $this->alertRow($alert))->all(),
            'pagination' => [
                'page' => $alerts->currentPage(),
                'last_page' => $alerts->lastPage(),
                'total' => $alerts->total(),
            ],
            'restocks' => $item->restocks()->latest('id')->limit(20)->get()->map(fn (Restock $restock) => [
                'id' => $restock->id,
                'before' => $restock->stock_before,
                'after' => $restock->stock_after,
                'sent' => $restock->sent,
                'trigger' => $restock->trigger,
                'at' => $restock->created_at?->toDayDateTimeString(),
                'ago' => $restock->created_at?->diffForHumans(),
            ])->all(),
            'limit' => Settings::limit(),
            'canManage' => User::current()->can('manage back in stock'),
            'canExport' => User::current()->can('export back in stock'),
            'urls' => [
                'index' => cp_route('back-in-stock.index'),
                'self' => cp_route('back-in-stock.show', $item->id),
                'send' => cp_route('back-in-stock.send', $item->id),
                'export' => cp_route('back-in-stock.export', ['item' => $item->id]),
                'person' => cp_route('back-in-stock.person'),
            ],
        ]);
    }

    /**
     * Email everyone waiting now (it's in stock, and nobody wants to wait
     * for the delay).
     */
    public function send(Item $item): RedirectResponse|JsonResponse
    {
        $this->authorize('manage back in stock');

        $product = $item->product();

        if (! $product || ! $product->isAvailable() || $product->isSoldOut()) {
            Toast::error(__('It’s not in stock (or not published), so nobody was emailed.'));

            return back();
        }

        $sent = $this->backInStock->sendNow($item);
        $left = $item->waiting()->count();

        Toast::success($left > 0
            ? __(':sent emailed. The rest are being sent, a few a minute.', ['sent' => $sent])
            : trans_choice('{0} Nobody was waiting.|{1} 1 person emailed.|[2,*] :count people emailed.', $sent));

        return back();
    }

    public function cancel(Alert $alert): RedirectResponse
    {
        $this->authorize('manage back in stock');

        $this->backInStock->cancel($alert)
            ? Toast::success(__('Request cancelled.'))
            : Toast::info(__('That request had already finished.'));

        return back();
    }

    public function destroy(Alert $alert): RedirectResponse
    {
        $this->authorize('manage back in stock');

        $alert->delete();

        Toast::success(__('Request deleted.'));

        return back();
    }

    /**
     * Everything kept about one email address: to answer someone asking
     * what's held about them, or to delete it.
     */
    public function person(Request $request): Response
    {
        $this->authorize('view back in stock');

        $email = $this->email($request);

        return Inertia::render('back-in-stock::Person', [
            'email' => $email,
            'alerts' => $email ? Alert::query()->forEmail($email)->latest()->get()->map(fn (Alert $alert) => $this->alertRow($alert, withProduct: true))->all() : [],
            'canManage' => User::current()->can('manage back in stock'),
            'canExport' => User::current()->can('export back in stock'),
            'urls' => [
                'index' => cp_route('back-in-stock.index'),
                'self' => cp_route('back-in-stock.person'),
                'download' => $email ? cp_route('back-in-stock.person.download', ['email' => $email]) : null,
                'forget' => cp_route('back-in-stock.person.forget'),
            ],
        ]);
    }

    public function download(Request $request): JsonResponse
    {
        $this->authorize('export back in stock');

        $email = $this->email($request) ?? abort(404);

        return response()->json(['email' => $email, 'requests' => $this->backInStock->export($email)], 200, [
            'Content-Disposition' => 'attachment; filename="back-in-stock-'.now()->format('Y-m-d').'.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function forget(Request $request): RedirectResponse
    {
        $this->authorize('manage back in stock');

        $email = $this->email($request) ?? abort(422);
        $deleted = $this->backInStock->forget($email);

        Toast::success(trans_choice('{0} Nothing was kept for that address.|{1} 1 request deleted.|[2,*] :count requests deleted.', $deleted));

        return redirect(cp_route('back-in-stock.person'));
    }

    /**
     * Requests as a spreadsheet (CSV): all of them, or one product's.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorize('export back in stock');

        $query = Alert::query()->with('item')->oldest();

        if ($itemId = $request->query('item')) {
            $query->where('item_id', (int) $itemId);
        }

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Product', 'Variant', 'Email', 'Status', 'Site', 'Asked', 'Confirmed', 'Emailed', 'Bought', 'Order'], escape: '');

            $names = [];

            $query->chunk(500, function (Collection $alerts) use ($out, &$names) {
                foreach ($alerts as $alert) {
                    $names[$alert->item_id] ??= $alert->item?->product()?->title() ?? $alert->product_id;

                    fputcsv($out, array_map(fn ($value) => $this->csvCell($value), [
                        $names[$alert->item_id],
                        $alert->variant,
                        $alert->email,
                        $alert->status->value,
                        $alert->site,
                        $alert->created_at?->toDateTimeString(),
                        $alert->confirmed_at?->toDateTimeString(),
                        $alert->notified_at?->toDateTimeString(),
                        $alert->converted_at?->toDateTimeString(),
                        $alert->order_id,
                    ]), escape: '');
                }
            });

            fclose($out);
        }, 'back-in-stock-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Products and variants, most wanted first.
     */
    protected function itemsQuery(?string $search, string $show): Builder
    {
        $query = Item::query()->withCount($this->counts());

        if ($show === 'waiting') {
            $query->whereHas('alerts', fn ($q) => $q->whereIn('status', [AlertStatus::Waiting->value, AlertStatus::Unconfirmed->value, AlertStatus::Sending->value]));
        }

        if ($search) {
            $ids = Entry::query()
                ->whereIn('collection', (array) config('statamic.cargo.products.collections', ['products']))
                ->where('title', 'like', '%'.$search.'%')
                ->get()
                ->map(fn ($entry) => (string) $entry->id())
                ->all();

            $query->where(fn ($q) => $q->whereIn('product_id', $ids)->orWhere('variant', 'like', '%'.$this->escapeLike($search).'%'));
        }

        return $query->orderByDesc('waiting_count')->orderByDesc('unconfirmed_count')->latest('updated_at');
    }

    /**
     * @return array<string, \Closure>
     */
    protected function counts(): array
    {
        return [
            'alerts as waiting_count' => fn ($q) => $q->where('status', AlertStatus::Waiting->value),
            'alerts as unconfirmed_count' => fn ($q) => $q->where('status', AlertStatus::Unconfirmed->value),
            'alerts as notified_count' => fn ($q) => $q->whereIn('status', [AlertStatus::Notified->value, AlertStatus::Converted->value]),
            'alerts as converted_count' => fn ($q) => $q->where('status', AlertStatus::Converted->value),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function itemRow(Item $item): array
    {
        $product = $item->product();
        $lastRestock = $item->restocks()->latest('id')->first();

        return [
            'id' => $item->id,
            'title' => $product?->title() ?? __('Deleted product'),
            'variant' => $product?->variantName() ?? ($item->variant !== '' ? $item->variant : null),
            'missing' => $product === null,
            'published' => $product?->isAvailable() ?? false,
            'stock' => $product?->stock(),
            'tracked' => $product?->tracksStock() ?? true,
            'sold_out' => $product?->isSoldOut() ?? false,
            'waiting' => (int) ($item->waiting_count ?? 0),
            'unconfirmed' => (int) ($item->unconfirmed_count ?? 0),
            'notified' => (int) ($item->notified_count ?? 0),
            'converted' => (int) ($item->converted_count ?? 0),
            'pending' => $item->pending,
            'last_restock' => $lastRestock?->created_at?->diffForHumans(),
            'url' => cp_route('back-in-stock.show', $item->id),
            'edit_url' => $product?->editUrl(),
            'live_url' => $product?->url(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function alertRow(Alert $alert, bool $withProduct = false): array
    {
        $product = $withProduct ? $alert->item?->product() : null;

        return [
            'id' => $alert->id,
            'email' => $alert->email,
            'status' => $alert->status->value,
            'status_label' => $alert->status->label(),
            'site' => $alert->site,
            'open' => $alert->isOpen(),
            'asked' => $alert->created_at?->toDayDateTimeString(),
            'asked_ago' => $alert->created_at?->diffForHumans(),
            'confirmed' => $alert->confirmed_at?->toDayDateTimeString(),
            'notified' => $alert->notified_at?->toDayDateTimeString(),
            'converted' => $alert->converted_at?->toDayDateTimeString(),
            'order_id' => $alert->order_id,
            'product' => $withProduct ? ($product?->name() ?? $alert->product_id) : null,
            'item_url' => $withProduct ? cp_route('back-in-stock.show', $alert->item_id) : null,
            'cancel_url' => cp_route('back-in-stock.cancel', $alert->id),
            'delete_url' => cp_route('back-in-stock.destroy', $alert->id),
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    protected function stats(): array
    {
        $since = now()->subDays(30);
        $notified = Alert::query()->whereIn('status', [AlertStatus::Notified->value, AlertStatus::Converted->value])->where('notified_at', '>=', $since)->count();
        $converted = Alert::query()->where('status', AlertStatus::Converted->value)->where('notified_at', '>=', $since)->count();

        return [
            'waiting' => Alert::query()->where('status', AlertStatus::Waiting->value)->count(),
            'products' => Item::query()->whereHas('waiting')->count(),
            'notified' => $notified,
            'converted' => $converted,
            'rate' => $notified > 0 ? round($converted / $notified * 100).'%' : null,
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function statuses(): array
    {
        return collect(AlertStatus::cases())->map(fn (AlertStatus $s) => ['value' => $s->value, 'label' => $s->label()])->all();
    }

    protected function search(Request $request): ?string
    {
        $search = $request->query('search');

        return is_string($search) && trim($search) !== '' ? mb_substr(trim($search), 0, 100) : null;
    }

    protected function email(Request $request): ?string
    {
        $email = $request->input('email');

        return is_string($email) && trim($email) !== '' && strlen($email) <= 254 ? Alert::normaliseEmail($email) : null;
    }

    protected function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    protected function settingsUrl(): ?string
    {
        try {
            $addon = Addon::get(Settings::ADDON);

            return $addon && User::current()->can('editSettings', $addon)
                ? cp_route('addons.settings.edit', $addon->slug())
                : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * A spreadsheet cell that can't be read as a formula.
     */
    protected function csvCell(mixed $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
