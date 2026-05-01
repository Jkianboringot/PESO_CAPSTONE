<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DuplicateFlag extends Model
{
    protected $fillable = [
        'applicant_id_new','applicant_id_existing',
        'matched_phonetic','matched_birthdate','matched_contact',
        'match_score','resolution_status',
        'resolved_by','resolution_notes','resolved_at',
    ];
    protected $casts = [
        'matched_phonetic'  => 'boolean',
        'matched_birthdate' => 'boolean',
        'matched_contact'   => 'boolean',
        'resolved_at'       => 'datetime',
    ];
    public function newApplicant() {
        return $this->belongsTo(Applicant::class, 'applicant_id_new');
    }
    public function existingApplicant() {
        return $this->belongsTo(Applicant::class, 'applicant_id_existing');
    }
    public function resolvedBy() {
        return $this->belongsTo(User::class, 'resolved_by');
    }
    public function scopePending($q) {
        return $q->where('resolution_status', 'Pending');
    }

}
