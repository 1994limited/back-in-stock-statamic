<?php

namespace NineteenNinetyFour\BackInStock\Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use NineteenNinetyFour\BackInStock\AlertStatus;
use NineteenNinetyFour\BackInStock\Models\Alert;
use NineteenNinetyFour\BackInStock\Models\Item;
use NineteenNinetyFour\BackInStock\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CommandsTest extends TestCase
{
    #[Test]
    public function prune_deletes_requests_kept_too_long(): void
    {
        $this->book(stock: 0);
        $unconfirmed = $this->asked('u@example.com');
        $waiting = $this->waiting('w@example.com');
        $cancelled = $this->waiting('c@example.com');
        $cancelled->forceFill(['status' => AlertStatus::Cancelled])->save();

        $this->travel(8)->days();
        $fresh = $this->asked('fresh@example.com');
        $this->artisan('back-in-stock:prune')->assertSuccessful();

        $this->assertNull(Alert::find($unconfirmed->id));
        $this->assertNotNull(Alert::find($waiting->id));
        $this->assertNotNull(Alert::find($cancelled->id));
        $this->assertNotNull(Alert::find($fresh->id));

        $this->travel(90)->days();
        $this->artisan('back-in-stock:prune');
        $this->assertNull(Alert::find($cancelled->id));
        $this->assertNotNull(Alert::find($waiting->id));

        $this->travel(300)->days();
        $this->artisan('back-in-stock:prune');
        $this->assertSame(0, Alert::count());

        $this->travel(2)->days();
        $this->artisan('back-in-stock:prune');
        $this->assertSame(0, Item::count());
    }

    #[Test]
    public function waiting_requests_can_be_kept_for_ever(): void
    {
        config(['back-in-stock.retention.waiting' => null]);
        $this->book(stock: 0);
        $waiting = $this->waiting();

        $this->travel(1000)->days();
        $this->artisan('back-in-stock:prune');

        $this->assertNotNull(Alert::find($waiting->id));
    }

    #[Test]
    public function forget_exports_or_deletes_one_address(): void
    {
        $this->book(stock: 0);
        $this->waiting('reader@example.com');
        $this->waiting('other@example.com');

        $this->artisan('back-in-stock:forget', ['email' => 'READER@example.com', '--export' => true])
            ->expectsOutputToContain('"email": "reader@example.com"')
            ->assertSuccessful();
        $this->assertSame(2, Alert::count());

        $this->artisan('back-in-stock:forget', ['email' => 'reader@example.com'])->expectsOutputToContain('1 requests deleted');
        $this->assertSame(['other@example.com'], Alert::pluck('email')->all());
    }

    #[Test]
    public function the_commands_are_scheduled(): void
    {
        $events = collect(app(Schedule::class)->events())->map(fn ($e) => [$e->command, $e->expression]);

        $this->assertTrue($events->contains(fn ($e) => str_contains($e[0], 'back-in-stock:check') && $e[1] === '*/5 * * * *'));
        $this->assertTrue($events->contains(fn ($e) => str_contains($e[0], 'back-in-stock:prune') && $e[1] === '0 0 * * *'));
    }
}
