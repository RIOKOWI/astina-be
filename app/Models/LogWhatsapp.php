<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogWhatsapp extends Model
{
    protected $table = 'log_whatsapp';

    public $timestamps = false;

    protected $fillable = [
        'id_warga',
        'id_message',
        'message',
        'incoming_time',
        'replied',
    ];

    protected function casts(): array
    {
        return [
            'incoming_time' => 'datetime',
        ];
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class, 'id_warga');
    }
}
