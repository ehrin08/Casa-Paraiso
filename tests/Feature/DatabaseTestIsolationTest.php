<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseTestIsolationTest extends TestCase
{
    public function test_phpunit_is_pinned_to_the_local_mysql_testing_database(): void
    {
        $connection = config('database.default');
        $configuration = config("database.connections.{$connection}");

        $this->assertSame('mysql', $connection);
        $this->assertSame('mysql', $configuration['host']);
        $this->assertSame('testing', $configuration['database']);
        $this->assertSame('testing', DB::connection()->getDatabaseName());
    }
}
