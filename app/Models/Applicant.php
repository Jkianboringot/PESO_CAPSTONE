<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Applicant extends Model
{
       protected $fillable = [
        'reference_id','last_name','first_name','middle_name',
        'birthdate','sex','civil_status','contact_number','email',
        'address','barangay_id','status','is_active',
        'consent_given','consent_given_at','last_name_metaphone',
    ];
 
    protected $casts = [
        'birthdate'       => 'date',
        'consent_given'   => 'boolean',
        'is_active'       => 'boolean',
        'consent_given_at'=> 'datetime',
    ];
 
    // Auto-generate reference_id and metaphone before creating
    protected static function boot(): void {
        parent::boot();
        static::creating(function ($applicant) {
            $applicant->reference_id = 'PESO-' . strtoupper(uniqid());
            $applicant->last_name_metaphone = metaphone($applicant->last_name);
        });
    }
 
    // Relationships
    public function barangay() { return $this->belongsTo(Barangay::class); }
    public function education() { return $this->hasOne(Education::class); }
    public function skills() {
        return $this->belongsToMany(Skill::class, 'applicant_skill')
                    ->withPivot('proficiency_level');
    }
    public function duplicateFlagsAsNew() {
        return $this->hasMany(DuplicateFlag::class, 'applicant_id_new');
    }
 
    // Scopes for filtering (used by Livewire components)
    public function scopeActive(Builder $q) { return $q->where('is_active', true); }
    public function scopeByBarangay(Builder $q, $id) {
        return $id ? $q->where('barangay_id', $id) : $q;
    }
    public function scopeByDateRange(Builder $q, $from, $to) {
        if ($from) $q->whereDate('created_at', '>=', $from);
        if ($to)   $q->whereDate('created_at', '<=', $to);
        return $q;
    }
    public function scopeByEducation(Builder $q, $level) {
        if (!$level) return $q;
        return $q->whereHas('education', fn($e) => $e->where('highest_level',$level));
    }
    public function scopeBySkillCategory(Builder $q, $categoryId) {
        if (!$categoryId) return $q;
        return $q->whereHas('skills', fn($s) => $s->where('skill_category_id',$categoryId));
    }
 
    // Full name accessor
    public function getFullNameAttribute(): string {
        return trim("{$this->last_name}, {$this->first_name} {$this->middle_name}");
    }

}
