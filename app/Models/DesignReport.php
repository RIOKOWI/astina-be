<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DesignReport extends Model
{
    protected $table = 'design_report';

    const CREATED_AT = 'cdt';

    const UPDATED_AT = 'mdt';

    protected $fillable = [
        'code',
        'name',
        'content',
        'header',
        'footer',
        'rw_approval',
        'lurah_approval',
        'camat_approval',
    ];

    protected $attributes = [
        'rw_approval' => 'N',
        'lurah_approval' => 'N',
        'camat_approval' => 'N',
    ];
}
