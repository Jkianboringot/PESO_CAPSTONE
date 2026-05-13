<?php

namespace App\Livewire;

use App\Models\{User, Role};
use App\Services\AuditLogService;
use Livewire\Component;
use Illuminate\Support\Facades\Hash;
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

    public function openCreate()
    {

        // no // AUTHORIZE check
        $this->reset(['name', 'email', 'password', 'role_id', 'editingId']);
        $this->showForm = true;
    }

    public function openEdit(int $id)
    {


        //OPTIMIZE mount user if edit is click this way its faster becuase its the first
        //thing that loads
        $user = User::findOrFail($id);
        $this->currentRoleId;
        foreach ($user->roles as $role) {
            $this->currentRoleId = $role->id;
        }
        //no // AUTHORIZE check

        $this->editingId = $id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->showForm = true;
        $this->role_id = $this->currentRoleId;


    }

    public function save(AuditLogService $audit)
    {

        //no // AUTHORIZE check
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
        $this->validate($rules);

        //SECURE-DB transaction or try catch
        if ($this->editingId) {
            $user = User::findOrFail($this->editingId);

            //REVIEW
            //temporary -> this should only be activate if role_id is to change
            if (($this->role_id != $this->currentRoleId) && ($user->id === auth()->id())) {
                abort(403, 'Cannot edit your own Role');
            }

            $role = Role::findOrFail($this->role_id);
            $user->syncRoles($role->name);

            // dd($role);
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
                // 'role_id' => $this->role_id,
            ]);
            $role = Role::findOrFail($this->role_id ?? '');
            $user->syncRoles($role->name);


            $audit->log('USER_CREATED', $user);
        }
        $this->showForm = false;
        session()->flash('success', 'User saved.');
    }

    public function deactivate(int $id, AuditLogService $audit)
    {
        // Prevent self-deactivation   - no // AUTHORIZE its good enough
        if ($id === auth()->id()) {
            session()->flash('error', 'You cannot deactivate your own account.');
            return;
        }
        $user = User::findOrFail($id);
        $user->update(['is_active' => false]);
        $audit->log('USER_DEACTIVATED', $user);
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
