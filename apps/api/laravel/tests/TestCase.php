<?php

/**
 * TestCase.php — the base class for the Laravel app's tests.
 *
 * Why: Laravel's TestCase boots the whole app before each test, so routes, the
 * database connection and facades work as they do for a real request. Shared test
 * helpers (such as checking a response against the contract, from checkpoint 2) go here.
 *
 * How it connects: every test in tests/ extends it; phpunit.xml sets the test
 * environment and the test database.
 */

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    //
}
