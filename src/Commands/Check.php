<?php

namespace NineteenNinetyFour\BackInStock\Commands;

use Illuminate\Console\Command;
use NineteenNinetyFour\BackInStock\Sender;
use NineteenNinetyFour\BackInStock\Watcher;
use Statamic\Console\RunsInPlease;

/**
 * Checks everything people are waiting for, for stock that came back
 * without the product being saved (an import writing files, a deploy),
 * and sends anything due. Runs every five minutes with the scheduler.
 */
class Check extends Command
{
    use RunsInPlease;

    protected $signature = 'back-in-stock:check';

    protected $description = 'Check for products back in stock, and send the emails that are due';

    public function handle(Watcher $watcher, Sender $sender): int
    {
        $released = $sender->releaseStuck();
        $restocked = $watcher->checkAll('schedule');
        $sent = $sender->sendDue();

        $this->components->info(sprintf(
            '%d back in stock, %d emailed%s.',
            $restocked,
            $sent,
            $released ? ", {$released} unfinished sends put back" : '',
        ));

        return self::SUCCESS;
    }
}
