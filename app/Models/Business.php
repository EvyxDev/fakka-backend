<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Business extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function vendor()
    {
        return $this->hasMany(Vendor::class);
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
