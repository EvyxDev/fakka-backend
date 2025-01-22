<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Transaction extends Model
{
    use HasFactory;
    protected $fillable = [
        'sender_id', 'sender_type', 'receiver_id', 'receiver_type', 'amount', 'status',
    ];

    // Polymorphic relationship to get the sender (user or vendor)
    public function sender()
    {
        return $this->morphTo();
    }

    // Polymorphic relationship to get the receiver (user or vendor)
    public function receiver()
    {
        return $this->morphTo();
    }
    public function getCreatedAtAttribute($value)
    {
        return Carbon::parse($value)->format('d M Y, h:i A'); 
    }
        
    public function getUpdatedAtAttribute($value)
    {
        return Carbon::parse($value)->format('d M Y, h:i A'); 
    }
}

