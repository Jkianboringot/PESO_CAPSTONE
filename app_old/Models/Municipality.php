<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Municipality extends Model {
    protected $fillable = ['name','province'];
    public function barangays() { return $this->hasMany(Barangay::class); }
}
