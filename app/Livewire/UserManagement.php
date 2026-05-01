<?php

namespace App\Livewire;

use App\Models\{User, Role};
use App\Services\AuditLogService;
use Livewire\Component;
use Illuminate\Support\Facades\Hash;

class UserManagement extends Component
{
       public bool   $showForm   = false;
    public ?int   $editingId  = null;
    public string $name       = '';
    public string $email      = '';
    public string $password   = '';
    public string $role_id    = '';
 
    public function openCreate() {
        $this->reset(['name','email','password','role_id','editingId']);
        $this->showForm = true;
    }
 
    public function openEdit(int $id) {
        $user = User::findOrFail($id);
        $this->editingId = $id;
        $this->name    = $user->name;
        $this->email   = $user->email;
        $this->role_id = $user->role_id;
        $this->password = '';
        $this->showForm = true;
    }
 
    public function save(AuditLogService $audit) {
        $rules = [
            'name'    => 'required|string|max:100',
            'email'   => 'required|email|unique:users,email' . ($this->editingId ? ",{$this->editingId}" : ''),
            'role_id' => 'required|exists:roles,id',
        ];
        if (!$this->editingId) {
            $rules['password'] = 'required|min:8';
        }
        $this->validate($rules);
 
        if ($this->editingId) {
            $user = User::findOrFail($this->editingId);
            $data = ['name' => $this->name, 'email' => $this->email, 'role_id' => $this->role_id];
            if ($this->password) $data['password'] = Hash::make($this->password);
            $user->update($data);
            $audit->log('USER_UPDATED', $user);
        } else {
            $user = User::create([
                'name'     => $this->name,
                'email'    => $this->email,
                'password'=> Hash::make($this->password),
                'role_id' => $this->role_id,
            ]);
            $audit->log('USER_CREATED', $user);
        }
        $this->showForm = false;
        session()->flash('success', 'User saved.');
    }
 
    public function deactivate(int $id, AuditLogService $audit) {
        // Prevent self-deactivation
        if ($id === auth()->id()) {
            session()->flash('error', 'You cannot deactivate your own account.');
            return;
        }
        $user = User::findOrFail($id);
        $user->update(['is_active' => false]);
        $audit->log('USER_DEACTIVATED', $user);
    }
 
    public function render() {
        return view('livewire.user-management', [
            'users' => User::with('role')->orderBy('name')->paginate(20),
            'roles' => Role::all(),
        ])->layout('layouts.app');
    }

}
