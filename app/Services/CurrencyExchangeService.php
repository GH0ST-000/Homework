<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CurrencyExchangeService
{
    private Client $httpClient;
    private string $apiUrl = 'https://api.exchangeratesapi.io/latest';
    private array $fallbackRates = [
        'USD' => 1.1497,
        'JPY' => 129.53
    ];

    public function __construct(Client $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function convertToEur(float $amount, string $fromCurrency, string $date): float
    {
        if ($fromCurrency === 'EUR') {
            return $amount;
        }

        $rate = $this->getExchangeRate($fromCurrency, $date);
        
        // Convert to EUR
        return $amount / $rate;
    }

    public function convertFromEur(float $amount, string $toCurrency, string $date): float
    {
        if ($toCurrency === 'EUR') {
            return $amount;
        }

        $rate = $this->getExchangeRate($toCurrency, $date);
        
        // Convert from EUR
        return $amount * $rate;
    }

    private function getExchangeRate(string $currency, string $date): float
    {
        // Trim the currency code to remove any whitespace
        $currency = trim($currency);
        
        if ($currency === 'EUR') {
            return 1.0;
        }
        
        $cacheKey = "exchange_rate_{$currency}_{$date}";
        
        // Try to get from cache first
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        
        try {
            // Try to get from API with the specific date
            $formattedDate = date('Y-m-d', strtotime($date));
            $historicalUrl = str_replace('latest', $formattedDate, $this->apiUrl);
            
            $response = $this->httpClient->get($historicalUrl, [
                'query' => [
                    'base' => 'EUR',
                    'symbols' => $currency
                ]
            ]);
            
            $data = json_decode($response->getBody()->getContents(), true);
            $rate = $data['rates'][$currency] ?? null;
            
            if ($rate !== null) {
                Cache::put($cacheKey, $rate, now()->addDay());
                return $rate;
            }
        } catch (GuzzleException $e) {
            Log::warning("Failed to get exchange rate from API for {$currency} on {$date}: " . $e->getMessage());
            
            // Try latest rates if historical rates failed
            try {
                $response = $this->httpClient->get($this->apiUrl, [
                    'query' => [
                        'base' => 'EUR',
                        'symbols' => $currency
                    ]
                ]);
                
                $data = json_decode($response->getBody()->getContents(), true);
                $rate = $data['rates'][$currency] ?? null;
                
                if ($rate !== null) {
                    Cache::put($cacheKey, $rate, now()->addHours(1));
                    return $rate;
                }
            } catch (GuzzleException $e2) {
                Log::error("Failed to get latest exchange rate from API for {$currency}: " . $e2->getMessage());
            }
        }
        
        // Use fallback rate if API requests failed
        if (isset($this->fallbackRates[$currency])) {
            $rate = $this->fallbackRates[$currency];
            Cache::put($cacheKey, $rate, now()->addDay());
            Log::info("Using fallback rate for {$currency}: {$rate}");
            return $rate;
        }
        
        throw new \RuntimeException("Exchange rate for currency '{$currency}' not available");
    }
} 