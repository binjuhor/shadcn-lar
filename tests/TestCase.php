<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // SAFETY CHECK: Prevent tests from running against production database
        // Tests should ONLY use SQLite in-memory database
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(
                "SAFETY STOP: Tests are configured to run against '{$connection}' database '{$database}'. ".
                'Tests MUST use SQLite in-memory database to prevent production data loss. '.
                "Run 'php artisan config:clear' and ensure phpunit.xml has DB_CONNECTION=sqlite and DB_DATABASE=:memory:"
            );
        }
    }
}
