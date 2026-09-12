<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssetService
{
    public function getList(
        ?string $search = null,
        ?string $category = null,
        ?string $condition = null,
        ?string $status = null,
        int $perPage = 15,
    ): LengthAwarePaginator {
        $query = Asset::query()->orderByDesc('created_at');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if ($category) {
            $query->where('category', $category);
        }

        if ($condition) {
            $query->where('condition', $condition);
        }

        if ($status) {
            $query->where('status', $status);
        }

        return $query->paginate($perPage);
    }

    public function create(array $data, User $user): Asset
    {
        return DB::transaction(function () use ($data, $user) {
            $lastAsset = Asset::query()->lockForUpdate()->orderByDesc('id')->first();
            $nextNumber = $lastAsset ? ((int) substr($lastAsset->code, 4) + 1) : 1;
            $code = 'AST-'.str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);

            $asset = Asset::create([
                'code' => $code,
                'name' => $data['name'],
                'category' => $data['category'],
                'description' => $data['description'] ?? null,
                'quantity' => 0,
                'unit' => $data['unit'],
                'purchase_price' => $data['purchase_price'] ?? null,
                'purchase_date' => $data['purchase_date'] ?? null,
                'condition' => $data['condition'] ?? 'new',
                'status' => $data['status'] ?? 'available',
            ]);

            if (isset($data['quantity']) && $data['quantity'] > 0) {
                AssetMovement::create([
                    'asset_id' => $asset->id,
                    'created_by' => $user->id,
                    'type' => 'in',
                    'quantity' => $data['quantity'],
                    'description' => 'Stok awal',
                    'movement_at' => now(),
                    'created_at' => now(),
                ]);

                $asset->update(['quantity' => $data['quantity']]);
            }

            Log::info('Asset created', ['asset_id' => $asset->id, 'code' => $code, 'user_id' => $user->id]);

            return $asset;
        });
    }

    public function getDetail(Asset $asset): Asset
    {
        return $asset->load(['movements.creator']);
    }

    public function update(Asset $asset, array $data): Asset
    {
        $updateData = collect($data)->except(['quantity'])->toArray();

        $asset->update($updateData);

        Log::info('Asset updated', ['asset_id' => $asset->id, 'user_id' => auth()->id()]);

        return $asset->fresh();
    }

    public function deactivate(Asset $asset): Asset
    {
        $asset->update(['status' => 'retired']);

        Log::info('Asset deactivated', ['asset_id' => $asset->id, 'user_id' => auth()->id()]);

        return $asset->fresh();
    }

    public function createMovement(Asset $asset, array $data, User $user): AssetMovement
    {
        return DB::transaction(function () use ($asset, $data, $user) {
            $asset = Asset::lockForUpdate()->findOrFail($asset->id);

            $quantity = (int) $data['quantity'];
            $type = $data['type'];
            $currentQty = $asset->quantity;

            if ($type === 'out' && $quantity > $currentQty) {
                Log::warning('Movement rejected: insufficient stock', [
                    'asset_id' => $asset->id,
                    'type' => $type,
                    'requested' => $quantity,
                    'available' => $currentQty,
                    'user_id' => $user->id,
                ]);
                abort(409, 'Stok tidak mencukupi.');
            }

            if ($type === 'adjustment') {
                $newQty = $currentQty + $quantity;
                if ($newQty < 0) {
                    Log::warning('Movement rejected: adjustment would make stock negative', [
                        'asset_id' => $asset->id,
                        'adjustment' => $quantity,
                        'current' => $currentQty,
                        'user_id' => $user->id,
                    ]);
                    abort(409, 'Stok tidak mencukupi.');
                }
                $asset->quantity = $newQty;
            } elseif ($type === 'in') {
                $asset->quantity = $currentQty + $quantity;
            } else {
                $asset->quantity = $currentQty - $quantity;
            }

            $asset->save();

            $movement = AssetMovement::create([
                'asset_id' => $asset->id,
                'created_by' => $user->id,
                'type' => $type,
                'quantity' => $quantity,
                'description' => $data['description'] ?? null,
                'movement_at' => $data['movement_at'] ?? now(),
                'created_at' => now(),
            ]);

            Log::info('Asset movement created', [
                'asset_id' => $asset->id,
                'movement_id' => $movement->id,
                'type' => $type,
                'quantity' => $quantity,
                'new_stock' => $asset->quantity,
                'user_id' => $user->id,
            ]);

            return $movement;
        });
    }

    public function getMovements(
        Asset $asset,
        ?string $type = null,
        ?string $from = null,
        ?string $to = null,
        ?string $search = null,
        int $perPage = 15,
    ): LengthAwarePaginator {
        $query = $asset->movements()
            ->with('creator')
            ->orderByDesc('movement_at')
            ->orderByDesc('created_at');

        if ($type) {
            $query->where('type', $type);
        }

        if ($from) {
            $query->whereDate('movement_at', '>=', $from);
        }

        if ($to) {
            $query->whereDate('movement_at', '<=', $to);
        }

        if ($search) {
            $query->where('description', 'like', "%{$search}%");
        }

        return $query->paginate($perPage);
    }
}
