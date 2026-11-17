<?php

namespace Tests\Feature;

use Tests\TestCase;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Livewire\RegistrationForm;
use App\Models\Municipality;
use App\Models\Barangay;
use App\Models\Skill;
use App\Models\Applicant;
use App\Models\DuplicateFlag;

class RegistrationDuplicateTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

   public function test_duplicate_registration_is_detected()
{
    $before = Applicant::count();

    $data = $this->validData([
        'birthdate'      => '2000-01-01',
        'contact_number' => '09123456789',
    ]);

    // First submit
    Livewire::test(RegistrationForm::class)
        ->set($data)
        ->call('submit')
        ->assertSet('submitted', true);

    // Second identical submit
    Livewire::test(RegistrationForm::class)
        ->set($data)
        ->call('submit')
        ->assertSet('submitted', true);

    $this->assertEquals($before + 2, Applicant::count());
}

    // ─────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────

    /** Valid full-form data. Birthdate and contact are random so rows from earlier runs don't collide. */
    private function validData(array $override = []): array
    {
        $municipality = Municipality::first();
        $barangay     = Barangay::where('municipality_id', $municipality->id)->first();
        $skill        = Skill::first();

        return array_merge([
            'last_name'             => 'Dela Cruz',
            'first_name'            => 'Juan',
            'birthdate'             => now()->subYears(rand(20, 60))->subDays(rand(1, 300))->toDateString(),
            'sex'                   => 'Male',
            'civil_status'          => 'Single',
            'contact_number'        => '09' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'password'              => 'Password123',
            'password_confirmation' => 'Password123',
            'address'               => 'Test Address',
            'municipality_id'       => $municipality->id,
            'barangay_id'           => $barangay->id,
            'highest_level'         => 'College Graduate',
            'selected_skills'       => [$skill->id],
            'skill_proficiencies'   => [$skill->id => 'Advanced'],
            'oath_given'            => true,
            'consent_given'         => true,
        ], $override);
    }

    /** Submits the form and returns the saved applicant (fresh from the DB, after detection ran). */
    private function register(array $override = []): Applicant
    {
        $component = Livewire::test(RegistrationForm::class)
            ->set($this->validData($override))
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        return Applicant::where('reference_id', $component->get('reference_id'))->firstOrFail();
    }

    private function flagsFor(Applicant $new)
    {
        return DuplicateFlag::where('applicant_id_new', $new->id)->get();
    }

    // ─────────────────────────────────────────────────────────────────
    // TWO-OF-THREE RULE
    // ─────────────────────────────────────────────────────────────────

    public function test_same_last_name_and_birthdate_is_flagged(): void
    {
        $first  = $this->register(['birthdate' => '1990-03-04']);
        $second = $this->register(['birthdate' => '1990-03-04']);

        $flags = $this->flagsFor($second);
        $this->assertCount(1, $flags);
        $this->assertTrue((bool) $flags[0]->matched_phonetic);
        $this->assertTrue((bool) $flags[0]->matched_birthdate);
        $this->assertFalse((bool) $flags[0]->matched_contact);
        $this->assertEquals(2, $flags[0]->match_score);
        $this->assertEquals($first->id, $flags[0]->applicant_id_existing);
        $this->assertEquals('Flagged', $second->status);
        $this->assertEquals('Pending', $first->fresh()->status); // existing record is untouched
    }

    public function test_same_contact_and_birthdate_with_different_last_name_is_flagged(): void
    {
        $this->register(['last_name' => 'Garcia', 'birthdate' => '1991-05-06', 'contact_number' => '09175550001']);
        $second = $this->register(['last_name' => 'Villanueva', 'birthdate' => '1991-05-06', 'contact_number' => '09175550001']);

        $flags = $this->flagsFor($second);
        $this->assertCount(1, $flags);
        $this->assertFalse((bool) $flags[0]->matched_phonetic);
        $this->assertTrue((bool) $flags[0]->matched_birthdate);
        $this->assertTrue((bool) $flags[0]->matched_contact);
    }

    public function test_same_contact_and_last_name_with_different_birthdate_is_flagged(): void
    {
        $this->register(['birthdate' => '1980-01-01', 'contact_number' => '09175550002']);
        $second = $this->register(['birthdate' => '1999-12-31', 'contact_number' => '09175550002']);

        $flags = $this->flagsFor($second);
        $this->assertCount(1, $flags);
        $this->assertFalse((bool) $flags[0]->matched_birthdate);
    }

    public function test_all_three_criteria_matching_gives_score_three(): void
    {
        $this->register(['birthdate' => '1985-07-07', 'contact_number' => '09175550003']);
        $second = $this->register(['birthdate' => '1985-07-07', 'contact_number' => '09175550003']);

        $this->assertEquals(3, $this->flagsFor($second)->first()->match_score);
    }

    // ─────────────────────────────────────────────────────────────────
    // ONE CRITERION IS NOT ENOUGH
    // ─────────────────────────────────────────────────────────────────

    public function test_only_same_last_name_is_not_flagged(): void
    {
        $this->register();
        $second = $this->register(); // random birthdate and contact differ

        $this->assertCount(0, $this->flagsFor($second));
        $this->assertEquals('Pending', $second->status);
    }

    public function test_only_same_birthdate_is_not_flagged(): void
    {
        $this->register(['last_name' => 'Garcia', 'birthdate' => '1992-02-02']);
        $second = $this->register(['last_name' => 'Villanueva', 'birthdate' => '1992-02-02']);

        $this->assertCount(0, $this->flagsFor($second));
        $this->assertEquals('Pending', $second->status);
    }

    public function test_only_same_contact_is_not_flagged(): void
    {
        $this->register(['last_name' => 'Garcia', 'contact_number' => '09175550004']);
        $second = $this->register(['last_name' => 'Villanueva', 'contact_number' => '09175550004']);

        $this->assertCount(0, $this->flagsFor($second));
    }

    public function test_contact_numbers_one_digit_apart_do_not_match(): void
    {
        $this->register(['last_name' => 'Garcia', 'birthdate' => '1993-03-03', 'contact_number' => '09175550005']);
        $second = $this->register(['last_name' => 'Villanueva', 'birthdate' => '1993-03-03', 'contact_number' => '09175550006']);

        $this->assertCount(0, $this->flagsFor($second)); // birthdate only = 1 point
    }

    // ─────────────────────────────────────────────────────────────────
    // LAST NAME VARIATIONS (phonetic criterion), paired with a shared birthdate
    // ─────────────────────────────────────────────────────────────────

    public function test_sound_alike_last_names_match(): void
    {
        $this->register(['last_name' => 'Reyes', 'birthdate' => '1994-04-04']);
        $second = $this->register(['last_name' => 'Reyez', 'birthdate' => '1994-04-04']);

        $this->assertCount(1, $this->flagsFor($second));
    }

    public function test_last_name_case_and_extra_spaces_are_ignored(): void
    {
        $this->register(['last_name' => 'Dela Cruz', 'birthdate' => '1995-05-05']);
        $second = $this->register(['last_name' => '  DELA   CRUZ ', 'birthdate' => '1995-05-05']);

        $this->assertCount(1, $this->flagsFor($second));
    }

    public function test_last_name_suffix_is_ignored(): void
    {
        $this->register(['last_name' => 'Santos', 'birthdate' => '1996-06-06']);
        $second = $this->register(['last_name' => 'Santos Jr.', 'birthdate' => '1996-06-06']);

        $this->assertCount(1, $this->flagsFor($second));
    }

    public function test_accented_last_name_matches_plain_spelling(): void
    {
        $this->register(['last_name' => 'Pena', 'birthdate' => '1997-07-07']);
        $second = $this->register(['last_name' => 'Peña', 'birthdate' => '1997-07-07']);

        $this->assertCount(1, $this->flagsFor($second));
    }

    public function test_different_last_names_with_same_birthdate_are_not_flagged(): void
    {
        $this->register(['last_name' => 'Santos', 'birthdate' => '1998-08-08']);
        $second = $this->register(['last_name' => 'Garcia', 'birthdate' => '1998-08-08']);

        $this->assertCount(0, $this->flagsFor($second));
    }

    // ─────────────────────────────────────────────────────────────────
    // WHO GETS COMPARED
    // ─────────────────────────────────────────────────────────────────

    public function test_inactive_existing_applicant_is_not_compared(): void
    {
        $first = $this->register(['birthdate' => '1989-09-09']);
        $first->update(['is_active' => false]);

        $second = $this->register(['birthdate' => '1989-09-09']);

        $this->assertCount(0, $this->flagsFor($second));
        $this->assertEquals('Pending', $second->status);
    }

    public function test_third_matching_registration_is_flagged_against_both_earlier_ones(): void
    {
        $first  = $this->register(['birthdate' => '1988-08-01']);
        $second = $this->register(['birthdate' => '1988-08-01']);
        $third  = $this->register(['birthdate' => '1988-08-01']);

        $flags = $this->flagsFor($third);
        $this->assertCount(2, $flags);
        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $flags->pluck('applicant_id_existing')->all()
        );
    }

    public function test_first_registration_is_never_flagged(): void
    {
        $first = $this->register(['birthdate' => '1987-07-01']);

        $this->assertCount(0, $this->flagsFor($first));
        $this->assertEquals('Pending', $first->status);
    }

    // ─────────────────────────────────────────────────────────────────
    // FLAG BOOKKEEPING AND BLOCKED SUBMITS
    // ─────────────────────────────────────────────────────────────────

    public function test_new_flag_starts_as_pending_and_unresolved(): void
    {
        $this->register(['birthdate' => '1986-06-01']);
        $second = $this->register(['birthdate' => '1986-06-01']);

        $flag = $this->flagsFor($second)->first();
        $this->assertEquals('Pending', $flag->resolution_status);
        $this->assertNull($flag->resolved_by);
        $this->assertNull($flag->resolved_at);
    }

    public function test_duplicate_is_still_saved_not_blocked(): void
    {
        $before = Applicant::count();

        $this->register(['birthdate' => '1984-04-01', 'contact_number' => '09175550007']);
        $this->register(['birthdate' => '1984-04-01', 'contact_number' => '09175550007']);

        $this->assertEquals($before + 2, Applicant::count());
    }

    public function test_invalid_submission_creates_no_applicant_and_no_flag(): void
    {
        $this->register(['birthdate' => '1983-03-01']);
        $applicants = Applicant::count();
        $flags      = DuplicateFlag::count();

        Livewire::test(RegistrationForm::class)
            ->set($this->validData(['birthdate' => '1983-03-01', 'last_name' => '']))
            ->call('submit')
            ->assertHasErrors(['last_name'])
            ->assertSet('submitted', false);

        $this->assertEquals($applicants, Applicant::count());
        $this->assertEquals($flags, DuplicateFlag::count());
    }

    public function test_submission_without_consent_creates_no_flag(): void
    {
        $this->register(['birthdate' => '1982-02-01']);
        $flags = DuplicateFlag::count();

        Livewire::test(RegistrationForm::class)
            ->set($this->validData(['birthdate' => '1982-02-01', 'consent_given' => false]))
            ->call('submit')
            ->assertHasErrors(['consent_given']);

        $this->assertEquals($flags, DuplicateFlag::count());
    }
}