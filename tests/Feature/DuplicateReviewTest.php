<?php

namespace Tests\Feature;

use App\Livewire\DuplicateReview;
use App\Models\Applicant;
use App\Models\Barangay;
use App\Models\DuplicateFlag;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * WHY THIS TEST FILE EXISTS
 * ─────────────────────────
 * DuplicateReview is the screen staff use to run the scan and decide each
 * flag. These tests check who may use it, that the scan button reports
 * correctly, and that a resolved flag stays resolved after the next scan.
 */
class DuplicateReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $staffUser;
    private Barangay $barangay;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('staff', 'web');
        Role::findOrCreate('admin', 'web');

        $this->staffUser = User::factory()->create();
        $this->staffUser->assignRole('staff');

        $municipality    = Municipality::factory()->create(['name' => 'Virac']);
        $this->barangay  = Barangay::factory()->create([
            'municipality_id' => $municipality->id,
            'name'            => 'San Jose',
        ]);
    }

    private function makeApplicant(array $overrides = []): Applicant
    {
        return Applicant::factory()->create(array_merge([
            'barangay_id'    => $this->barangay->id,
            'first_name'     => 'Juan',
            'last_name'      => fake()->unique()->lastName(),
            'birthdate'      => fake()->unique()->date('Y-m-d', '2000-01-01'),
            'sex'            => 'Male',
            'civil_status'   => 'Single',
            'contact_number' => '0917' . str_pad((string) random_int(1, 9999999), 7, '0', STR_PAD_LEFT),
            'is_active'      => true,
            'status'         => 'Pending',
        ], $overrides));
    }

    /**
     * Two applicants that match on all criteria.
     */
    private function makeMatchingPair(): array
    {
        $older = $this->makeApplicant([
            'last_name'      => 'Dela Cruz',
            'birthdate'      => '1990-05-14',
            'contact_number' => '09171234567',
        ]);
        $newer = $this->makeApplicant([
            'last_name'      => 'Delacruz',
            'birthdate'      => '1990-05-14',
            'contact_number' => '+63 917-123-4567',
        ]);

        return [$older, $newer];
    }

    // ─────────────────────────────────────────────────────────────────
    // ACCESS
    // ─────────────────────────────────────────────────────────────────

    /**
     * A user without the staff or admin role cannot open the page.
     *
     * WHY: Duplicate flags contain personal data. Only staff who handle
     * applicant records may see or run the scan.
     */
    public function test_user_without_staff_role_cannot_open_page(): void
    {
        $user = User::factory()->create();

        $this->expectException(HttpException::class);

        Livewire::actingAs($user)->test(DuplicateReview::class);
    }

    // ─────────────────────────────────────────────────────────────────
    // SCAN BUTTON
    // ─────────────────────────────────────────────────────────────────

    /**
     * Clicking the scan button creates the flag and shows a success toast.
     *
     * WHY: Staff rely on the toast to know whether the scan found anything.
     */
    public function test_scan_button_creates_flags_and_shows_success_toast(): void
    {
        $this->makeMatchingPair();

        Livewire::actingAs($this->staffUser)
            ->test(DuplicateReview::class)
            ->call('scanForDuplicates')
            ->assertDispatched('notify');

        $this->assertDatabaseCount('duplicate_flags', 1);
    }

    /**
     * The new flag appears in the queue right after the scan.
     *
     * WHY: If the scan runs but the queue doesn't refresh, staff think
     * nothing was found.
     */
    public function test_new_flag_appears_in_queue_after_scan(): void
    {
        [, $newer] = $this->makeMatchingPair();

        Livewire::actingAs($this->staffUser)
            ->test(DuplicateReview::class)
            ->call('scanForDuplicates')
            ->assertSee($newer->reference_id);
    }

    // ─────────────────────────────────────────────────────────────────
    // RESOLVED FLAGS STAY RESOLVED
    // ─────────────────────────────────────────────────────────────────

    /**
     * After staff choose "Retain Both", the next scan must not flag the
     * pair again.
     *
     * WHY: This connects the review action with the scan. A decision made
     * by staff has to survive the next scan.
     */
    public function test_retained_pair_is_not_flagged_again_after_scan(): void
    {
        [, $newer] = $this->makeMatchingPair();

        $flag = DuplicateFlag::factory()->create([
            'applicant_id_new'      => $newer->id,
            'applicant_id_existing' => Applicant::where('id', '<', $newer->id)->first()->id,
        ]);

        Livewire::actingAs($this->staffUser)
            ->test(DuplicateReview::class)
            ->call('openFlag', $flag->id)
            ->call('resolve', 'Retained Both')
            ->call('scanForDuplicates');

        $this->assertDatabaseCount('duplicate_flags', 1);
        $this->assertDatabaseHas('duplicate_flags', [
            'id'                => $flag->id,
            'resolution_status' => 'Retained Both',
        ]);
    }
}