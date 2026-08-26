<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramBotService;
use Exception;
use Illuminate\Console\Command;

class TelegramPollCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:poll {--timeout=30 : Long polling timeout in seconds}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start Telegram Long Polling daemon to listen and process messages locally';

    /**
     * Execute the console command.
     */
    public function handle(TelegramBotService $botService): int
    {
        $timeout = (int) $this->option('timeout');
        $offset = 0;

        $this->info("🤖 Starting Telegram Long Polling daemon (timeout: {$timeout}s)...");
        $this->info('Press Ctrl+C to stop.');

        while (true) {
            try {
                $updates = $botService->getUpdates($offset, 100, $timeout);

                foreach ($updates as $update) {
                    $updateId = $update['update_id'] ?? null;
                    if ($updateId) {
                        $offset = $updateId + 1;
                    }

                    $this->line("📥 Processing Update #{$updateId}...");
                    $botService->handleUpdate($update);
                }
            } catch (Exception $e) {
                $this->error('Polling error: '.$e->getMessage());
                sleep(2); // Cool down before retrying
            }
        }

        return Command::SUCCESS;
    }
}
