<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IncentivosHistorial extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'porcentaje',
        'fecha_indice',
        'estado',
    ];

        // one to many inversa
        public function user()
        {
            return $this->belongsTo(User::class);
        }
}
