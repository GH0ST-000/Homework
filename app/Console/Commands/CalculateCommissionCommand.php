<?php

namespace App\Console\Commands;

use App\Services\CommissionCalculator;
use App\Services\CsvFileHandler;
use Illuminate\Console\Command;

class CalculateCommissionCommand extends Command
{
    protected $signature = 'commission:calculate 
                            {file : Path to the CSV file with transactions}
                            {--detailed : Show detailed output with transaction information}';
    protected $description = 'Calculate commission fees for operations from a CSV file';

    private CsvFileHandler $csvFileHandler;
    private CommissionCalculator $commissionCalculator;

    public function __construct(
        CsvFileHandler $csvFileHandler,
        CommissionCalculator $commissionCalculator
    ) {
        parent::__construct();
        $this->csvFileHandler = $csvFileHandler;
        $this->commissionCalculator = $commissionCalculator;
    }

    public function handle()
    {
        $filePath = $this->argument('file');
        $detailed = $this->option('detailed');

        try {
            if ($detailed) {
                $this->info('Reading transactions from file: ' . $filePath);
            }
            
            $transactions = $this->csvFileHandler->parseTransactions($filePath);
            
            if (empty($transactions)) {
                $this->error('No valid transactions found in the file.');
                return 1;
            }

            if ($detailed) {
                $this->info('Found ' . count($transactions) . ' transactions to process.');
                $this->info('Calculating commission fees...');
            }

            foreach ($transactions as $index => $transaction) {
                try {
                    if ($detailed) {
                        $this->line('');
                        $this->line('Transaction #' . ($index + 1) . ':');
                        $this->line('Date: ' . $transaction->getDate());
                        $this->line('User: ' . $transaction->getUserId() . ' (' . $transaction->getUserType() . ')');
                        $this->line('Operation: ' . $transaction->getOperationType());
                        $this->line('Amount: ' . $transaction->getAmount() . ' ' . $transaction->getCurrency());
                    }
                    
                    $commission = $this->commissionCalculator->calculate($transaction);
                    
                    if ($detailed) {
                        $this->line('Commission: ' . $this->formatCommission($commission, $transaction->getCurrency()) . ' ' . $transaction->getCurrency());
                    } else {
                        // Regular output without explanation
                        $this->printCommission($commission, $transaction->getCurrency());
                    }
                } catch (\Exception $e) {
                    // If a single transaction fails, log the error but continue processing
                    $this->error('Error processing transaction #' . ($index + 1) . ': ' . $e->getMessage());
                }
            }

            return 0;
        } catch (\Exception $e) {
            $this->error('An error occurred: ' . $e->getMessage());
            
            if ($detailed) {
                $this->error('Stack trace:');
                $this->error($e->getTraceAsString());
            }
            
            return 1;
        }
    }

    private function printCommission(float $commission, string $currency): void
    {
        echo $this->formatCommission($commission, $currency) . PHP_EOL;
    }

    private function formatCommission(float $commission, string $currency): string
    {
        // Format based on currency decimal places
        if ($currency === 'JPY') {
            return (string) (int) $commission; // No decimal places for JPY
        }
        
        // For other currencies with 2 decimal places
        return number_format($commission, 2, '.', '');
    }
} 