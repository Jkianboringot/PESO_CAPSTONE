<?php

namespace App\Livewire;

use App\Models\DuplicateFlag;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class DuplicateReview extends Component
{
    use WithPagination;

    private const ACTIONS = ['Merged', 'Retained Both', 'Deleted'];

    public ?int $reviewingFlagId = null;
    public ?DuplicateFlag $activeFlag = null;
    public string $resolutionNotes = '';

    public function mount(): void
    {
        // Only staff/admin may even see this page
        abort_unless($this->canReview(), 403);
    }

    private function canReview(): bool
    {
        return auth()->check() && auth()->user()->hasRole(['staff', 'admin']);
    }

    // ---------- TOAST HELPER ----------
    private function notify(string $type, string $message): void
    {
        $this->dispatch('notify', type: $type, message: $message);
    }

    public function openFlag(int $flagId): void
    {
        if (! $this->canReview()) {
            $this->notify('error', 'You are not allowed to review duplicate flags.');
            return;
        }

        try {
            // pending() = only flags that are still unresolved.
            // find() returns null when nothing matches, and Laravel only runs the
            // eager-load queries when a flag was actually found.
            $flag = DuplicateFlag::pending()->with([
                'newApplicant.barangay',
                'newApplicant.education',
                'newApplicant.skills.category',
                'existingApplicant.barangay',
                'existingApplicant.education',
                'existingApplicant.skills.category',
            ])->find($flagId);
        } catch (\Throwable $e) {
            report($e);
            $this->notify('error', 'Could not open the flag. Please try again.');
            return;
        }

        if (! $flag) {
            $this->closeFlag();
            $this->notify('error', 'This flag was already resolved or no longer exists.');
            return;
        }

        $this->reviewingFlagId = $flag->id;
        $this->activeFlag      = $flag;
        $this->resolutionNotes = '';
        $this->resetValidation();
    }

    public function closeFlag(): void
    {
        $this->reset(['reviewingFlagId', 'activeFlag', 'resolutionNotes']);
        $this->resetValidation();
    }

    public function resolve(string $action, AuditLogService $audit): void
    {
        if (! $this->canReview()) {
            $this->notify('error', 'You are not allowed to resolve duplicate flags.');
            return;
        }

        if (! in_array($action, self::ACTIONS, true)) {
            $this->notify('error', 'Invalid action.');
            return;
        }

        if (! $this->reviewingFlagId) {
            $this->notify('error', 'Open a flag first.');
            return;
        }

        try {
            $this->validate(
                ['resolutionNotes' => ['nullable', 'string', 'max:1000']],
                ['resolutionNotes.max' => 'Notes must not exceed 1000 characters.']
            );
        } catch (ValidationException $e) {
            $this->notify('error', 'Please fix the highlighted fields.');
            throw $e; // keeps the inline error
        }

        $flagId = $this->reviewingFlagId;
        $notes  = trim($this->resolutionNotes);

        try {
            $resolved = DB::transaction(function () use ($flagId, $action, $notes, $audit) {
                // Lock the row: if two staff click at the same time, the second one waits,
                // then finds the flag is no longer pending and stops.
                $flag = DuplicateFlag::pending()->lockForUpdate()->find($flagId);

                if (! $flag) {
                    return false; // someone else already resolved it
                }

                // "Retained Both" = different people, so no applicant change
                if (in_array($action, ['Merged', 'Deleted'], true)) {
                    $flag->newApplicant->update(['is_active' => false, 'status' => 'Inactive']);
                }

                $flag->update([
                    'resolution_status' => $action,
                    'resolved_by'       => auth()->id(),
                    'resolution_notes'  => $notes !== '' ? $notes : null,
                    'resolved_at'       => now(),
                ]);

                // Inside the transaction: if the audit log fails, everything rolls back
                $audit->logDuplicateResolved($flag, $action);

                return true;
            });
        } catch (\Throwable $e) {
            report($e);
            $this->notify('error', 'Could not resolve the flag. Please try again.');
            return;
        }

        $this->closeFlag();

        if (! $resolved) {
            $this->notify('error', 'This flag was already resolved by someone else.');
            return;
        }

        $this->notify('success', "Flag resolved: {$action}.");
    }

    public function render()
    {
        return view('livewire.duplicate-review', [
            'flags' => DuplicateFlag::pending()
                ->with(['newApplicant', 'existingApplicant'])
                ->orderByDesc('created_at')
                ->paginate(15),
        ])->layout('layouts.app');
    }
}