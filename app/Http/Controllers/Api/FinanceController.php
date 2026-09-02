<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreFinancialTransactionRequest;
use App\Http\Resources\Finance\FinancialTransactionResource;
use App\Services\FinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    public function __construct(
        private readonly FinanceService $financeService,
    ) {}

    public function summary(Request $request): JsonResponse
    {
        $month = $request->string('month', '');
        $summary = $this->financeService->getSummary($month ? $month->toString() : null);

        return response()->json([
            'success' => true,
            'message' => 'Ringkasan keuangan.',
            'data' => $summary,
            'meta' => null,
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $type = $request->string('type', '');
        $category = $request->string('category', '');
        $dateFrom = $request->string('date_from', '');
        $dateTo = $request->string('date_to', '');
        $perPage = $request->integer('per_page', 15);

        $transactions = $this->financeService->getTransactions(
            $type ? $type->toString() : null,
            $category ? $category->toString() : null,
            $dateFrom ? $dateFrom->toString() : null,
            $dateTo ? $dateTo->toString() : null,
            $perPage,
        );

        $data = $transactions->getCollection()->map(fn ($t) => new FinancialTransactionResource($t));

        return response()->json([
            'success' => true,
            'message' => 'Daftar transaksi berhasil diambil.',
            'data' => [
                'data' => $data->toArray(),
            ],
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    public function storeTransaction(StoreFinancialTransactionRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('rt') && ! $user->hasRole('bendahara')) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses.',
                'errors' => null,
                'data' => null,
            ], 403);
        }

        $transaction = $this->financeService->createTransaction(
            $request->validated(),
            $request->user(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Transaksi berhasil dibuat.',
            'data' => new FinancialTransactionResource($transaction),
            'meta' => null,
        ], 201);
    }
}
