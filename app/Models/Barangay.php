<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Barangay extends Model
{
    use HasFactory;
      protected $fillable = ['name','municipality_id'];
    public function municipality() { return $this->belongsTo(Municipality::class); }
    public function applicants() { return $this->hasMany(Applicant::class); }

}
