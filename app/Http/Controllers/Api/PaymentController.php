<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\ApprovePaymentRequest;
use App\Http\Requests\Payment\RejectPaymentRequest;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Requests\Payment\UploadPaymentProofRequest;
use App\Http\Resources\Payment\PaymentProofResource;
use App\Http\Resources\Payment\PaymentResource;
use App\Models\DueBill;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    public function myPayments(Request $request): JsonResponse
    {
        $residentId = $request->user()->resident_id;
        $perPage = $request->integer('per_page', 15);

        $payments = $this->paymentService->getMyPayments($residentId, $perPage);
        $data = $payments->getCollection()->map(fn ($p) => new PaymentResource($p));

        return response()->json([
            'success' => true,
            'message' => 'Daftar pembayaran berhasil diambil.',
            'data' => [
                'data' => $data->toArray(),
            ],
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
        ]);
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $dueBill = DueBill::findOrFail($request->integer('due_bill_id'));

        $payment = $this->paymentService->createPayment(
            $dueBill,
            $request->validated(),
            $request->user(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran berhasil dibuat. Silakan upload bukti pembayaran.',
            'data' => new PaymentResource($payment),
            'meta' => null,
        ], 201);
    }

    public function show(Request $request, Payment $payment): JsonResponse
    {
        $payment = $this->paymentService->getPayment($payment, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Detail pembayaran.',
            'data' => new PaymentResource($payment),
            'meta' => null,
        ]);
    }

    public function uploadProof(UploadPaymentProofRequest $request, Payment $payment): JsonResponse
    {
        $proof = $this->paymentService->uploadProof(
            $payment,
            $request->validated(),
            $request->user(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Bukti pembayaran berhasil diupload.',
            'data' => new PaymentProofResource($proof),
            'meta' => null,
        ], 201);
    }

    public function pending(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('bendahara')) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses.',
                'errors' => null,
                'data' => null,
            ], 403);
        }

        $perPage = $request->integer('per_page', 15);
        $payments = $this->paymentService->getPendingPayments($perPage);
        $data = $payments->getCollection()->map(fn ($p) => new PaymentResource($p));

        return response()->json([
            'success' => true,
            'message' => 'Daftar pembayaran pending berhasil diambil.',
            'data' => [
                'data' => $data->toArray(),
            ],
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
        ]);
    }

    public function approve(ApprovePaymentRequest $request, Payment $payment): JsonResponse
    {
        $payment = $this->paymentService->approvePayment($payment, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran berhasil disetujui.',
            'data' => new PaymentResource($payment),
            'meta' => null,
        ]);
    }

    public function reject(RejectPaymentRequest $request, Payment $payment): JsonResponse
    {
        $payment = $this->paymentService->rejectPayment(
            $payment,
            $request->user(),
            $request->string('reason')->toString(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran berhasil ditolak.',
            'data' => new PaymentResource($payment),
            'meta' => null,
        ]);
    }
}
