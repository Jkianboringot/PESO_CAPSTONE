<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Certification extends Model
{
    protected $fillable = [
        'applicant_id',
        'name',
        'issuing_body',
        'year_issued',
        'expiry_year',
    ];

    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }
}
