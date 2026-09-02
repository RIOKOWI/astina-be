<?php

namespace App\Services;

use App\Models\FinancialTransaction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class FinanceService
{
    public function getSummary(?string $month = null): array
    {
        $query = FinancialTransaction::query();

        if ($month) {
            $query->whereYear('transaction_at', substr($month, 0, 4))
                ->whereMonth('transaction_at', substr($month, 5, 2));
        }

        $totalIncome = (clone $query)->where('type', 'income')->sum('amount');
        $totalExpense = (clone $query)->where('type', 'expense')->sum('amount');

        $allTimeIncome = FinancialTransaction::where('type', 'income')->sum('amount');
        $allTimeExpense = FinancialTransaction::where('type', 'expense')->sum('amount');

        return [
            'balance' => $allTimeIncome - $allTimeExpense,
            'total_income' => (float) $totalIncome,
            'total_expense' => (float) $totalExpense,
        ];
    }

    public function getTransactions(
        ?string $type = null,
        ?string $category = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        int $perPage = 15,
    ): LengthAwarePaginator {
        $query = FinancialTransaction::query()->with('creator');

        if ($type) {
            $query->where('type', $type);
        }

        if ($category) {
            $query->where('category', $category);
        }

        if ($dateFrom) {
            $query->whereDate('transaction_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('transaction_at', '<=', $dateTo);
        }

        return $query->orderByDesc('transaction_at')->paginate($perPage);
    }

    public function createTransaction(array $data, User $user): FinancialTransaction
    {
        if ($data['type'] === 'expense') {
            $this->validateExpenseBalance($data['amount']);
        }

        return FinancialTransaction::create([
            'created_by' => $user->id,
            'payment_id' => null,
            'type' => $data['type'],
            'amount' => $data['amount'],
            'category' => $data['category'],
            'description' => $data['description'] ?? null,
            'transaction_at' => $data['transaction_at'] ?? now(),
        ]);
    }

    private function validateExpenseBalance(float $amount): void
    {
        $income = FinancialTransaction::where('type', 'income')->sum('amount');
        $expense = FinancialTransaction::where('type', 'expense')->sum('amount');
        $balance = $income - $expense;

        if ($amount > $balance) {
            abort(409, 'Saldo tidak mencukupi.');
        }
    }
}
