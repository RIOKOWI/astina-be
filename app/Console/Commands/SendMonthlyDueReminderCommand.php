<?php

namespace App\Console\Commands;

use App\Models\DueBill;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendMonthlyDueReminderCommand extends Command
{
    protected $signature = 'finance:send-monthly-reminder {year?} {month?}';

    protected $description = 'Kirim notifikasi pengingat iuran bulanan untuk warga yang belum bayar';

    public function __construct(
        private readonly NotificationService $notificationService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $year = $this->argument('year') ?? now()->year;
        $month = $this->argument('month') ?? now()->month;

        $year = (int) $year;
        $month = (int) $month;

        $unpaidBills = DueBill::query()
            ->whereYear('due_date', $year)
            ->whereMonth('due_date', $month)
            ->where('status', 'unpaid')
            ->whereHas('resident.user')
            ->with('resident.user')
            ->get();

        if ($unpaidBills->isEmpty()) {
            $this->info('Tidak ada tagihan unpaid.');

            return Command::SUCCESS;
        }

        $bar = $this->output->createProgressBar($unpaidBills->count());
        $bar->start();

        foreach ($unpaidBills as $bill) {
            $user = $bill->resident->user;

            $this->notificationService->sendToUser($user, 'due_reminder', 'Pengingat Iuran Bulanan', 'Anda memiliki tagihan iuran yang belum dibayar.', [
                'type' => 'due_reminder',
                'due_bill_id' => (string) $bill->id,
                'amount' => (string) $bill->amount,
            ]);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        Log::info('Monthly due reminder sent', [
            'year' => $year,
            'month' => $month,
            'count' => $unpaidBills->count(),
        ]);

        $this->info("Pengingat berhasil dikirim ke {$unpaidBills->count()} warga.");

        return Command::SUCCESS;
    }
}
