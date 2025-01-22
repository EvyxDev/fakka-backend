<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Fee extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id', 'amount',
    ];
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
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
