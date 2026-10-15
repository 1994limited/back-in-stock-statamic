<?php

namespace NineteenNinetyFour\BackInStock\Commands;

use Illuminate\Console\Command;
use NineteenNinetyFour\BackInStock\BackInStock;
use Statamic\Console\RunsInPlease;

/**
 * Shows or deletes everything kept about an email address.
 */
class Forget extends Command
{
    use RunsInPlease;

    protected $signature = 'back-in-stock:forget {email} {--export : Print what is kept as JSON instead of deleting it}';

    protected $description = 'Delete (or export) everything Back in Stock keeps about an email address';

    public function handle(BackInStock $backInStock): int
    {
        $email = (string) $this->argument('email');

        if ($this->option('export')) {
            $this->line(json_encode(['email' => mb_strtolower($email), 'requests' => $backInStock->export($email)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $deleted = $backInStock->forget($email);

        $this->components->info("{$deleted} requests deleted.");

        return self::SUCCESS;
    }
}
