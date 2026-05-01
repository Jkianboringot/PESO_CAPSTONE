<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
       protected $fillable = ['name','skill_category_id'];
    public function category() { return $this->belongsTo(SkillCategory::class, 'skill_category_id'); }
    public function applicants() {
        return $this->belongsToMany(Applicant::class, 'applicant_skill')
                    ->withPivot('proficiency_level');
    }

}
