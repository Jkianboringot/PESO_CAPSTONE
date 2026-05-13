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
        $auditLogs=ModelsAuditLog::orderBy('created_at')->paginate(20);
        return view('livewire.audit-log',['auditLogs'=>$auditLogs]);
    }
}
