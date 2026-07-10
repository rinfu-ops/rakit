<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PostgreSqlFoundationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_testing_database_connection_uses_postgresql_18(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertSame('rakit_test', DB::selectOne('select current_database() as name')->name);

        $versionNumber = DB::selectOne('SHOW server_version_num')->server_version_num;

        $this->assertGreaterThanOrEqual(180000, (int) $versionNumber);
    }

    public function test_pg_trgm_extension_is_available(): void
    {
        $extension = DB::table('pg_extension')
            ->where('extname', 'pg_trgm')
            ->value('extname');

        $this->assertSame('pg_trgm', $extension);
    }
}
