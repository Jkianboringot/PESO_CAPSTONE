# PESO Connect — Testing Philosophy and Mindset

## How I Thought About This

Before writing one test, I asked: "What can go wrong, and what would it cost PESO if it did?"

A test is not written to prove code works. A test is written to DEFINE what correct behavior means,
and to CATCH the moment it stops being correct — now, or 6 months from now when someone edits
the code without realizing the consequences.

---

## The Mental Framework: Risk-First Testing

I categorized every piece of the system by asking three questions:

1. IF this breaks, who gets hurt?
   - Resident data lost → public trust destroyed
   - Duplicate goes undetected → PESO submits dirty data to DOLE
   - Wrong role bypasses middleware → data breach
   - Report downloads wrong records → incorrect DOLE submission

2. WHAT is the hardest thing to get right in this module?
   - Registration: the DB transaction (all 3 writes or none)
   - Duplicate detection: the scoring algorithm (math must be exact)
   - Middleware: the 3-condition check (order matters)
   - Analytics: the queries return real aggregations (not empty/wrong)
   - Exports: the DOLE format alignment (19 columns, correct order)

3. WHAT are the edge cases a human would never manually test?
   - Score of exactly 2 (threshold boundary)
   - Score of exactly 1 (should NOT flag)
   - Admin trying to deactivate themselves
   - Empty skill list submitted
   - Municipality changed after barangay selected

This gives you a test suite that is not "comprehensive by line count"
but COMPREHENSIVE by risk coverage.

---

## Test Categories Used

### Unit Tests
Test a single class or method IN ISOLATION.
No database. No HTTP. No Livewire.
Fast (< 1ms each). Run hundreds per second.
Purpose: verify business logic is mathematically correct.

Files:
- DuplicateDetectionServiceTest.php  — the scoring algorithm
- AuditLogServiceTest.php            — the logging calls
- ApplicantsExportTest.php           — the column mapping
- ApplicantModelTest.php             — scopes and accessors

### Feature Tests
Test the full HTTP request → controller/livewire → database → response cycle.
Uses a real test database (SQLite in memory or MySQL test DB).
Purpose: verify that modules work end-to-end as a user would use them.

Files:
- RegistrationFormTest.php           — the 5-step form
- AuthTest.php                       — login/logout/middleware
- ApplicantManagementTest.php        — CRUD + filters
- DuplicateReviewTest.php            — the resolution queue
- WorkforceAnalyticsTest.php         — chart data queries
- SkillsGapAnalysisTest.php          — gap/surplus split
- ReportGeneratorTest.php            — export download
- UserManagementTest.php             — admin-only CRUD
- RoleMiddlewareTest.php             — access control

---

## The Golden Rule of Testing

Every test must answer: "IF this test fails, what EXACTLY broke?"

A vague test that just checks status 200 tells you nothing.
A specific test that checks the duplicate flag was created
with matched_phonetic=true AND match_score=2 tells you exactly
which criterion fired and whether the scoring was correct.

---
