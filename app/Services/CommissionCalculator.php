<?php

namespace App\Services;

use App\Models\Transaction;

class CommissionCalculator
{
    private const DEPOSIT_COMMISSION_RATE = 0.0003; // 0.03%
    private const PRIVATE_WITHDRAW_COMMISSION_RATE = 0.003; // 0.3%
    private const BUSINESS_WITHDRAW_COMMISSION_RATE = 0.005; // 0.5%
    private const FREE_WITHDRAW_LIMIT_EUR = 1000;
    private const FREE_WITHDRAW_OPERATIONS_PER_WEEK = 3;

    private CurrencyExchangeService $exchangeService;
    private array $weeklyWithdrawals = [];

    public function __construct(CurrencyExchangeService $exchangeService)
    {
        $this->exchangeService = $exchangeService;
    }

    public function calculate(Transaction $transaction): float
    {
        if ($transaction->getOperationType() === 'deposit') {
            return $this->calculateDepositCommission($transaction);
        } elseif ($transaction->getOperationType() === 'withdraw') {
            if ($transaction->getUserType() === 'private') {
                return $this->calculatePrivateWithdrawCommission($transaction);
            } elseif ($transaction->getUserType() === 'business') {
                return $this->calculateBusinessWithdrawCommission($transaction);
            }
        }

        throw new \InvalidArgumentException('Invalid operation or user type');
    }

    private function calculateDepositCommission(Transaction $transaction): float
    {
        $commission = $transaction->getAmount() * self::DEPOSIT_COMMISSION_RATE;
        return $this->roundUp($commission, $transaction->getCurrency());
    }

    private function calculateBusinessWithdrawCommission(Transaction $transaction): float
    {
        $commission = $transaction->getAmount() * self::BUSINESS_WITHDRAW_COMMISSION_RATE;
        return $this->roundUp($commission, $transaction->getCurrency());
    }

    private function calculatePrivateWithdrawCommission(Transaction $transaction): float
    {
        $date = new \DateTime($transaction->getDate());
        $userId = $transaction->getUserId();
        $weekNumber = $date->format('oW'); // Year and week number
        $weekKey = $userId . '-' . $weekNumber;

        // Initialize weekly data if not exists
        if (!isset($this->weeklyWithdrawals[$weekKey])) {
            $this->weeklyWithdrawals[$weekKey] = [
                'operations' => 0,
                'amount_eur' => 0,
            ];
        }

        // Convert amount to EUR for limit checking
        $amountInEur = $transaction->getAmount();
        if ($transaction->getCurrency() !== 'EUR') {
            $amountInEur = $this->exchangeService->convertToEur(
                $transaction->getAmount(),
                $transaction->getCurrency(),
                $transaction->getDate()
            );
        }

        // Update weekly operations count
        $this->weeklyWithdrawals[$weekKey]['operations']++;
        
        // Check if this operation exceeds the free limits
        $operationsThisWeek = $this->weeklyWithdrawals[$weekKey]['operations'];
        $amountUsedThisWeek = $this->weeklyWithdrawals[$weekKey]['amount_eur'];
        
        // Operation is within free limits
        if ($operationsThisWeek <= self::FREE_WITHDRAW_OPERATIONS_PER_WEEK) {
            $remainingFreeAmount = self::FREE_WITHDRAW_LIMIT_EUR - $amountUsedThisWeek;
            
            // If we still have free amount
            if ($remainingFreeAmount > 0) {
                // Update used amount
                $this->weeklyWithdrawals[$weekKey]['amount_eur'] += $amountInEur;
                
                // If amount exceeds free limit
                if ($amountInEur > $remainingFreeAmount) {
                    $exceedingAmountEur = $amountInEur - $remainingFreeAmount;
                    
                    // Convert exceeding amount back to original currency
                    $exceedingAmount = $exceedingAmountEur;
                    if ($transaction->getCurrency() !== 'EUR') {
                        $exceedingAmount = $this->exchangeService->convertFromEur(
                            $exceedingAmountEur,
                            $transaction->getCurrency(),
                            $transaction->getDate()
                        );
                    }
                    
                    // Calculate commission on exceeding amount
                    $commission = $exceedingAmount * self::PRIVATE_WITHDRAW_COMMISSION_RATE;
                    return $this->roundUp($commission, $transaction->getCurrency());
                }
                
                // No exceeding amount, no commission
                return 0;
            }
        }
        
        // Update weekly amount (even when exceeding free operations limit)
        $this->weeklyWithdrawals[$weekKey]['amount_eur'] += $amountInEur;
        
        // Operation exceeds free limits, calculate commission on full amount
        $commission = $transaction->getAmount() * self::PRIVATE_WITHDRAW_COMMISSION_RATE;
        return $this->roundUp($commission, $transaction->getCurrency());
    }

    private function roundUp(float $amount, string $currency): float
    {
        $decimalPlaces = $this->getCurrencyDecimalPlaces($currency);
        $multiplier = 10 ** $decimalPlaces;
        
        return ceil($amount * $multiplier) / $multiplier;
    }

    private function getCurrencyDecimalPlaces(string $currency): int
    {
        $currencyDecimalPlaces = [
            'JPY' => 0,
            'EUR' => 2,
            'USD' => 2,
            // Add more currencies as needed
        ];

        return $currencyDecimalPlaces[$currency] ?? 2; // Default to 2 decimal places
    }
} 