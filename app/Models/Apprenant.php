<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Apprenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'nom_tuteur',
        'prenom_tuteur',
        'photocopie_cni',
        'diplome',
        'contact_tuteur',
        'extrait_naissance',
        'casier_judiciaire',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
