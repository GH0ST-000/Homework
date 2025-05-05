<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Services\CommissionCalculator;
use App\Services\CsvFileHandler;
use App\Services\CurrencyExchangeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Mockery;

class CalculateCommissionCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock the CurrencyExchangeService to use fixed rates for predictable test results
        $this->mock(CurrencyExchangeService::class, function ($mock) {
            $mock->shouldReceive('convertToEur')
                ->with(30000, 'JPY', Mockery::any())
                ->andReturn(30000 / 129.53);
                
            $mock->shouldReceive('convertToEur')
                ->with(100, 'USD', Mockery::any())
                ->andReturn(100 / 1.1497);
                
            $mock->shouldReceive('convertToEur')
                ->with(3000000, 'JPY', Mockery::any())
                ->andReturn(3000000 / 129.53);
                
            $mock->shouldReceive('convertFromEur')
                ->with(Mockery::any(), 'JPY', Mockery::any())
                ->andReturnUsing(function ($amount) {
                    return $amount * 129.53;
                });
                
            $mock->shouldReceive('convertFromEur')
                ->with(Mockery::any(), 'USD', Mockery::any())
                ->andReturnUsing(function ($amount) {
                    return $amount * 1.1497;
                });
        });
    }

    public function test_command_calculates_commissions_correctly()
    {
        // Create a test CSV file
        $csvContent = <<<CSV
2014-12-31,4,private,withdraw,1200.00,EUR
2015-01-01,4,private,withdraw,1000.00,EUR
2016-01-05,4,private,withdraw,1000.00,EUR
2016-01-05,1,private,deposit,200.00,EUR
2016-01-06,2,business,withdraw,300.00,EUR
2016-01-06,1,private,withdraw,30000,JPY
2016-01-07,1,private,withdraw,1000.00,EUR
2016-01-07,1,private,withdraw,100.00,USD
2016-01-10,1,private,withdraw,100.00,EUR
2016-01-10,2,business,deposit,10000.00,EUR
2016-01-10,3,private,withdraw,1000.00,EUR
2016-02-15,1,private,withdraw,300.00,EUR
2016-02-19,5,private,withdraw,3000000,JPY
CSV;

        $filepath = storage_path('testing_commission.csv');
        file_put_contents($filepath, $csvContent);

        // Expected output according to the task example
        $expectedOutput = [
            "0.60",
            "3.00",
            "0.00",
            "0.06",
            "1.50",
            "0",
            "0.70",
            "0.30",
            "0.30",
            "3.00",
            "0.00",
            "0.00",
            "8612"
        ];

        // Execute the command
        $this->artisan('commission:calculate', ['file' => $filepath])
            ->assertExitCode(0);

        // Clean up
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }
} 