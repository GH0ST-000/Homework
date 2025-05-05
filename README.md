# Transaction Commission Calculator

This Laravel application calculates commission fees for financial operations (deposits and withdrawals) based on defined rules.

## Requirements

- PHP 8.1+
- Composer

## Installation

1. Clone this repository:

```bash
git clone <repository-url>
cd <repository-directory>
```

2. Install dependencies:

```bash
composer install
```

3. Copy the environment file:

```bash
cp .env.example .env
```

4. Generate application key:

```bash
php artisan key:generate
```

## Usage

The application provides a command to calculate commission fees from a CSV file. 

### Running the Command

```bash
php artisan commission:calculate input.csv
```

Where `input.csv` is the path to your CSV file with transactions.

For detailed output with information about each transaction:

```bash
php artisan commission:calculate input.csv --detailed
```

### CSV File Format

The CSV file should contain the following columns in this order:

1. Date (Y-m-d format)
2. User ID (number)
3. User type ("private" or "business")
4. Operation type ("deposit" or "withdraw")
5. Amount (e.g., "200.00" or "300")
6. Currency (e.g., "EUR", "USD", "JPY")

### Example

```
2016-01-05,1,private,deposit,200.00,EUR
2016-01-06,2,business,withdraw,300.00,EUR
2016-01-06,1,private,withdraw,30000,JPY
```

## Testing

Run the tests with:

```bash
php artisan test
```

## Commission Rules

### Deposit Rule

- All deposits are charged 0.03% of deposit amount.

### Withdraw Rules

#### Private Clients

- Commission fee - 0.3% from withdrawn amount.
- 1000.00 EUR for a week (from Monday to Sunday) is free of charge for the first 3 withdraw operations per week. 4th and subsequent operations are calculated at 0.3%.
- If total free of charge amount is exceeded, commission is calculated only for the exceeded amount.

#### Business Clients

- Commission fee - 0.5% from withdrawn amount.

## Currency Exchange

- For operations not in EUR, the application will attempt to get exchange rates from the Exchange Rates API.
- In case the API is unavailable, the application will fall back to hardcoded rates:
  - EUR:USD - 1:1.1497
  - EUR:JPY - 1:129.53
- Exchange rates are cached to minimize API calls.
- Commission fee is always calculated in the currency of the operation.
- Commission fees are rounded up to currency's decimal places.

## Architecture

The application follows a service-oriented architecture:

- **Transaction Model**: Represents a financial transaction.
- **CommissionCalculator Service**: Calculates the commission fees.
- **CurrencyExchangeService**: Handles currency conversions and exchange rate retrieval.
- **CsvFileHandler Service**: Parses CSV files into Transaction objects.
- **CalculateCommissionCommand**: Laravel console command to orchestrate the process.

This design allows for easy extension and modification without changing the core system. For example:
- To add a new currency, simply update the fallback rates array
- To change commission rules, modify the constants in the CommissionCalculator
- To support a different file format, create a new file handler class
# Homework
