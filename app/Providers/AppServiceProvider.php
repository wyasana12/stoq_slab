<?php

namespace App\Providers;

use App\Models\PurchaseOrder;
use App\Models\Restock;
use App\Models\StockMutations;
use App\Models\StockTransfers;
use App\Models\User;
use App\Observers\StockMutationObserver;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap([
            'purchase_order' => PurchaseOrder::class,
            'transfer'       => StockTransfers::class,
            'restock'        => Restock::class,
            'user' => User::class,
        ]);

        Scramble::afterOpenApiGenerated(function (OpenApi $openApi) {
            $openApi->secure(
                SecurityScheme::http('bearer')
            );
        });

        StockMutations::observe(StockMutationObserver::class);
    }
}
