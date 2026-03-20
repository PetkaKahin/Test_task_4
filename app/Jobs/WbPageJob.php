<?php

namespace App\Jobs;

use App\Traits\FetchesWbPages;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Support\Facades\Log;
use Throwable;

class WbPageJob implements ShouldQueue
{
    use Queueable, Batchable, FetchesWbPages;

    public int $tries = 5;
    public array $backoff = [10, 30, 60, 120, 300];

    public function middleware(): array
    {
        return [
            new ThrottlesExceptions(maxAttempts: 3, decaySeconds: 60),
        ];
    }

    public function __construct(
        private readonly string $path,
        private readonly array $params,
        private readonly int $page,
        private readonly string $modelClass,
        private readonly string $mode = 'insert',
        private readonly ?string $upsertKey = null,
    ) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        // Специально, чтобы не получать 429
        sleep(3);

        $body = $this->fetchPage($this->path, $this->params, $this->page);
        $rows = $body['data'];

        if (empty($rows)) {
            return;
        }

        if ($this->mode === 'upsert' && $this->upsertKey) {
            foreach (array_chunk($rows, 500) as $chunk) {
                $this->modelClass::upsert($chunk, [$this->upsertKey]);
            }
        } else {
            foreach (array_chunk($rows, 500) as $chunk) {
                $this->modelClass::insert($chunk);
            }
        }

        Log::info("WB Page: {$this->path} page {$this->page}", ['rows' => count($rows)]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error("WbPageJob FAILED: {$this->path} page {$this->page}", [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}