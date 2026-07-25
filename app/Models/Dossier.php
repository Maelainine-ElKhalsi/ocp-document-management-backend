<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dossier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'axe_id',
        'user_id',
    ];

    // Dossier appartient à un Axe
    public function axe()
    {
        return $this->belongsTo(Axe::class);
    }

    // Dossier appartient à un User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Dossier a plusieurs Files
    public function files()
    {
        return $this->hasMany(File::class);
    }
}
