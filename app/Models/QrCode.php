<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class QrCode extends Model
{
    use HasFactory;
    protected $fillable = [
        'sender_id', 'receiver_id', 'model_of_sender', 'model_of_receiver', 'qr_code', 'amount', 'status',
    ];
    
    public function sender()
    {
        return $this->morphTo();
    }
    
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
