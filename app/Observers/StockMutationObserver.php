<?php

namespace App\Observers;

use App\Jobs\GenerateDssCacheJob;
use App\Models\StockMutations;

class StockMutationObserver
{

    /**
     * Handle the StockMutation "updated" event.
     */
    public function updated(StockMutations $stockMutation): void
    {
        if ($stockMutation->wasChanged('status') && in_array($stockMutation->status, config('dss.completed_statuses'))) {
            GenerateDssCacheJob::dispatch();
        }
    }
}
