<?php

namespace App\Providers;

use App\Contracts\Repositories\StationSummaryRepositoryInterface;
use App\Contracts\Repositories\TransferEventRepositoryInterface;
use App\Contracts\Services\StationSummaryServiceInterface;
use App\Contracts\Services\TransferIngestionServiceInterface;
use App\Repositories\DatabaseStationSummaryRepository;
use App\Repositories\DatabaseTransferEventRepository;
use App\Services\Station\StationSummaryService;
use App\Services\Transfer\TransferIngestionService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TransferEventRepositoryInterface::class, DatabaseTransferEventRepository::class);
        $this->app->singleton(StationSummaryRepositoryInterface::class, DatabaseStationSummaryRepository::class);
        $this->app->singleton(TransferIngestionServiceInterface::class, TransferIngestionService::class);
        $this->app->singleton(StationSummaryServiceInterface::class, StationSummaryService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
