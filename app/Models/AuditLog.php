<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Service;
use App\Models\Axe;
use App\Models\Dossier;

class AuditLog extends Model
{
    protected $fillable = [
        'action',
        'actor_user_id',
        'entity_type',
        'entity_id',
        'service_id',
        'axe_id',
        'dossier_id',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function axe()
    {
        return $this->belongsTo(Axe::class);
    }

    public function dossier()
    {
        return $this->belongsTo(Dossier::class);
    }
}
