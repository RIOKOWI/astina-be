<?php

namespace App\Services;

use App\Models\DueBill;
use App\Models\FinancialTransaction;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly ImageService $imageService,
    ) {}

    public function createPayment(DueBill $dueBill, array $data, User $user): Payment
    {
        $hasPending = Payment::query()
            ->where('due_bill_id', $dueBill->id)
            ->where('resident_id', $user->resident_id)
            ->where('status', 'pending')
            ->exists();

        if ($hasPending) {
            abort(409, 'Anda sudah memiliki pembayaran pending untuk tagihan ini.');
        }

        if ((float) $data['amount'] !== (float) $dueBill->amount) {
            abort(422, 'Jumlah pembayaran harus sesuai dengan tagihan.');
        }

        $payment = Payment::create([
            'due_bill_id' => $dueBill->id,
            'resident_id' => $user->resident_id,
            'amount' => $data['amount'],
            'method' => $data['method'] ?? 'transfer',
            'status' => 'pending',
            'paid_at' => now(),
        ]);

        return $payment;
    }

    public function uploadProof(Payment $payment, array $data, User $user): PaymentProof
    {
        if ($payment->resident_id !== $user->resident_id) {
            abort(403, 'Anda tidak memiliki akses ke pembayaran ini.');
        }

        if ($payment->status !== 'pending') {
            abort(409, 'Pembayaran sudah diproses.');
        }

        $file = $data['file'];
        $directory = "payment-proofs/{$payment->id}";
        $isImage = in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true);

        if ($isImage) {
            $result = $this->imageService->process($file);
            $path = "{$directory}/{$result->filename}";
            $mimeType = $result->mimeType;
            $fileSize = $result->fileSize;
        } else {
            // PDF: store directly without processing
            $filename = Str::uuid()->toString().'.pdf';
            $path = "{$directory}/{$filename}";
            $mimeType = $file->getMimeType();
            $fileSize = $file->getSize();
        }

        try {
            if ($isImage) {
                Storage::disk('public')->put($path, $result->contents);
            } else {
                Storage::disk('public')->put($path, file_get_contents($file->getPathname()));
            }

            $proof = PaymentProof::create([
                'payment_id' => $payment->id,
                'path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
                'created_at' => now(),
            ]);

            $this->notifyBendahara($payment);

            return $proof;
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);
            throw $e;
        }
    }

    public function approvePayment(Payment $payment, User $bendahara): Payment
    {
        if ($payment->status !== 'pending') {
            abort(409, 'Pembayaran sudah diproses.');
        }

        $hasProof = PaymentProof::query()
            ->where('payment_id', $payment->id)
            ->exists();

        if (! $hasProof) {
            abort(422, 'Bukti pembayaran belum diupload.');
        }

        $existingTx = FinancialTransaction::query()
            ->where('payment_id', $payment->id)
            ->exists();

        if ($existingTx) {
            abort(409, 'Transaksi keuangan sudah ada untuk pembayaran ini.');
        }

        DB::transaction(function () use ($payment, $bendahara) {
            $payment->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $bendahara->id,
            ]);

            FinancialTransaction::create([
                'created_by' => $bendahara->id,
                'payment_id' => $payment->id,
                'type' => 'income',
                'amount' => $payment->amount,
                'category' => 'iuran',
                'description' => 'Iuran warga',
                'transaction_at' => now(),
            ]);
        });

        $payment->refresh();
        $payment->load('resident');

        Log::info('Payment approved', [
            'payment_id' => $payment->id,
            'approved_by' => $bendahara->id,
            'amount' => $payment->amount,
        ]);

        $this->notifyApproval($payment);

        return $payment;
    }

    public function rejectPayment(Payment $payment, User $bendahara, string $reason): Payment
    {
        if ($payment->status !== 'pending') {
            abort(409, 'Pembayaran sudah diproses.');
        }

        $payment->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
        ]);

        Log::info('Payment rejected', [
            'payment_id' => $payment->id,
            'rejected_by' => $bendahara->id,
            'reason' => $reason,
        ]);

        $this->notifyRejection($payment, $reason);

        return $payment->fresh();
    }

    public function getMyPayments(int $residentId, int $perPage = 15): LengthAwarePaginator
    {
        return Payment::query()
            ->with(['dueBill.due', 'proofs'])
            ->where('resident_id', $residentId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function getPendingPayments(int $perPage = 15): LengthAwarePaginator
    {
        return Payment::query()
            ->with(['resident', 'dueBill.due', 'proofs'])
            ->where('status', 'pending')
            ->whereHas('proofs')
            ->orderByDesc('paid_at')
            ->paginate($perPage);
    }

    public function getPayment(Payment $payment, User $user): Payment
    {
        $isBendahara = $user->hasRole('bendahara');
        $isRt = $user->hasRole('rt');

        if (! $isBendahara && ! $isRt && $payment->resident_id !== $user->resident_id) {
            abort(403, 'Anda tidak memiliki akses ke pembayaran ini.');
        }

        $payment->load(['resident', 'dueBill.due', 'proofs', 'approver']);

        return $payment;
    }

    private function notifyBendahara(Payment $payment): void
    {
        $residentName = $payment->resident?->full_name ?? 'Warga';

        $this->notificationService->sendToUsersWithRole('bendahara', 'payment_submitted', 'Pembayaran Baru', "{$residentName} mengirim bukti pembayaran iuran.", [
            'type' => 'payment_submitted',
            'payment_id' => (string) $payment->id,
        ]);
    }

    private function notifyApproval(Payment $payment): void
    {
        if (! $payment->resident?->user) {
            return;
        }

        $this->notificationService->sendToUser($payment->resident->user, 'payment_approved', 'Pembayaran Disetujui', 'Pembayaran iuran Anda telah disetujui.', [
            'type' => 'payment_approved',
            'payment_id' => (string) $payment->id,
        ]);
    }

    private function notifyRejection(Payment $payment, string $reason): void
    {
        if (! $payment->resident?->user) {
            return;
        }

        $this->notificationService->sendToUser($payment->resident->user, 'payment_rejected', 'Pembayaran Ditolak', "Pembayaran iuran Anda ditolak. {$reason}", [
            'type' => 'payment_rejected',
            'payment_id' => (string) $payment->id,
        ]);
    }
}
