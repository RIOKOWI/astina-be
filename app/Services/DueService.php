<?php

namespace App\Services;

use App\Models\Due;
use App\Models\DueBill;
use App\Models\Resident;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DueService
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function getList(bool $includeInactive = false, int $perPage = 15): LengthAwarePaginator
    {
        $query = Due::query()->orderByDesc('created_at');

        if (! $includeInactive) {
            $query->where('is_active', true);
        }

        return $query->paginate($perPage);
    }

    public function getDue(Due $due): Due
    {
        $due->load('dueBills.resident');

        return $due;
    }

    public function create(array $data): Due
    {
        return Due::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'amount' => $data['amount'],
            'frequency' => $data['frequency'] ?? 'monthly',
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function update(Due $due, array $data): Due
    {
        $due->update([
            'name' => $data['name'] ?? $due->name,
            'description' => $data['description'] ?? $due->description,
            'amount' => $data['amount'] ?? $due->amount,
            'frequency' => $data['frequency'] ?? $due->frequency,
            'start_date' => $data['start_date'] ?? $due->start_date,
            'end_date' => $data['end_date'] ?? $due->end_date,
            'is_active' => $data['is_active'] ?? $due->is_active,
        ]);

        return $due->fresh();
    }

    public function deactivate(Due $due): Due
    {
        $due->update(['is_active' => false]);

        return $due->fresh();
    }

    public function generateMonthlyDueBills(int $year, int $month): int
    {
        $due = Due::query()
            ->where('is_active', true)
            ->where('frequency', 'monthly')
            ->where(fn ($q) => $q->whereNull('start_date')->orWhere('start_date', '<=', "{$year}-{$month}-01"))
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', "{$year}-{$month}-01"))
            ->first();

        if (! $due) {
            return 0;
        }

        $dueDate = sprintf('%04d-%02d-05', $year, $month);

        $activeResidents = Resident::query()
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('left_at')->orWhere('left_at', '>=', "{$year}-{$month}-01"))
            ->get();

        $created = 0;

        DB::transaction(function () use ($due, $activeResidents, $dueDate, $year, $month, &$created) {
            foreach ($activeResidents as $resident) {
                $exists = DueBill::query()
                    ->where('due_id', $due->id)
                    ->where('resident_id', $resident->id)
                    ->whereYear('due_date', $year)
                    ->whereMonth('due_date', $month)
                    ->exists();

                if (! $exists) {
                    DueBill::create([
                        'due_id' => $due->id,
                        'resident_id' => $resident->id,
                        'amount' => $due->amount,
                        'due_date' => $dueDate,
                        'status' => 'unpaid',
                    ]);
                    $created++;
                }
            }
        });

        return $created;
    }

    public function getMyDueBills(int $residentId, int $perPage = 15): LengthAwarePaginator
    {
        return DueBill::query()
            ->with('due')
            ->where('resident_id', $residentId)
            ->orderByDesc('due_date')
            ->paginate($perPage);
    }
}
