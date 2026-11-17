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

    Livewire::actingAs($user)->test(DuplicateReview::class)->assertForbidden();
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

        // ─────────────────────────────────────────────────────────────────
    // HELPERS FOR EDGE CASES
    // ─────────────────────────────────────────────────────────────────

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /** True only when the callable throws an HttpException with status 403. */
    private function refusedWith403(callable $action): bool
    {
        try {
            $action();
            return false;
        } catch (HttpException $e) {
            return $e->getStatusCode() === 403;
        }
    }

    /** A pending flag for a pair. Pass overrides to set status, notes, or created_at. */
    private function flagFor(Applicant $new, Applicant $existing, array $attrs = []): DuplicateFlag
    {
        return DuplicateFlag::factory()->create(array_merge([
            'applicant_id_new'      => $new->id,
            'applicant_id_existing' => $existing->id,
            'resolution_status'     => 'Pending',
        ], $attrs));
    }

    private function page()
    {
        return Livewire::actingAs($this->staffUser)->test(DuplicateReview::class);
    }

    // ─────────────────────────────────────────────────────────────────
    // ACCESS
    // ─────────────────────────────────────────────────────────────────

    public function test_admin_can_open_page(): void
    {
        Livewire::actingAs($this->userWithRole('admin'))
            ->test(DuplicateReview::class)
            ->assertOk();
    }

    /**
     * Calls mount() directly, the same way the other permission tests do.
     * Livewire's test helper does not reliably pass the 403 through.
     */
    public function test_user_without_role_cannot_mount_page(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assertTrue(
            $this->refusedWith403(fn () => (new DuplicateReview())->mount()),
            'User without a role: DuplicateReview mount() should be refused with 403.'
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // OPENING A FLAG
    // ─────────────────────────────────────────────────────────────────

    public function test_open_flag_sets_the_active_flag_and_clears_notes(): void
    {
        [$older, $newer] = $this->makeMatchingPair();
        $flag = $this->flagFor($newer, $older);

        $this->page()
            ->set('resolutionNotes', 'left over from before')
            ->call('openFlag', $flag->id)
            ->assertSet('reviewingFlagId', $flag->id)
            ->assertSet('resolutionNotes', '');
    }

    /** A flag resolved elsewhere cannot be opened. The user gets a clear message. */
    public function test_open_flag_that_is_already_resolved_is_refused(): void
    {
        [$older, $newer] = $this->makeMatchingPair();
        $flag = $this->flagFor($newer, $older, ['resolution_status' => 'Merged']);

        $this->page()
            ->call('openFlag', $flag->id)
            ->assertSet('reviewingFlagId', null)
            ->assertDispatched('notify', type: 'error', message: 'This flag was already resolved or no longer exists.');
    }

    /** Opening an ID that does not exist also closes whatever flag was open before. */
    public function test_opening_a_missing_flag_closes_the_open_one(): void
    {
        [$older, $newer] = $this->makeMatchingPair();
        $flag = $this->flagFor($newer, $older);

        $this->page()
            ->call('openFlag', $flag->id)
            ->call('openFlag', 99999)
            ->assertSet('reviewingFlagId', null)
            ->assertSet('activeFlag', null);
    }

    public function test_close_flag_resets_the_review_state(): void
    {
        [$older, $newer] = $this->makeMatchingPair();
        $flag = $this->flagFor($newer, $older);

        $this->page()
            ->call('openFlag', $flag->id)
            ->set('resolutionNotes', 'draft note')
            ->call('closeFlag')
            ->assertSet('reviewingFlagId', null)
            ->assertSet('activeFlag', null)
            ->assertSet('resolutionNotes', '');
    }

    // ─────────────────────────────────────────────────────────────────
    // RESOLVE GUARDS
    // ─────────────────────────────────────────────────────────────────

    public function test_resolve_without_an_open_flag_is_refused(): void
    {
        [$older, $newer] = $this->makeMatchingPair();
        $flag = $this->flagFor($newer, $older);

        $this->page()
            ->call('resolve', 'Merged')
            ->assertDispatched('notify', type: 'error', message: 'Open a flag first.');

        $this->assertDatabaseHas('duplicate_flags', ['id' => $flag->id, 'resolution_status' => 'Pending']);
    }

    /** Any action outside the three allowed values is refused and changes nothing. */
    public function test_invalid_action_is_refused_and_changes_nothing(): void
    {
        [$older, $newer] = $this->makeMatchingPair();
        $flag = $this->flagFor($newer, $older);

        $this->page()
            ->call('openFlag', $flag->id)
            ->call('resolve', 'Approved')
            ->assertDispatched('notify', type: 'error', message: 'Invalid action.');

        $this->assertDatabaseHas('duplicate_flags', ['id' => $flag->id, 'resolution_status' => 'Pending']);
        $this->assertDatabaseHas('applicants', ['id' => $newer->id, 'status' => 'Pending', 'is_active' => true]);
    }

    public function test_notes_over_1000_characters_are_rejected(): void
    {
        [$older, $newer] = $this->makeMatchingPair();
        $flag = $this->flagFor($newer, $older);

        $this->page()
            ->call('openFlag', $flag->id)
            ->set('resolutionNotes', str_repeat('a', 1001))
            ->call('resolve', 'Merged')
            ->assertHasErrors(['resolutionNotes']);

        $this->assertDatabaseHas('duplicate_flags', ['id' => $flag->id, 'resolution_status' => 'Pending']);
    }

    /**
     * Staff A opens a flag, then staff B resolves it. When A clicks resolve,
     * the locked re-check finds it is no longer pending and stops. Nothing is written.
     */
    public function test_flag_resolved_by_someone_else_during_review_is_not_changed(): void
    {
        [$older, $newer] = $this->makeMatchingPair();
        $flag = $this->flagFor($newer, $older);

        $page = $this->page()->call('openFlag', $flag->id);

        DuplicateFlag::whereKey($flag->id)->update(['resolution_status' => 'Deleted']);

        $page->call('resolve', 'Merged')
            ->assertDispatched('notify', type: 'error', message: 'This flag was already resolved by someone else.');

        $this->assertDatabaseHas('applicants', ['id' => $newer->id, 'status' => 'Pending', 'is_active' => true]);
    }

    /** Resolve closes the flag, so a second click on the same flag is refused. */
    public function test_second_resolve_on_the_same_flag_is_refused(): void
    {
        [$older, $newer] = $this->makeMatchingPair();
        $flag = $this->flagFor($newer, $older);

        $this->page()
            ->call('openFlag', $flag->id)
            ->call('resolve', 'Merged')
            ->call('resolve', 'Merged')
            ->assertDispatched('notify', type: 'error', message: 'Open a flag first.');
    }

    // ─────────────────────────────────────────────────────────────────
    // RESOLUTION OUTCOMES
    // ─────────────────────────────────────────────────────────────────

    /** Merged: the incoming record is deactivated and marked Duplicated. The flag records who resolved it and when. */
    public function test_merged_deactivates_the_incoming_record_and_records_the_reviewer(): void
    {
        [$older, $newer] = $this->makeMatchingPair();
        $flag = $this->flagFor($newer, $older);

        $this->page()
            ->call('openFlag', $flag->id)
            ->call('resolve', 'Merged')
            ->assertDispatched('notify', type: 'success');

        $this->assertDatabaseHas('applicants', ['id' => $newer->id, 'is_active' => false, 'status' => 'Duplicated']);
        $this->assertDatabaseHas('duplicate_flags', [
            'id'                => $flag->id,
            'resolution_status' => 'Merged',
            'resolved_by'       => $this->staffUser->id,
        ]);
        $this->assertNotNull(DuplicateFlag::find($flag->id)->resolved_at);
    }

    /** Deleted currently has the same effect on the data as Merged. This documents that. */
    public function test_deleted_deactivates_the_incoming_record_like_merged(): void
    {
        [$older, $newer] = $this->makeMatchingPair();
        $flag = $this->flagFor($newer, $older);

        $this->page()->call('openFlag', $flag->id)->call('resolve', 'Deleted');

        $this->assertDatabaseHas('applicants', ['id' => $newer->id, 'is_active' => false, 'status' => 'Duplicated']);
        $this->assertDatabaseHas('duplicate_flags', ['id' => $flag->id, 'resolution_status' => 'Deleted']);
    }

    /** Merged and Deleted never change the existing record. Its status and active state stay as they were. */
    public function test_merged_leaves_the_existing_record_untouched(): void
    {
        [$older, $newer] = $this->makeMatchingPair();
        $older->update(['status' => 'Verified']);
        $flag = $this->flagFor($newer, $older);

        $this->page()->call('openFlag', $flag->id)->call('resolve', 'Merged');

        $this->assertDatabaseHas('applicants', ['id' => $older->id, 'is_active' => true, 'status' => 'Verified']);
    }

    /** Retained Both: both records end up Verified. The existing record was Pending, so it is audited. */
    public function test_retained_both_verifies_both_records_and_audits_the_existing_change(): void
    {
        [$older, $newer] = $this->makeMatchingPair();
        $newer->update(['status' => 'Flagged']);
        $flag = $this->flagFor($newer, $older);

        $this->page()->call('openFlag', $flag->id)->call('resolve', 'Retained Both');

        $this->assertDatabaseHas('applicants', ['id' => $newer->id, 'is_active' => true, 'status' => 'Verified']);
        $this->assertDatabaseHas('applicants', ['id' => $older->id, 'status' => 'Verified']);

        // One audit row for the existing record's status change, one for resolving the flag.
        // If AuditLogService writes a different number of rows for a resolve, adjust this count.
        $this->assertDatabaseCount('audit_logs', 2);
    }

    /** When the existing record is already Verified, Retained Both does not touch or audit it again. */
    public function test_retained_both_does_not_rewrite_an_already_verified_existing_record(): void
    {
        [$older, $newer] = $this->makeMatchingPair();
        $older->update(['status' => 'Verified']);
        $flag = $this->flagFor($newer, $older);

        $this->page()->call('openFlag', $flag->id)->call('resolve', 'Retained Both');

        // Only the flag resolution is audited.
        $this->assertDatabaseCount('audit_logs', 1);
    }

    /**
     * Retained Both verifies a Flagged existing record. Verify itself refuses
     * Flagged applicants, so this is a different path to the same status.
     * This documents current behaviour. See the open question in the doc.
     */
    public function test_retained_both_verifies_a_flagged_existing_record(): void
    {
        [$older, $newer] = $this->makeMatchingPair();
        $older->update(['status' => 'Flagged']);
        $flag = $this->flagFor($newer, $older);

        $this->page()->call('openFlag', $flag->id)->call('resolve', 'Retained Both');

        $this->assertDatabaseHas('applicants', ['id' => $older->id, 'status' => 'Verified']);
    }

    /** Notes are trimmed before they are saved. */
    public function test_resolution_notes_are_trimmed_before_saving(): void
    {
        [$older, $newer] = $this->makeMatchingPair();
        $flag = $this->flagFor($newer, $older);

        $this->page()
            ->call('openFlag', $flag->id)
            ->set('resolutionNotes', '  Checked the ID  ')
            ->call('resolve', 'Merged');

        $this->assertDatabaseHas('duplicate_flags', ['id' => $flag->id, 'resolution_notes' => 'Checked the ID']);
    }

    /** Notes made only of spaces are saved as NULL, not an empty string. */
    public function test_blank_resolution_notes_are_saved_as_null(): void
    {
        [$older, $newer] = $this->makeMatchingPair();
        $flag = $this->flagFor($newer, $older);

        $this->page()
            ->call('openFlag', $flag->id)
            ->set('resolutionNotes', '   ')
            ->call('resolve', 'Merged');

        $this->assertNull(DuplicateFlag::find($flag->id)->resolution_notes);
    }

    // ─────────────────────────────────────────────────────────────────
    // THE QUEUE
    // ─────────────────────────────────────────────────────────────────

    /** Once resolved, a flag leaves the queue. Only pending flags are listed. */
    public function test_resolved_flag_leaves_the_queue(): void
    {
        [$older, $newer] = $this->makeMatchingPair();
        $first  = $this->flagFor($newer, $older);
        $other  = Applicant::factory()->create(['barangay_id' => $this->barangay->id, 'is_active' => true]);
        $second = $this->flagFor($other, $older);

        $page = $this->page();
        $page->call('openFlag', $first->id)->call('resolve', 'Merged');

        $this->assertSame(1, $page->viewData('flags')->total());
        $this->assertSame($second->id, $page->viewData('flags')->first()->id);
    }

    /** The queue shows 15 per page, with the rest on the next page. */
    public function test_queue_pages_at_fifteen_flags(): void
    {
        $new = $this->makeApplicant();

        foreach (range(1, 16) as $_) {
            $this->flagFor($new, $this->makeApplicant());
        }

        $page = $this->page();

        $this->assertSame(16, $page->viewData('flags')->total());
        $this->assertCount(15, $page->viewData('flags'));
    }

    /** Newest flags appear first. */
    public function test_queue_lists_newest_flags_first(): void
    {
        [$older, $newer] = $this->makeMatchingPair();
        $oldFlag = $this->flagFor($newer, $older, ['created_at' => now()->subDays(2)]);

        $other     = $this->makeApplicant();
        $recentFlag = $this->flagFor($other, $older, ['created_at' => now()]);

        $this->assertSame(
            [$recentFlag->id, $oldFlag->id],
            $this->page()->viewData('flags')->pluck('id')->all()
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // THE SCAN BUTTON
    // ─────────────────────────────────────────────────────────────────

    /** On an empty database the scan reports zero and does not fail. */
    public function test_scan_on_empty_database_reports_zero(): void
    {
        $this->page()
            ->call('scanForDuplicates')
            ->assertDispatched('notify', type: 'success', message: 'Scan complete: 0 active applicant(s) checked, 0 new flag(s).');
    }

    /** The toast message gives the checked count and the new-flag count. */
    public function test_scan_toast_reports_checked_and_new_counts(): void
    {
        $this->makeMatchingPair();

        $this->page()
            ->call('scanForDuplicates')
            ->assertDispatched('notify', type: 'success', message: 'Scan complete: 2 active applicant(s) checked, 1 new flag(s).');
    }

    /** Scanning again reports zero new flags, so the toast does not suggest new work. */
    public function test_second_scan_reports_no_new_flags(): void
    {
        $this->makeMatchingPair();

        $page = $this->page()->call('scanForDuplicates');
        $page->call('scanForDuplicates')
            ->assertDispatched('notify', type: 'success', message: 'Scan complete: 2 active applicant(s) checked, 0 new flag(s).');

        $this->assertDatabaseCount('duplicate_flags', 1);
    }

    /** A scan does not close the flag the reviewer has open. */
    public function test_scan_keeps_an_open_flag_open(): void
    {
        [$older, $newer] = $this->makeMatchingPair();
        $flag = $this->flagFor($newer, $older);

        $this->page()
            ->call('openFlag', $flag->id)
            ->call('scanForDuplicates')
            ->assertSet('reviewingFlagId', $flag->id);
    }

    // ─────────────────────────────────────────────────────────────────
    // KNOWN BUG
    // ─────────────────────────────────────────────────────────────────

    /**
     * KNOWN BUG (DUP-3): Retained Both sets a deactivated incoming record back
     * to active and Verified. That skips the Activate action's rules and writes
     * no audit row for the incoming record.
     *
     * HOW TO REPRODUCE: flag a pair, deactivate the incoming record, open the
     * flag and choose Retained Both. BUG: the record is active again with no
     * audit entry for it. Expected: an audit entry for the reactivation.
     *
     * FIX: in applyRetainBoth(), write the same audit entry Activate writes
     * (or do not reactivate, and leave the record for the Activate action).
     */
    public function test_retained_both_on_a_deactivated_record_writes_an_audit_entry(): void
    {
        [$older, $newer] = $this->makeMatchingPair();
        $newer->update(['is_active' => false, 'status' => 'Duplicated']);
        $flag = $this->flagFor($newer, $older);

        $this->page()->call('openFlag', $flag->id)->call('resolve', 'Retained Both');

        $this->assertDatabaseHas('applicants', ['id' => $newer->id, 'is_active' => true, 'status' => 'Verified']);
        $this->assertDatabaseHas('audit_logs', ['model_id' => $newer->id]);
    }
}