<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Seminar extends Model
{
    protected $fillable = [
        'applicant_id',
        'title',
        'organizer',
        'year',
    ];

    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }
}
