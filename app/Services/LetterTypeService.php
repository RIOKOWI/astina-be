<?php

namespace App\Services;

use App\Models\LetterField;
use App\Models\LetterType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class LetterTypeService
{
    public function getList(bool $includeInactive = false, int $perPage = 15): LengthAwarePaginator
    {
        return LetterType::query()
            ->with(['fields' => fn ($q) => $q->orderBy('sort_order')])
            ->when(! $includeInactive, fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function create(array $data): LetterType
    {
        return DB::transaction(function () use ($data) {
            $fields = $data['fields'] ?? [];
            unset($data['fields']);

            $letterType = LetterType::create($data + ['is_active' => $data['is_active'] ?? true]);

            foreach ($fields as $index => $fieldData) {
                $letterType->fields()->create(array_merge($fieldData, [
                    'sort_order' => $fieldData['sort_order'] ?? $index + 1,
                ]));
            }

            return $letterType->load('fields');
        });
    }

    public function update(LetterType $letterType, array $data): LetterType
    {
        return DB::transaction(function () use ($letterType, $data) {
            $fields = $data['fields'] ?? null;
            unset($data['fields']);

            $letterType->update($data);

            if ($fields !== null) {
                $letterType->fields()->delete();
                foreach ($fields as $index => $fieldData) {
                    $letterType->fields()->create(array_merge($fieldData, [
                        'sort_order' => $fieldData['sort_order'] ?? $index + 1,
                    ]));
                }
            }

            return $letterType->fresh()->load('fields');
        });
    }

    public function deactivate(LetterType $letterType): LetterType
    {
        $letterType->update(['is_active' => false]);

        return $letterType;
    }

    public function createField(LetterType $letterType, array $data): LetterField
    {
        return $letterType->fields()->create($data);
    }

    public function updateField(LetterType $letterType, LetterField $field, array $data): LetterField
    {
        $field->update($data);

        return $field;
    }

    public function deleteField(LetterType $letterType, LetterField $field): void
    {
        // Cannot delete if field has values from existing letters
        if ($field->fieldValues()->exists()) {
            abort(409, 'Field tidak dapat dihapus karena sudah digunakan oleh surat.');
        }

        $field->delete();
    }
}
