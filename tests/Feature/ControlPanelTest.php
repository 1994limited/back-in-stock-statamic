<?php

namespace NineteenNinetyFour\BackInStock\Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use NineteenNinetyFour\BackInStock\AlertStatus;
use NineteenNinetyFour\BackInStock\Mail\RestockedMail;
use NineteenNinetyFour\BackInStock\Models\Alert;
use NineteenNinetyFour\BackInStock\Models\Item;
use NineteenNinetyFour\BackInStock\Settings;
use NineteenNinetyFour\BackInStock\Tests\TestCase;
use NineteenNinetyFour\BackInStock\Widgets\BackInStockWidget;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Contracts\Auth\User;
use Statamic\Facades\Addon;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Permission;

class ControlPanelTest extends TestCase
{
    protected function viewer(): User
    {
        return $this->user('viewer@example.com', ['view back in stock']);
    }

    protected function manager(): User
    {
        return $this->user('manager@example.com', ['view back in stock', 'manage back in stock', 'export back in stock']);
    }

    #[Test]
    public function every_page_needs_permission(): void
    {
        $this->book(stock: 0);
        $alert = $this->waiting();
        $this->actingAs($this->user('nobody@example.com', ['access cp']));

        $this->getJson(cp_route('back-in-stock.index'))->assertForbidden();
        $this->getJson(cp_route('back-in-stock.show', $alert->item_id))->assertForbidden();
        $this->getJson(cp_route('back-in-stock.person', ['email' => 'reader@example.com']))->assertForbidden();
        $this->getJson(cp_route('back-in-stock.export'))->assertForbidden();
    }

    #[Test]
    public function viewing_does_not_allow_sending_deleting_or_downloading(): void
    {
        $this->book(stock: 2);
        Item::for('lantern-ledger');
        $this->book('out', stock: 0);
        $alert = $this->waiting('reader@example.com', 'out');
        $this->actingAs($this->viewer());

        $this->postJson(cp_route('back-in-stock.send', $alert->item_id))->assertForbidden();
        $this->postJson(cp_route('back-in-stock.cancel', $alert->id))->assertForbidden();
        $this->deleteJson(cp_route('back-in-stock.destroy', $alert->id))->assertForbidden();
        $this->postJson(cp_route('back-in-stock.person.forget'), ['email' => 'reader@example.com'])->assertForbidden();
        $this->getJson(cp_route('back-in-stock.person.download', ['email' => 'reader@example.com']))->assertForbidden();
        $this->getJson(cp_route('back-in-stock.export'))->assertForbidden();

        $this->assertSame(AlertStatus::Waiting, $this->statusOf($alert));
    }

    #[Test]
    public function the_list_shows_the_most_wanted_first(): void
    {
        $this->book('one', stock: 0, data: ['title' => 'Wanted once']);
        $this->editions(hardback: 0);
        $this->waiting('a@example.com', 'one');
        $this->waiting('a@example.com', 'coral-arithmetic', 'Hardback');
        $this->waiting('b@example.com', 'coral-arithmetic', 'Hardback');
        $this->asked('c@example.com', 'coral-arithmetic', 'Hardback');

        $this->actingAs($this->viewer())
            ->get(cp_route('back-in-stock.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('back-in-stock::Index')
                ->has('items', 2)
                ->where('items.0.title', 'Coral Arithmetic')
                ->where('items.0.variant', 'Hardback')
                ->where('items.0.waiting', 2)
                ->where('items.0.unconfirmed', 1)
                ->where('items.0.sold_out', true)
                ->where('items.0.stock', 0)
                ->where('items.1.title', 'Wanted once')
                ->where('stats.waiting', 3)
                ->where('stats.products', 2)
                ->where('canExport', false)
                ->etc());
    }

    #[Test]
    public function the_list_can_be_searched_by_product(): void
    {
        $this->book('one', stock: 0, data: ['title' => 'Small Weathers']);
        $this->book('two', stock: 0, data: ['title' => 'The Salt Orchard']);
        $this->waiting('a@example.com', 'one');
        $this->waiting('a@example.com', 'two');

        $this->actingAs($this->viewer())
            ->get(cp_route('back-in-stock.index', ['search' => 'salt']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('items', 1)->where('items.0.title', 'The Salt Orchard')->etc());

        $this->get(cp_route('back-in-stock.index', ['search' => ['array']]))->assertOk();
    }

    #[Test]
    public function a_products_page_lists_whos_waiting_and_what_happened(): void
    {
        $this->book(stock: 0);
        $alert = $this->waiting('a@example.com');
        $this->asked('b@example.com');
        Mail::fake();
        $this->restock('lantern-ledger', 1);
        $this->restock('lantern-ledger', 0);
        $this->waiting('c@example.com');

        $this->actingAs($this->manager())
            ->get(cp_route('back-in-stock.show', $alert->item_id))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('back-in-stock::Show')
                ->where('item.title', 'The Lantern Ledger')
                ->has('alerts', 3)
                ->where('alerts.0.email', 'c@example.com')
                ->where('alerts.0.status', 'waiting')
                ->where('alerts.1.status', 'unconfirmed')
                ->where('alerts.2.status', 'notified')
                ->has('restocks', 1)
                ->where('restocks.0.sent', 1)
                ->where('restocks.0.after', 1)
                ->where('inStock', false)
                ->where('canManage', true)
                ->etc());
    }

    #[Test]
    public function send_now_emails_everyone_waiting_when_its_in_stock(): void
    {
        config(['back-in-stock.limit' => 'stock']);
        $this->book(stock: 0);
        $this->waiting('a@example.com');
        $this->waiting('b@example.com');
        Mail::fake();
        $this->restock('lantern-ledger', 1);
        Mail::assertSent(RestockedMail::class, 1);

        $this->actingAs($this->manager())
            ->post(cp_route('back-in-stock.send', Item::sole()->id))
            ->assertRedirect();

        Mail::assertSent(RestockedMail::class, 2);
    }

    #[Test]
    public function send_now_does_nothing_while_sold_out(): void
    {
        $this->book(stock: 0);
        $alert = $this->waiting();
        Mail::fake();

        $this->actingAs($this->manager())->post(cp_route('back-in-stock.send', $alert->item_id));

        Mail::assertNothingSent();
        $this->assertSame(AlertStatus::Waiting, $this->statusOf($alert));
    }

    #[Test]
    public function requests_can_be_cancelled_and_deleted(): void
    {
        $this->book(stock: 0);
        $one = $this->waiting('a@example.com');
        $two = $this->waiting('b@example.com');
        $this->actingAs($this->manager());

        $this->post(cp_route('back-in-stock.cancel', $one->id))->assertRedirect();
        $this->delete(cp_route('back-in-stock.destroy', $two->id))->assertRedirect();

        $this->assertSame(AlertStatus::Cancelled, $this->statusOf($one));
        $this->assertNull(Alert::find($two->id));
    }

    #[Test]
    public function everything_about_one_address_can_be_found_downloaded_and_deleted(): void
    {
        $this->book('a', stock: 0);
        $this->book('b', stock: 0);
        $this->waiting('reader@example.com', 'a');
        $this->asked('reader@example.com', 'b');
        $this->waiting('other@example.com', 'a');
        $this->actingAs($this->manager());

        $this->get(cp_route('back-in-stock.person', ['email' => 'Reader@Example.com']))
            ->assertInertia(fn (AssertableInertia $page) => $page->component('back-in-stock::Person')->where('email', 'reader@example.com')->has('alerts', 2)->etc());

        $download = $this->get(cp_route('back-in-stock.person.download', ['email' => 'reader@example.com']))->assertOk();
        $this->assertStringContainsString('attachment;', $download->headers->get('Content-Disposition'));
        $this->assertCount(2, $download->json('requests'));
        $this->assertSame('Email me once.', $download->json('requests.0.consent'));

        $this->post(cp_route('back-in-stock.person.forget'), ['email' => 'reader@example.com'])->assertRedirect();

        $this->assertSame(['other@example.com'], Alert::pluck('email')->all());
    }

    #[Test]
    public function requests_download_as_a_spreadsheet_that_cant_run_formulas(): void
    {
        $this->book('x', stock: 0, data: ['title' => '=HYPERLINK("http://evil")']);
        $this->waiting('a@example.com', 'x');
        $this->actingAs($this->manager());

        $csv = $this->get(cp_route('back-in-stock.export'))->assertOk()->streamedContent();

        $this->assertStringContainsString('Product,Variant,Email,Status', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString('a@example.com,waiting', $csv);
    }

    #[Test]
    public function the_nav_and_permissions_are_registered(): void
    {
        $this->actingAs($this->user('admin@example.com', super: true));

        $this->get(cp_route('back-in-stock.index'))->assertOk();
        $this->assertNotNull(Permission::get('manage back in stock'));
    }

    #[Test]
    public function the_widget_lists_sold_out_products_most_wanted_first(): void
    {
        $this->book('back', stock: 0, data: ['title' => 'Back now']);
        $this->editions(hardback: 0);
        $this->waiting('a@example.com', 'coral-arithmetic', 'Hardback');
        $this->waiting('b@example.com', 'coral-arithmetic', 'Hardback');
        $this->actingAs($this->viewer());

        $props = (new BackInStockWidget)->component()->toArray()['props'];

        $this->assertSame('Wanted while sold out', $props['title']);
        $this->assertSame('Coral Arithmetic – Hardback', $props['rows'][0]['name']);
        $this->assertSame(2, $props['rows'][0]['waiting']);
        $this->assertCount(1, $props['rows']);
    }

    #[Test]
    public function settings_are_edited_in_the_control_panel_and_default_to_the_config(): void
    {
        config(['back-in-stock.limit' => 'stock', 'back-in-stock.delay' => 9]);
        $addon = Addon::get(Settings::ADDON);

        $this->assertTrue($addon->hasSettingsBlueprint());
        $this->assertSame('stock', Settings::limit());
        $this->assertSame(9, Settings::delay());

        $addon->settings()->set(['limit' => 'all', 'delay' => 0])->save();
        $this->assertSame('all', Settings::limit());
        $this->assertSame(0, Settings::delay());

        // With no asset container (no logo field), one, or several.
        $this->actingAs($this->user('admin@example.com', super: true))
            ->get(cp_route('addons.settings.edit', $addon->slug()))
            ->assertOk();

        AssetContainer::make('images')->disk('local')->save();
        AssetContainer::make('files')->disk('local')->save();
        $this->get(cp_route('addons.settings.edit', $addon->slug()))->assertOk();
    }
}
