<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Training extends Model
{
    protected $fillable = [
        'applicant_id',
        'title',
        'provider',
        'year',
    ];

    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }
}
