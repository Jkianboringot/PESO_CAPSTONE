<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Education extends Model
{
    protected $fillable = [
        'applicant_id','highest_level','course_program',
        'school_name','year_graduated',
    ];
    public function applicant() { return $this->belongsTo(Applicant::class); }

}
