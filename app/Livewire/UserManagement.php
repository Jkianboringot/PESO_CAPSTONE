<?php
 /**
     * kian.
     */

namespace App\Livewire;

use App\Models\{User, Role};
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class UserManagement extends Component
{
    public bool $showForm = false;
    public ?int $editingId = null;
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $role_id = '';

    public string $currentRoleId = '';

    public function mount()
    {

        abort_if(
            !auth()->user()->hasRole('admin'),
            403
        );

    }

    // ---------- TOAST HELPER ----------
    private function notify(string $type, string $message): void
    {
        $this->dispatch('notify', type: $type, message: $message);
    }

    public function openCreate()
    {


        $this->reset(['name', 'email', 'password', 'role_id', 'editingId']);
        $this->showForm = true;
    }

    public function openEdit(int $id)
    {


        //OPTIMIZE mount user if edit is click this way its faster becuase its the first
        //thing that loads
        $user = User::findOrFail($id);
        $this->currentRoleId = '';
        foreach ($user->roles as $role) {
            $this->currentRoleId = $role->id;
        }


        $this->editingId = $id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->showForm = true;
        $this->role_id = $this->currentRoleId;


    }

    public function save(AuditLogService $audit)
    {

        abort_if(
            !auth()->user()->hasRole('admin'),
            403
        );


        $rules = [
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email' . ($this->editingId ? ",{$this->editingId}" : ''),//REVIEW
            'role_id' => 'required|exists:roles,id',
        ];
        if (!$this->editingId) { //if we are not in editing form we require password
            $rules['password'] = 'required|min:8';
        }

        try {
            $this->validate($rules);
        } catch (ValidationException $e) {
            $this->notify('error', 'Please fix the highlighted fields.');
            throw $e; // keeps the inline field errors
        }

        //REVIEW
        //temporary -> this should only be activate if role_id is to change
        if (
            $this->editingId
            && ($this->role_id != $this->currentRoleId)
            && ((int) $this->editingId === (int) auth()->id())
        ) {
            $this->notify('error', 'You cannot change your own role.');
            return;
        }

        $isEdit = (bool) $this->editingId;

        try {
            DB::transaction(function () use ($audit, $isEdit) {
                if ($isEdit) {
                    $user = User::findOrFail($this->editingId);

                    $role = Role::findOrFail($this->role_id);
                    $user->syncRoles($role->name);

                    $data = ['name' => $this->name, 'email' => $this->email];
                    if ($this->password)
                        $data['password'] = Hash::make($this->password);
                    $user->update($data);

                    $audit->log('USER_UPDATED', $user);
                } else {
                    $user = User::create([
                        'name' => $this->name,
                        'email' => $this->email,
                        'password' => Hash::make($this->password),
                    ]);
                    $role = Role::findOrFail($this->role_id);
                    $user->syncRoles($role->name);

                    $audit->log('USER_CREATED', $user);
                }
            });
        } catch (\Throwable $e) {
            report($e);
            $this->notify('error', 'Could not save the user. Please try again.');
            return;
        }

        $this->showForm = false;
        $this->notify('success', $isEdit ? 'User updated successfully.' : 'User created successfully.');
    }

    public function deactivate(int $id, AuditLogService $audit)
    {
        // Prevent self-deactivation   - no // AUTHORIZE its good enough
        if ($id === auth()->id()) {
            $this->notify('error', 'You cannot deactivate your own account.');
            return;
        }

        try {
            $user = User::findOrFail($id);
            $user->update(['is_active' => false]);

            $audit->log('USER_DEACTIVATED', $user);
        } catch (\Throwable $e) {
            report($e);
            $this->notify('error', 'Could not deactivate the user. Please try again.');
            return;
        }

        $this->notify('success', 'User deactivated.');
    }

    public function activated(int $id, AuditLogService $audit)
    {
        // Prevent self-activation   - no // AUTHORIZE its good enough
        if ($id === auth()->id()) {
            $this->notify('error', 'You cannot activate your own account.');
            return;
        }

        try {
            $user = User::findOrFail($id);
            $user->update(['is_active' => true]);

            $audit->log('USER_ACTIVATED', $user);
        } catch (\Throwable $e) {
            report($e);
            $this->notify('error', 'Could not activate the user. Please try again.');
            return;
        }

        $this->notify('success', 'User activated.');
    }

    public function render()
    {
        abort_if(
            !auth()->user()->hasRole('admin'),
            403
        );
        return view('livewire.user-management', [
            'users' => User::with('roles')->orderBy('name')->paginate(20),
            'roles' => Role::all(),
        ])->layout('layouts.app');
    }

}