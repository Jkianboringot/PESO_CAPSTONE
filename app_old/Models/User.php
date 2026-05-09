<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable {
    use Notifiable;

    protected $fillable = ['name','email','password','role_id','is_active'];
    protected $hidden   = ['password','remember_token'];
    protected $casts    = ['is_active' => 'boolean', 'last_login_at' => 'datetime'];

    public function role()      { return $this->belongsTo(Role::class); }
    public function auditLogs() { return $this->hasMany(AuditLog::class); }

    public function hasRole(string $slug): bool { return $this->role?->slug === $slug; }
    public function isAdmin(): bool { return $this->hasRole('admin'); }
    public function isStaff(): bool { return $this->hasRole('staff'); }
}
