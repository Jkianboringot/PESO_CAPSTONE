<?php

namespace Tests\Feature\Concerns;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;

/**
 * Shared setup for the admin-only CRUD tests.
 *
 * The app uses Spatie-style roles (hasRole / syncRoles / role:admin middleware),
 * so users get roles through assignRole(), NOT a role_id column.
 */
trait SetsUpAdminAndStaff
{
    protected User $admin;
    protected User $staff;

    protected function setUpUsers(): void
    {
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('staff', 'web');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->staff = User::factory()->create();
        $this->staff->assignRole('staff');
    }

    /**
     * Assert an audit row exists. Checks action + actor (+ optional model id)
     * instead of model_type, which depends on how AuditLogService stores it.
     */
    protected function assertAudited(string $action, ?int $modelId = null): void
    {
        $query = AuditLog::where('action', $action)->where('user_id', $this->admin->id);

        if ($modelId !== null) {
            $query->where('model_id', $modelId);
        }

        $this->assertTrue($query->exists(), "Expected audit log entry [{$action}] was not written.");
    }
}
