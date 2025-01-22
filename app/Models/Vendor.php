<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Vendor extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable;


    protected $fillable = [
        'name',
        'phone',
        'pincode',
        'password',
        'user_id',
        'balance',
    ];

    public function qrCodes()
    {
        return $this->hasMany(QrCode::class);
    }

    // A vendor can be involved in many transactions
    public function transactions()
    {
        return $this->morphMany(Transaction::class, 'sender');
    }
    public function businesses()
    {
        return $this->belongsTo(Business::class);
    }

    protected $hidden = [
        'password',
        'remember_token',
    ];
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }
    public function getJWTCustomClaims()
    {
        return [];
    }

}
