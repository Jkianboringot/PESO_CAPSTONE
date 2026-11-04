<?php

namespace App\Livewire\Applicant;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.applicant-app')]
#[Title('Experience & Credentials')]
class Credentials extends Component
{
    public const MAX_WORK         = 10;
    public const MAX_TRAININGS    = 15;
    public const MAX_SEMINARS     = 15;
    public const MAX_CERTS        = 15;

    public array $work     = ['company_name' => '', 'position' => '', 'duration' => '', 'description' => ''];
    public array $training = ['title' => '', 'provider' => '', 'year' => ''];
    public array $seminar  = ['title' => '', 'organizer' => '', 'year' => ''];
    public array $cert     = ['name' => '', 'issuing_body' => '', 'year_issued' => '', 'expiry_year' => ''];

    private function applicant()
    {
        return Auth::guard('applicant')->user();
    }

    /** Turn empty strings into null so optional columns stay NULL. */
    private function clean(array $data): array
    {
        return array_map(fn ($v) => $v === '' ? null : $v, $data);
    }

    private function yearRule(): string
    {
        return 'nullable|integer|between:1950,' . now()->year;
    }

    /** Returns false and sets an error on $field when the limit is reached. */
    private function underLimit(string $relation, int $max, string $field, string $label): bool
    {
        $count = $this->applicant()->{$relation}()->count();

        if ($count >= $max) {
            $this->addError($field, "You can add up to {$max} {$label}.");
            return false;
        }

        return true;
    }

    /* ---------------- Work experience ---------------- */

    public function addWork(): void
    {
        $this->validate([
            'work.company_name' => 'required|string|max:255',
            'work.position'     => 'required|string|max:255',
            'work.duration'     => 'nullable|string|max:50',
            'work.description'  => 'nullable|string|max:1000',
        ], [
            'work.company_name.required' => 'Please enter the company name.',
            'work.position.required'     => 'Please enter your position.',
        ]);

        if (! $this->underLimit('workExperiences', self::MAX_WORK, 'work.company_name', 'work experiences')) {
            return;
        }

        $this->applicant()->workExperiences()->create($this->clean($this->work));

        $this->reset('work');
        session()->flash('status', 'Work experience added.');
    }

    public function removeWork(int $id): void
    {
        $this->applicant()->workExperiences()->whereKey($id)->delete();
        session()->flash('status', 'Work experience removed.');
    }

    /* ---------------- Trainings ---------------- */

    public function addTraining(): void
    {
        $this->validate([
            'training.title'    => 'required|string|max:255',
            'training.provider' => 'nullable|string|max:255',
            'training.year'     => $this->yearRule(),
        ], ['training.title.required' => 'Please enter the training title.']);

        if (! $this->underLimit('trainings', self::MAX_TRAININGS, 'training.title', 'trainings')) {
            return;
        }

        $this->applicant()->trainings()->create($this->clean($this->training));

        $this->reset('training');
        session()->flash('status', 'Training added.');
    }

    public function removeTraining(int $id): void
    {
        $this->applicant()->trainings()->whereKey($id)->delete();
        session()->flash('status', 'Training removed.');
    }

    /* ---------------- Seminars ---------------- */

    public function addSeminar(): void
    {
        $this->validate([
            'seminar.title'     => 'required|string|max:255',
            'seminar.organizer' => 'nullable|string|max:255',
            'seminar.year'      => $this->yearRule(),
        ], ['seminar.title.required' => 'Please enter the seminar title.']);

        if (! $this->underLimit('seminars', self::MAX_SEMINARS, 'seminar.title', 'seminars')) {
            return;
        }

        $this->applicant()->seminars()->create($this->clean($this->seminar));

        $this->reset('seminar');
        session()->flash('status', 'Seminar added.');
    }

    public function removeSeminar(int $id): void
    {
        $this->applicant()->seminars()->whereKey($id)->delete();
        session()->flash('status', 'Seminar removed.');
    }

    /* ---------------- Certifications ---------------- */

    public function addCert(): void
    {
        $this->validate([
            'cert.name'         => 'required|string|max:255',
            'cert.issuing_body' => 'nullable|string|max:255',
            'cert.year_issued'  => $this->yearRule(),
            'cert.expiry_year'  => 'nullable|integer|between:1950,2100',
        ], ['cert.name.required' => 'Please enter the certification name.']);

        if (
            $this->cert['year_issued'] !== '' && $this->cert['expiry_year'] !== ''
            && (int) $this->cert['expiry_year'] < (int) $this->cert['year_issued']
        ) {
            $this->addError('cert.expiry_year', 'Expiry year cannot be earlier than the year issued.');
            return;
        }

        if (! $this->underLimit('certifications', self::MAX_CERTS, 'cert.name', 'certifications')) {
            return;
        }

        $this->applicant()->certifications()->create($this->clean($this->cert));

        $this->reset('cert');
        session()->flash('status', 'Certification added.');
    }

    public function removeCert(int $id): void
    {
        $this->applicant()->certifications()->whereKey($id)->delete();
        session()->flash('status', 'Certification removed.');
    }

    public function render()
    {
        $a = $this->applicant();

        return view('livewire.applicant.applicant-credentials', [
            'works'    => $a->workExperiences()->latest()->get(),
            'trainings'=> $a->trainings()->orderByDesc('year')->get(),
            'seminars' => $a->seminars()->orderByDesc('year')->get(),
            'certs'    => $a->certifications()->orderByDesc('year_issued')->get(),
        ]);
    }
}