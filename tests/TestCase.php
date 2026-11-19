<?php

namespace Tests;

use App\Models\Company;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * The company every record in a test belongs to.
     */
    protected ?Company $company = null;

    /**
     * Almost every model in this application is scoped to a company. With no tenant
     * set the scope resolves to "match nothing", so records created by a test are
     * invisible to it and lookups come back as 404s. Giving each test a company up
     * front means models pick it up as they are created, exactly as they do behind a
     * real request. TenantManager holds its tenant statically, so it is reassigned on
     * every test rather than carried over from the previous one.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Test Company',
            'slug' => 'test-company',
            'is_active' => true,
        ]);

        TenantManager::setTenant($this->company);
    }

    /**
     * Assert a column holds something other than the value that was written to it,
     * which is what proves the encrypted cast reached the table rather than the model
     * simply handing the value straight back.
     */
    protected function assertStoredEncrypted(string $table, int $id, string $column, string $plainValue): void
    {
        $stored = DB::table($table)->where('id', $id)->value($column);

        $this->assertNotSame($plainValue, $stored, "{$table}.{$column} was stored in the clear.");
        $this->assertStringNotContainsString($plainValue, (string) $stored, "{$table}.{$column} still contains the plain value.");
    }
}
