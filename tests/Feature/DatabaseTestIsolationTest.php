<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseTestIsolationTest extends TestCase
{
    public function test_phpunit_is_pinned_to_the_local_mariadb_testing_database(): void
    {
        $connection = config('database.default');
        $configuration = config("database.connections.{$connection}");

        $this->assertSame('mysql', $connection);
        $this->assertSame('mariadb', $configuration['host']);
        $this->assertSame('testing', $configuration['database']);
        $this->assertSame('testing', DB::connection()->getDatabaseName());
        $this->assertStringContainsString('MariaDB', DB::selectOne('SELECT VERSION() AS version')->version);
    }
}
