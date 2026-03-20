<?php

namespace App\Jobs;

use App\Traits\FetchesWbPages;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

class WbDispatcherJob implements ShouldQueue
{
    use Queueable, FetchesWbPages;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        private readonly string $path,
        private readonly array $params,
        private readonly string $modelClass,
        private readonly string $mode = 'insert',
        private readonly ?string $upsertKey = null,
        private readonly bool $truncateFirst = false,
    ) {}

    public function handle(): void
    {
        $body     = $this->fetchPage($this->path, $this->params, 1);
        $rows     = $body['data'];
        $lastPage = $body['meta']['last_page'];

        Log::info("WB Dispatcher: {$this->path} — {$lastPage} page(s) total, page 1 rows: " . count($rows));

        if ($this->truncateFirst) {
            $this->modelClass::truncate();
        }

        $this->saveRows($rows);

        if ($lastPage <= 1) {
            Log::info("WB Dispatcher: {$this->path} — single page, done");
            return;
        }

        $pageJobs = [];
        for ($page = 2; $page <= $lastPage; $page++) {
            $pageJobs[] = new WbPageJob(
                path: $this->path,
                params: $this->params,
                page: $page,
                modelClass: $this->modelClass,
                mode: $this->mode,
                upsertKey: $this->upsertKey,
            );
        }

        Bus::batch($pageJobs)
            ->allowFailures()
            ->catch(function (\Illuminate\Bus\Batch $batch, Throwable $e) {
                Log::error("WB Batch failed: {$batch->name}", [
                    'error' => $e->getMessage(),
                    'failedJobs' => $batch->failedJobs,
                    'totalJobs' => $batch->totalJobs,
                ]);
            })
            ->name("WB Import: {$this->path}")
            ->dispatch();
    }

    private function saveRows(array $rows): void
    {
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
    }

    public function failed(Throwable $exception): void
    {
        Log::error("WbDispatcherJob FAILED: {$this->path}", [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}