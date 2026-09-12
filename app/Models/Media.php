<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Media extends Model
{
    use HasFactory;

    public const COLLECTION_KTP = 'ktp';

    public const COLLECTION_KK = 'kk';

    protected $fillable = [
        'model_type',
        'model_id',
        'collection',
        'disk',
        'path',
        'file_name',
        'mime_type',
        'file_size',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'model' => MorphTo::class,
        ];
    }
}
