<?php

namespace App\Livewire;

use App\Models\AuditLog as ModelsAuditLog;
use Livewire\Component;
use Livewire\WithPagination;

class AuditLog extends Component
{
    use WithPagination;

    public function render()
    {
        abort_if( // TODO-LATER - dont use this, use a proper message error , this is just for testing
            // REVIEW - although this is protected by route, is that enough? learn about this see if this can still be access
            !auth()->user()->hasRole([ 'admin']),
            403
        );
        $auditLogs = ModelsAuditLog::orderBy('created_at')->paginate(20);
        return view('livewire.audit-log', ['auditLogs' => $auditLogs]);
    }
}
