<?php

namespace App\Providers;

use App\Services\CommissionCalculator;
use App\Services\CsvFileHandler;
use App\Services\CurrencyExchangeService;
use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CurrencyExchangeService::class, function ($app) {
            return new CurrencyExchangeService(new Client());
        });

        $this->app->singleton(CsvFileHandler::class, function ($app) {
            return new CsvFileHandler();
        });

        $this->app->singleton(CommissionCalculator::class, function ($app) {
            return new CommissionCalculator(
                $app->make(CurrencyExchangeService::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
