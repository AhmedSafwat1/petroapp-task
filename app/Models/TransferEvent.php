<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransferEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'event_id',
        'station_id',
        'amount',
        'status',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }
}
