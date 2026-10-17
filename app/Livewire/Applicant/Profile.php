<?php

namespace App\Livewire\Applicant;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.applicant-app')]
#[Title('My Registration')]
class Profile extends Component
{
    public string $contact_number = '';
    public string $email = '';
    public string $address = '';

    public function mount(): void
    {
        $a = Auth::guard('applicant')->user();

        $this->contact_number = (string) $a->contact_number;
        $this->email          = (string) $a->email;
        $this->address        = (string) $a->address;
    }

    protected function rules(): array
    {
        $id = Auth::guard('applicant')->id();

        return [
            // unique so nobody can take another applicant's login identifier
            'contact_number' => ['required', 'string', 'regex:/^[0-9+\-\s()]{7,20}$/', Rule::unique('applicants', 'contact_number')->ignore($id)],
            'email'          => ['nullable', 'email', 'max:255', Rule::unique('applicants', 'email')->ignore($id)],
            'address'        => ['required', 'string', 'max:255'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        DB::table('applicants')
            ->where('id', Auth::guard('applicant')->id())
            ->update([
                'contact_number' => $this->contact_number,
                'email'          => $this->email !== '' ? $this->email : null,
                'address'        => $this->address,
                'updated_at'     => now(),
            ]);

        session()->flash('status', 'Your contact details were updated.');
    }

    public function render()
    {
        $a = Auth::guard('applicant')->user();

        $barangay = DB::table('barangays')->where('id', $a->barangay_id)->value('name');

        return view('livewire.applicant.applicant-profile', [
            'applicant' => $a,
            'barangay'  => $barangay,
        ]);
    }
}