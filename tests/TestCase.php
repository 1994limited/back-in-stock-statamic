<?php

namespace NineteenNinetyFour\BackInStock\Tests;

use DuncanMcClean\Cargo\Contracts\Cart\Cart as CartContract;
use DuncanMcClean\Cargo\Contracts\Orders\Order as OrderContract;
use DuncanMcClean\Cargo\Contracts\Products\Product as ProductContract;
use DuncanMcClean\Cargo\Discounts\DiscountServiceProvider;
use DuncanMcClean\Cargo\Facades\Cart;
use DuncanMcClean\Cargo\Facades\PaymentGateway;
use DuncanMcClean\Cargo\Facades\Product;
use DuncanMcClean\Cargo\Orders\Actions\CreateOrderFromCart;
use DuncanMcClean\Cargo\Orders\OrderServiceProvider;
use DuncanMcClean\Cargo\Payments\PaymentServiceProvider;
use DuncanMcClean\Cargo\ServiceProvider as CargoServiceProvider;
use DuncanMcClean\Cargo\Shipping\ShippingServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use NineteenNinetyFour\BackInStock\AlertStatus;
use NineteenNinetyFour\BackInStock\BackInStock;
use NineteenNinetyFour\BackInStock\Models\Alert;
use NineteenNinetyFour\BackInStock\ServiceProvider;
use Statamic\Addons\Manifest;
use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Statamic\Testing\AddonTestCase;
use Statamic\Testing\Concerns\FakesRoles;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;
use Stillat\Proteus\WriterServiceProvider;

abstract class TestCase extends AddonTestCase
{
    use FakesRoles, PreventsSavingStacheItemsToDisk, RefreshDatabase;

    protected string $addonServiceProvider = ServiceProvider::class;

    /** Where Cargo keeps carts and orders, and blueprints are read from, during a test. */
    protected static string $content = __DIR__.'/__fixtures__/tmp';

    protected function getPackageProviders($app)
    {
        // Cargo first, as a site would have it: this addon depends on it.
        return array_merge([
            CargoServiceProvider::class,
            DiscountServiceProvider::class,
            OrderServiceProvider::class,
            PaymentServiceProvider::class,
            ShippingServiceProvider::class,
            WriterServiceProvider::class,
        ], parent::getPackageProviders($app));
    }

    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']->set('statamic.editions.pro', true);
        $app['config']->set('statamic.cargo', require __DIR__.'/../vendor/duncanmcclean/statamic-cargo/config/cargo.php');

        Route::get('checkout', fn () => 'Checkout')->name('checkout');
        Route::get('checkout/confirmation', fn () => 'Confirmation')->name('checkout.confirmation');
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        // Statamic boots only the addons in its manifest, and the test case
        // lists just this one. Cargo is installed alongside it on a site.
        $app->make(Manifest::class)->manifest['duncanmcclean/statamic-cargo'] = [
            'id' => 'duncanmcclean/statamic-cargo',
            'slug' => 'cargo',
            'version' => '1.15.0',
            'namespace' => 'DuncanMcClean\\Cargo',
            'autoload' => 'src',
            'provider' => CargoServiceProvider::class,
        ];

        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('statamic.cargo.carts.directory', static::$content.'/carts');
        $app['config']->set('statamic.cargo.orders.directory', static::$content.'/orders');
        $app['config']->set('statamic.cargo.discounts.directory', static::$content.'/discounts');
        $app['config']->set('statamic.cargo.taxes.tax_classes.directory', static::$content.'/tax-classes');
        $app['config']->set('statamic.cargo.taxes.tax_zones.directory', static::$content.'/tax-zones');
        $app['config']->set('mail.default', 'array');
        $app['config']->set('view.paths', [__DIR__.'/__fixtures__/views']);
    }

    protected function setUp(): void
    {
        $files = new Filesystem;
        $files->deleteDirectory(static::$content);
        $files->ensureDirectoryExists(static::$content.'/blueprints/collections/products');
        $files->copy(__DIR__.'/__fixtures__/blueprints/product.yaml', static::$content.'/blueprints/collections/products/product.yaml');

        parent::setUp();

        Site::setSites([
            'default' => ['name' => 'Dog-ear Books', 'url' => 'http://localhost/', 'locale' => 'en_GB', 'attributes' => ['currency' => 'GBP']],
        ])->save();

        Blueprint::setDirectory(static::$content.'/blueprints');

        Collection::make('products')->routes('/books/{slug}')->save();
    }

    protected function tearDown(): void
    {
        Cart::forgetCurrentCart();

        // Addon settings saved by a test are written to the test app.
        (new Filesystem)->deleteDirectory(resource_path('addons'));

        parent::tearDown();

        (new Filesystem)->deleteDirectory(static::$content);
    }

    /**
     * A book with a stock level (null: stock isn't counted).
     */
    protected function book(string $id = 'lantern-ledger', ?int $stock = 0, array $data = []): ProductContract
    {
        return $this->productEntry($id, array_merge(['title' => 'The Lantern Ledger', 'price' => 999], $stock === null ? [] : ['stock' => $stock], $data));
    }

    /**
     * A book in hardback and paperback, each with its own stock.
     */
    protected function editions(string $id = 'coral-arithmetic', int $hardback = 0, int $paperback = 7, array $data = []): ProductContract
    {
        return $this->productEntry($id, array_merge([
            'title' => 'Coral Arithmetic',
            'tax_class' => 'general',
            'product_variants' => [
                'variants' => [['name' => 'Edition', 'values' => ['Hardback', 'Paperback']]],
                'options' => [
                    ['key' => 'Hardback', 'variant' => 'Hardback', 'price' => 1800, 'stock' => $hardback],
                    ['key' => 'Paperback', 'variant' => 'Paperback', 'price' => 1300, 'stock' => $paperback],
                ],
            ],
        ], $data));
    }

    protected function productEntry(string $id, array $data): ProductContract
    {
        $published = $data['published'] ?? true;
        unset($data['published']);

        Entry::make()->collection('products')->blueprint('product')->id($id)->slug($id)->published($published)->data($data)->save();

        return Product::find($id);
    }

    /**
     * Change a product's stock as the Control Panel does: set it and save.
     */
    protected function restock(string $id, int $stock, ?string $variant = null): void
    {
        $product = Product::find($id);

        if ($variant === null) {
            $product->set('stock', $stock)->save();

            return;
        }

        $variants = $product->get('product_variants');
        $variants['options'] = collect($variants['options'])->map(fn ($o) => $o['key'] === $variant ? array_merge($o, ['stock' => $stock]) : $o)->all();
        $product->set('product_variants', $variants)->save();
    }

    /**
     * A confirmed request.
     */
    protected function waiting(string $email = 'reader@example.com', string $product = 'lantern-ledger', ?string $variant = null): Alert
    {
        $alert = $this->asked($email, $product, $variant);
        app(BackInStock::class)->confirm($alert);

        return $alert->refresh();
    }

    /**
     * A request, not yet confirmed.
     */
    protected function asked(string $email = 'reader@example.com', string $product = 'lantern-ledger', ?string $variant = null): Alert
    {
        $backInStock = app(BackInStock::class);
        RateLimiter::clear('back-in-stock:confirm:'.hash('sha256', $email));

        return $backInStock->subscribe($backInStock->product($product, $variant), $email, consent: 'Email me once.');
    }

    protected function statusOf(Alert $alert): AlertStatus
    {
        return $alert->refresh()->status;
    }

    protected function cart(array $lineItems = [], string $email = 'reader@example.com'): CartContract
    {
        $cart = Cart::make()
            ->customer(['name' => 'Ada Reader', 'email' => $email])
            ->set('billing_address', ['line_1' => '1 High Street', 'city' => 'London', 'postcode' => 'N1 1AA', 'country' => 'GBR']);

        foreach ($lineItems as $lineItem) {
            $cart->lineItems()->create(array_merge(['quantity' => 1], $lineItem));
        }

        $cart->save();
        Cart::setCurrent($cart);

        return $cart;
    }

    protected function checkout(CartContract $cart): OrderContract
    {
        $cart = Cart::find($cart->id());

        return app(CreateOrderFromCart::class)->handle($cart, $cart->isPaid() ? PaymentGateway::find('dummy') : null);
    }

    protected function user(string $email = 'admin@example.com', array $permissions = [], bool $super = false): \Statamic\Contracts\Auth\User
    {
        if ($permissions) {
            $this->setTestRoles(['tester' => ['access cp', ...$permissions]]);
        }

        $user = User::make()->email($email)->id((string) str($email)->before('@')->slug());

        if ($permissions) {
            $user->assignRole('tester');
        }

        if ($super) {
            $user->makeSuper();
        }

        $user->save();

        return $user;
    }

    /**
     * Render Antlers as a site template would (trusted, so tags run).
     */
    protected function antlers(string $template, array $data = []): string
    {
        return (string) Antlers::parse($template, $data, true);
    }
}
