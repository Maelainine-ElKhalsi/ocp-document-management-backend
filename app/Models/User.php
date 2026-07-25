<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'service_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    // User appartient à un Service
    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    // User a plusieurs Dossiers
    public function dossiers()
    {
        return $this->hasMany(Dossier::class);
    }

    // User a plusieurs Files
    public function files()
    {
        return $this->hasMany(File::class);
    }

    public function axes()
    {
        return $this->belongsToMany(Axe::class)->withTimestamps();
    }

    // Helper — vérifier le role
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isChefService()
    {
        return $this->role === 'chef_service';
    }

    public function isTechnicien()
    {
        return $this->role === 'technicien';
    }
}
