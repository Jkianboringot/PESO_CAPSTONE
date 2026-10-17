<?php

namespace App\Livewire\Applicant;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.applicant-app')]
#[Title('Change Password')]
class ChangePassword extends Component
{
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    protected function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        $applicant = Auth::guard('applicant')->user();

        if (! Hash::check($this->current_password, $applicant->password)) {
            $this->addError('current_password', 'Your current password is incorrect.');
            return;
        }

        DB::table('applicants')
            ->where('id', $applicant->id)
            ->update(['password' => Hash::make($this->password), 'updated_at' => now()]);

        $this->reset('current_password', 'password', 'password_confirmation');
        session()->flash('status', 'Password updated.');
    }

    public function render()
    {
        return view('livewire.applicant.applicant-change-password');
    }
}