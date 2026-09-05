<?php
// tests/TestCase.php
// WHY: This is the base class all PESO Connect tests extend.
// It wires Laravel's testing infrastructure and Livewire's
// test helpers so every test has access to them without
// repeating boilerplate use statements.
//
// We also override the application creation to force the
// test environment, ensuring .env.testing is used rather
// than .env — preventing tests from writing to the real database.

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    //
    // RefreshDatabase is declared per-test-class (not here) so each
    // test class explicitly opts in. Some tests (pure unit tests with
    // no DB) don't need it — forcing it globally wastes time.
    //
    // If you find yourself writing the same setUp() in every test class,
    // move it here. But keep this class lean — shared state creates
    // test interdependence, which is a testing anti-pattern.
    //
}
