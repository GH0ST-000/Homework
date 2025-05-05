<?php

namespace App\Models;

class Transaction
{
    private string $date;
    private int $userId;
    private string $userType;
    private string $operationType;
    private float $amount;
    private string $currency;

    public function __construct(
        string $date,
        int $userId,
        string $userType,
        string $operationType,
        float $amount,
        string $currency
    ) {
        $this->date = trim($date);
        $this->userId = $userId;
        $this->userType = trim($userType);
        $this->operationType = trim($operationType);
        $this->amount = $amount;
        $this->currency = trim($currency);
    }

    public function getDate(): string
    {
        return $this->date;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getUserType(): string
    {
        return $this->userType;
    }

    public function getOperationType(): string
    {
        return $this->operationType;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public static function fromCsvRow(array $row): self
    {
        return new self(
            $row[0], // date
            (int) $row[1], // user_id
            $row[2], // user_type
            $row[3], // operation_type
            (float) $row[4], // amount
            $row[5] // currency
        );
    }
} 