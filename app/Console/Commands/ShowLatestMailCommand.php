<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MailLogReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Prints the links of the most recent mails the application "sent".
 *
 * Locally MAIL_MAILER=log, so every message (verification, password reset) ends
 * up in storage/logs/laravel.log instead of an inbox. This command pulls the
 * actionable link back out, which beats scanning a two megabyte log by hand.
 *
 * Development helper: it refuses to run in production, where real mail is
 * delivered and the log must not become a source of live tokens.
 */
class ShowLatestMailCommand extends Command
{
    protected $signature = 'noircat:mail:latest
        {--limit=3 : How many links to print}
        {--path= : Read another log file (defaults to storage/logs/laravel.log)}
        {--force : Run even in production (not recommended)}';

    protected $description = 'Show the newest email verification or password reset links from the mail log';

    public function handle(): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->components->error('Refusing to print live links in production. Use --force if you really mean it.');

            return self::FAILURE;
        }

        $path = (string) ($this->option('path') ?: storage_path('logs/laravel.log'));
        $reader = new MailLogReader($path);
        $newest = $reader->newestOrEmpty(max(1, (int) $this->option('limit')));

        if ($newest === []) {
            $this->components->warn("No verification or reset link found in {$path}.");
            $this->line('  Registration mail is queued: make sure a worker is running.');

            return $this->reportQueueState(self::SUCCESS);
        }

        $this->components->info('Newest links from '.$path);

        foreach ($newest as $index => $link) {
            $kind = str_contains($link, '/email/verify/') ? 'email verification' : 'password reset';
            $this->components->twoColumnDetail('#'.($index + 1)." {$kind}", $link);
        }

        return $this->reportQueueState(self::SUCCESS);
    }

    /**
     * A queued notification that no worker picked up is the usual reason for a
     * missing mail, so say it out loud.
     */
    private function reportQueueState(int $exitCode): int
    {
        if (config('queue.default') !== 'database') {
            return $exitCode;
        }

        try {
            $pending = DB::table('jobs')->count();
        } catch (Throwable) {
            return $exitCode;
        }

        if ($pending > 0) {
            $this->newLine();
            $this->components->warn("{$pending} queued job(s) waiting: run `php artisan queue:work` to deliver them.");
        }

        return $exitCode;
    }
}
