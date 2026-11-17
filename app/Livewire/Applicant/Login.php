<?php

namespace App\Livewire\Applicant;

use App\Models\Applicant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.applicant-guest')]// change to whatever layout your login pages use
class Login extends Component
{
    #[Validate('required|string')]
    public string $login = '';   // reference ID, contact number or email

    #[Validate('required|string')]
    public string $password = '';
    public bool $remember = false;

  public function authenticate()
{
    $this->login = trim($this->login);

    $this->validate();

    $applicant = Applicant::query()
        ->where('reference_id', $this->login)
        ->orWhere('contact_number', $this->login)
        ->orWhere('email', $this->login)
        ->first();

    // Same key for every identifier that points at the same applicant.
    $key = $this->throttleKey($applicant);

    if (RateLimiter::tooManyAttempts($key, 5)) {
        $seconds = RateLimiter::availableIn($key);
        $this->addError('login', "Too many attempts. Try again in {$seconds} seconds.");
        return;
    }

    // one generic message for every failure so the form doesn't reveal which IDs exist
    if (
        ! $applicant
        || ! $applicant->password
        || ! $applicant->is_active
        || ! Hash::check($this->password, $applicant->password)
    ) {
        RateLimiter::hit($key);

        $this->reset('password');
        $this->addError('login', 'These credentials do not match our records.');
        return;
    }

    RateLimiter::clear($key);

    Auth::guard('applicant')->login($applicant, $this->remember);
    session()->regenerate();

    // Drop any stale staff "intended" URL (e.g. /dashboard) so applicants
    // always land on their own dashboard.
    session()->forget('url.intended');

    return $this->redirectRoute('applicant.profile');
}

protected function throttleKey(?Applicant $applicant = null): string
{
    $who = $applicant
        ? 'applicant:' . $applicant->id
        : 'login:' . Str::lower($this->login);

    return Str::transliterate($who . '|' . request()->ip());
}
    public function render()
    {

        return view('livewire.applicant.login');
    }
}