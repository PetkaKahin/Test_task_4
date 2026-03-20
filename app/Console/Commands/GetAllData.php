<?php

namespace App\Console\Commands;

use App\Jobs\WbDispatcherJob;
use App\Models\Income;
use App\Models\Order;
use App\Models\Sale;
use App\Models\Stock;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('db:get-all-data {--from=2020-01-01 : Start date for historical data fetch}')]
#[Description('Fills in the database from the source')]
class GetAllData extends Command
{
    public function handle(): int
    {
        $today    = now()->toDateString();
        $dateFrom = $this->option('from');

        WbDispatcherJob::dispatch('/api/stocks', ['dateFrom' => $today], Stock::class, 'insert', null, true);
        WbDispatcherJob::dispatch('/api/incomes', ['dateFrom' => $dateFrom, 'dateTo' => $today], Income::class, 'insert', null, true);
        WbDispatcherJob::dispatch('/api/sales', ['dateFrom' => $dateFrom, 'dateTo' => $today], Sale::class, 'upsert', 'sale_id');
        WbDispatcherJob::dispatch('/api/orders', ['dateFrom' => $dateFrom, 'dateTo' => $today], Order::class, 'insert', null, true);

        $this->info('Джобы поставлены в очередь');

        return self::SUCCESS;
    }
}