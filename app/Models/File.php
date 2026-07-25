<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'file_url',
        'previous_file_url',
        'file_type',
        'file_size',
        'dossier_id',
        'user_id',
    ];

    // File appartient à un Dossier
    public function dossier()
    {
        return $this->belongsTo(Dossier::class);
    }

    // File appartient à un User
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
