<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
    ];

    // Service a plusieurs Axes
    public function axes()
    {
        return $this->hasMany(Axe::class);
    }

    // Service a plusieurs Users
    public function users()
    {
        return $this->hasMany(User::class);
    }
}
