<?php

namespace App\Services;

use App\Models\Transaction;

class CsvFileHandler
{
    public function parseTransactions(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Failed to open file: {$filePath}");
        }

        $transactions = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== 6) {
                continue; // Skip invalid rows
            }

            $transactions[] = Transaction::fromCsvRow($row);
        }

        fclose($handle);

        return $transactions;
    }
} 