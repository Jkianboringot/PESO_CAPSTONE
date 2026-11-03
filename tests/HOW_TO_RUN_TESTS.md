# PESO Connect — How to Run the Tests
# Complete Setup and Usage Guide

---

## Step 1 — Copy All Test Files Into Your Laravel Project

Copy each file to the matching path inside your `peso-connect/` project:

```
tests/TestCase.php                                    → tests/TestCase.php
tests/Unit/DuplicateDetectionServiceTest.php          → tests/Unit/
tests/Unit/AuditLogServiceTest.php                    → tests/Unit/
tests/Unit/ApplicantModelTest.php                     → tests/Unit/
tests/Unit/ApplicantsExportTest.php                   → tests/Unit/
tests/Feature/AuthTest.php                            → tests/Feature/
tests/Feature/RegistrationFormTest.php                → tests/Feature/
tests/Feature/ApplicantManagementTest.php             → tests/Feature/
tests/Feature/DuplicateReviewTest.php                 → tests/Feature/
tests/Feature/WorkforceAnalyticsTest.php              → tests/Feature/
tests/Feature/UserManagementAndReportTest.php         → tests/Feature/

database/factories/RoleFactory.php                    → database/factories/
database/factories/MunicipalityFactory.php            → database/factories/
database/factories/BarangayFactory.php                → database/factories/
database/factories/ApplicantFactory.php               → database/factories/
database/factories/EducationFactory.php               → database/factories/
database/factories/SkillCategoryFactory.php           → database/factories/
database/factories/SkillFactory.php                   → database/factories/
database/factories/DuplicateFlagFactory.php           → database/factories/
database/factories/AuditLogFactory.php                → database/factories/

phpunit.xml                                           → (project root, replaces existing)
.env.testing                                          → (project root)
```

---

## Step 2 — Generate Your Test APP_KEY

```bash
php artisan key:generate --env=testing
```

This writes a key to `.env.testing`. Without it, every test fails
with a "No application encryption key" error.

---

## Step 3 — Link Factories to Models

Open each Model and add `use HasFactory;` if not already there:

```php
// app/Models/Applicant.php
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Applicant extends Model {
    use HasFactory;
    // ...
}
```

Do this for: **User, Role, Municipality, Barangay, Applicant,
Education, SkillCategory, Skill, DuplicateFlag, AuditLog**

Then tell each model where its factory lives. Laravel finds factories
automatically if they follow the naming convention
`Database\Factories\{ModelName}Factory`. No extra config needed.

---

## Step 4 — Install Livewire Test Helpers

```bash
composer require livewire/livewire --dev
```

Livewire ships with `Livewire\Livewire::test()` and
`Livewire::actingAs()` used throughout the feature tests.

---

## Step 5 — Run All Tests

```bash
# Run everything
php artisan test

# Run only Unit tests (fast — no DB, no HTTP)
php artisan test --testsuite=Unit

# Run only Feature tests
php artisan test --testsuite=Feature

# Run a single test file
php artisan test tests/Unit/DuplicateDetectionServiceTest.php

# Run a single test method
php artisan test --filter test_flag_created_when_score_is_two_phonetic_and_birthdate

# Run with detailed output (shows each test name)
php artisan test --verbose

# Run with code coverage (requires Xdebug or PCOV)
php artisan test --coverage
php artisan test --coverage --min=80
```

---

## Expected Test Output

```
PESO Connect Test Suite
══════════════════════════════════════════════════

   PASS  Tests\Unit\DuplicateDetectionServiceTest
  ✓ no flag when score is zero                          0.05s
  ✓ no flag when score is one phonetic only             0.03s
  ✓ no flag when score is one birthdate only            0.03s
  ✓ flag created when score is two phonetic and birthdate  0.04s
  ✓ flag created when score is two phonetic and contact    0.04s
  ✓ flag created when score is three all criteria       0.04s
  ✓ ma abbreviation expanded for phonetic match         0.04s
  ✓ contact match ignores prefix format                 0.03s
  ✓ does not flag against inactive applicants           0.03s
  ✓ applicant not compared against themselves           0.03s
  ✓ multiple flags created for multiple matches         0.04s

   PASS  Tests\Unit\AuditLogServiceTest
  ✓ log creates audit log entry with correct action     0.06s
  ✓ guest action stores null user id                    0.04s
  ✓ changes array stored as json                        0.05s
  ✓ log applicant created links model correctly         0.04s
  ✓ log applicant updated stores diff                   0.04s
  ✓ log login records correct user                      0.04s
  ✓ multiple log entries all persisted                  0.04s

   PASS  Tests\Unit\ApplicantModelTest
  ✓ active scope excludes inactive applicants           0.08s
  ✓ active scope returns empty when none active         0.03s
  ✓ by barangay scope filters correctly                 0.05s
  ✓ by barangay scope with null returns all             0.04s
  ✓ date range scope is inclusive on both ends          0.05s
  ✓ date range scope with nulls returns all             0.03s
  ✓ full name accessor formats correctly                0.01s
  ✓ full name accessor handles null middle name         0.01s
  ✓ reference id auto generated on create               0.04s
  ✓ metaphone auto generated on create                  0.04s

   PASS  Tests\Unit\ApplicantsExportTest
  ✓ export produces exactly 19 columns                  0.12s
  ✓ column positions match dole ble format              0.08s
  ✓ null middle name outputs empty string not null      0.05s
  ✓ null email outputs empty string                     0.05s
  ✓ missing education record does not crash export      0.05s
  ✓ skills column joins with pipe separator             0.06s
  ✓ no skills exports empty string                      0.05s
  ✓ headings count matches column count                 0.04s
  ✓ first heading is reference id                       0.04s

   PASS  Tests\Feature\AuthTest
  ✓ valid staff user can login and reaches dashboard    0.45s
  ✓ wrong password fails login                          0.38s
  ✓ deactivated account cannot login                    0.39s
  ✓ nonexistent email fails login                       0.35s
  ✓ authenticated user redirected from login page       0.28s
  ✓ logout clears session and redirects                 0.30s
  ✓ guest redirected to login from dashboard            0.18s
  ✓ guest cannot access any staff route                 0.22s
  ✓ staff cannot access admin only user management      0.25s
  ✓ admin can access user management                    0.27s
  ✓ admin can access staff routes                       0.30s
  ✓ deactivated user blocked on next request            0.32s
  ✓ registration form accessible to guests              0.20s
  ✓ welcome page accessible to guests                   0.18s

  ... (remaining feature tests)

  Tests:    XX passed
  Duration: X.XXs
```

---

## What Each Test Suite Covers

| Suite   | File                               | Tests | What It Protects |
|---------|------------------------------------|-------|------------------|
| Unit    | DuplicateDetectionServiceTest      | 11    | Scoring algorithm boundary conditions |
| Unit    | AuditLogServiceTest                | 7     | RA 10173 audit trail completeness |
| Unit    | ApplicantModelTest                 | 10    | Scope accuracy, accessor output, auto-generation |
| Unit    | ApplicantsExportTest               | 9     | DOLE BLE column format and null handling |
| Feature | AuthTest                           | 14    | Login flow, role access control, route protection |
| Feature | RegistrationFormTest               | 8     | 5-step form, DB transaction, consent enforcement |
| Feature | ApplicantManagementTest            | 10    | Search/filter, soft delete, audit logging |
| Feature | DuplicateReviewTest                | 8     | Resolution actions, audit trail, flag state |
| Feature | WorkforceAnalyticsTest             | 4     | Chart data accuracy, inactive exclusion |
| Feature | SkillsGapAnalysisTest (same file)  | 4     | Gap/adequate split, threshold boundary |
| Feature | UserManagementTest                 | 7     | Password hashing, self-deactivation guard |
| Feature | ReportGeneratorTest                | 5     | Export trigger, format validation, audit log |

**Total: ~97 tests**

---

## Common Errors and Fixes

### "No application encryption key has been specified"
```bash
php artisan key:generate --env=testing
```

### "Class 'Database\Factories\ApplicantFactory' not found"
Add `use HasFactory;` to the Applicant model. Also run:
```bash
composer dump-autoload
```

### "SQLSTATE[HY000]: General error: 1 no such table"
The migration did not run. This usually means `RefreshDatabase`
is missing from a test class. Add at the top of the class:
```php
use Illuminate\Foundation\Testing\RefreshDatabase;
// inside the class:
use RefreshDatabase;
```

### "Livewire\Exceptions\ComponentNotFoundException"
The Livewire component class does not exist or has a wrong namespace.
Check that the class is in `app/Livewire/` with namespace `App\Livewire`.

### "Call to undefined method Livewire::actingAs()"
Your Livewire version may not support `actingAs()` directly.
Use `$this->actingAs($user)` before `Livewire::test()` instead:
```php
$this->actingAs($this->staffUser);
Livewire::test(ApplicantManagement::class)->...
```

### SQLite does not support year() column type
Change `$table->year('year_graduated')` to
`$table->unsignedSmallInteger('year_graduated')->nullable()`
in the education migration when using SQLite for tests.

---

## Continuous Integration (Optional)

Add this to `.github/workflows/tests.yml` for automatic test runs:

```yaml
name: PESO Connect Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: sqlite3, pdo_sqlite
      - run: composer install
      - run: cp .env.testing .env
      - run: php artisan key:generate
      - run: php artisan test --coverage --min=75
```

---

## The Mindset Summary

Every test in this suite was written to answer one question:

> "If this test fails tomorrow, what exactly broke — and why does it matter?"

A test that just checks HTTP 200 tells you nothing useful when it fails.
A test that checks `matched_phonetic = true AND match_score = 2`
tells you exactly which criterion fired and whether the boundary is correct.

Write tests that are specific enough to be useful when they fail.
That is the only standard that matters.
