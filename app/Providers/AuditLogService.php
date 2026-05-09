<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogService {

    /**
     * Record an auditable action.
     *
     * @param string $action  e.g. "APPLICANT_CREATED", "DUPLICATE_RESOLVED"
     * @param mixed  $model   The Eloquent model affected (optional)
     * @param array  $changes ['before' => [...], 'after' => [...]] (optional)
     */
    public function log(string $action, $model = null, array $changes = []): void {
        AuditLog::create([
            'user_id'    => Auth::id(), // null for guest (applicant) actions
            'action'     => $action,
            'model_type' => $model ? class_basename($model) : null,
            'model_id'   => $model?->id,
            'changes'    => empty($changes) ? null : $changes,
            'ip_address' => Request::ip(), // SANITIZE
            'user_agent' => Request::userAgent(), //sanitize this
        ]);
    }

    // Convenience wrappers
    public function logApplicantCreated($applicant): void {
        $this->log('APPLICANT_CREATED', $applicant);
    }

    public function logApplicantUpdated($applicant, array $changes): void {
        $this->log('APPLICANT_UPDATED', $applicant, $changes); //confusing
    }

    public function logDuplicateResolved($flag, string $action): void {
        $this->log('DUPLICATE_RESOLVED_' . strtoupper($action), $flag);
    }

    public function logReportDownloaded(string $params): void {
        $this->log('REPORT_DOWNLOADED', null, ['params' => $params]);
    }

    public function logLogin($user): void {
        $this->log('USER_LOGIN', $user);
    }

    public function logLogout($user): void {
        $this->log('USER_LOGOUT', $user);
    }

    
    public function logDeactivate($user): void {
        $this->log('APPLICANT_DEACTIVATED', $user);
    }
}
