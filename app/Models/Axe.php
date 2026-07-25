<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Axe extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'service_id',
    ];

    // Axe appartient à un Service
    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    // Axe a plusieurs Dossiers
    public function dossiers()
    {
        return $this->hasMany(Dossier::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
