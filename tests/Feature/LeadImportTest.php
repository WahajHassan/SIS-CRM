<?php

namespace Tests\Feature;

use App\Models\BusinessCategory;
use App\Models\Lead;
use App\Models\SystemLog;
use App\Models\UsaState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Bringing a list of businesses in from a spreadsheet.
 *
 * A new customer arrives with their leads in a file; without this they type
 * them in, which means they do not start. So the import has to survive what
 * real files are actually like: Excel's byte order mark, columns in a different
 * order and under different names, the same business twice, and a trade spelled
 * slightly wrong.
 */
class LeadImportTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'company_id' => $this->company->id,
            'name' => 'Owner',
            'username' => 'owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'superadmin',
            'is_active' => true,
            'approval_status' => 'approved',
        ]);
    }

    private function file(string $contents, string $name = 'leads.csv'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'imp');
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, 'text/csv', null, true);
    }

    private function import(UploadedFile $file)
    {
        $this->withoutExceptionHandling();

        return $this->actingAs($this->owner)
            ->from(route('admin.leads'))
            ->post(route('admin.leads.import'), ['file' => $file]);
    }

    /* ---- the ordinary case --------------------------------------------------- */

    public function test_a_plain_file_goes_in(): void
    {
        $r = $this->import($this->file(
            "Name,Business,Phone,Email\n" .
            "Jane Roof,Roof Co,555 000 1111,jane@roof.test\n" .
            "Bob Siding,Siding Co,555 000 2222,bob@siding.test\n"
        ));

        $this->assertSame(2, Lead::count());
        $this->assertDatabaseHas('leads', ['business_name' => 'Roof Co', 'name' => 'Jane Roof']);
    }

    public function test_the_columns_can_be_in_any_order_and_called_other_things(): void
    {
        $this->import($this->file(
            "Company,Contact Name,Contact Number\n" .
            "Roof Co,Jane Roof,5550001111\n"
        ));

        $this->assertDatabaseHas('leads', ['business_name' => 'Roof Co', 'name' => 'Jane Roof', 'phone' => '5550001111']);
    }

    public function test_it_survives_the_mark_excel_writes(): void
    {
        // Saved as "CSV UTF-8" from Excel, which is what most people will do.
        $this->import($this->file("\xEF\xBB\xBF" . "Name,Business\nJane Roof,Roof Co\n"));

        $this->assertDatabaseHas('leads', ['business_name' => 'Roof Co']);
    }

    public function test_blank_lines_at_the_end_are_ignored(): void
    {
        $this->import($this->file("Name,Business\nJane Roof,Roof Co\n\n,\n"));

        $this->assertSame(1, Lead::count());
    }

    /* ---- the same business twice ---------------------------------------------- */

    public function test_a_business_already_on_the_list_is_left_alone(): void
    {
        Lead::create([
            'company_id' => $this->company->id,
            'name' => 'Jane Roof',
            'business_name' => 'Roof Co',
            'phone' => '5550001111',
            'source' => 'Cold call',
        ]);

        $this->import($this->file("Name,Business,Phone\nJane Roof,Roof Co,5550001111\nBob Siding,Siding Co,5550002222\n"));

        // Two agents ringing the same business is what makes a calling list
        // stop being trusted.
        $this->assertSame(2, Lead::count());
        $this->assertSame(1, Lead::where('business_name', 'Roof Co')->count());
    }

    public function test_the_same_number_typed_differently_is_the_same_business(): void
    {
        Lead::create([
            'company_id' => $this->company->id,
            'name' => 'Jane',
            'business_name' => 'Roof Co',
            'phone' => '5550001111',
            'source' => 'Cold call',
        ]);

        $this->import($this->file("Name,Business,Phone\nJanet,Roofing Company,+1 (555) 000-1111\n"));

        $this->assertSame(1, Lead::count());
    }

    public function test_a_file_that_repeats_itself_only_imports_one(): void
    {
        $this->import($this->file("Name,Business,Phone\nJane,Roof Co,5550001111\nJane,Roof Co,5550001111\n"));

        $this->assertSame(1, Lead::count());
    }

    /* ---- the company's own lists ---------------------------------------------- */

    public function test_a_trade_that_matches_is_linked_rather_than_copied(): void
    {
        $trade = BusinessCategory::create(['company_id' => $this->company->id, 'name' => 'Roofing', 'is_active' => true]);
        $state = UsaState::create(['company_id' => $this->company->id, 'name' => 'Arizona', 'is_active' => true]);

        $this->import($this->file("Business,Trade,State\nRoof Co,roofing,ARIZONA\n"));

        $lead = Lead::first();

        $this->assertSame($trade->id, $lead->business_category_id);
        $this->assertSame($state->id, $lead->usa_state_id);
    }

    public function test_a_trade_nobody_has_heard_of_is_reported_not_invented(): void
    {
        $response = $this->import($this->file("Business,Trade\nRoof Co,Underwater Basket Weaving\n"));

        // Inventing it would quietly corrupt the list every report is built on.
        $this->assertSame(0, BusinessCategory::where('name', 'Underwater Basket Weaving')->count());
        $this->assertSame(1, Lead::count());
        $this->assertNull(Lead::first()->business_category_id);

        $problems = session('import_problems');
        $this->assertNotEmpty($problems);
        $this->assertStringContainsString('Underwater Basket Weaving', implode(' ', $problems));
    }

    /* ---- rows that are not leads ----------------------------------------------- */

    public function test_a_row_with_nothing_to_identify_it_is_reported(): void
    {
        $this->import($this->file("Name,Business,Phone,Notes\nJane,Roof Co,5550001111,fine\n,,,just a note\n"));

        $this->assertSame(1, Lead::count());
        $this->assertStringContainsString('Line 3', implode(' ', session('import_problems')));
    }

    public function test_a_file_with_no_columns_we_know_imports_nothing(): void
    {
        $this->import($this->file("Alpha,Beta,Gamma\n1,2,3\n"));

        $this->assertSame(0, Lead::count());
        $this->assertStringContainsString('recognised', implode(' ', session('import_problems')));
    }

    public function test_an_empty_file_is_turned_away_at_the_door(): void
    {
        // A nought-byte file has no readable type, so it never reaches the
        // importer at all — which is the right place to stop it.
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->import($this->file(''));

        $this->assertSame(0, Lead::count());
    }

    public function test_a_file_with_only_a_heading_imports_nothing(): void
    {
        $this->import($this->file("Name,Business
"));

        $this->assertSame(0, Lead::count());
    }

    /* ---- what it fills in ------------------------------------------------------ */

    public function test_an_imported_lead_knows_where_it_came_from(): void
    {
        $this->import($this->file("Name,Business\nJane,Roof Co\n"));

        $lead = Lead::first();

        $this->assertSame('Imported', $lead->source);
        $this->assertSame('No Answer', $lead->status);
        $this->assertSame($this->owner->id, $lead->created_by);
        $this->assertSame($this->company->id, $lead->company_id);
    }

    public function test_a_source_in_the_file_is_kept(): void
    {
        $this->import($this->file("Business,Source\nRoof Co,Trade show\n"));

        $this->assertSame('Trade show', Lead::first()->source);
    }

    /* ---- and who may do it ------------------------------------------------------ */

    public function test_somebody_without_the_section_is_refused(): void
    {
        $agent = User::create([
            'company_id' => $this->company->id,
            'name' => 'Nobody',
            'username' => 'nobody',
            'email' => 'nobody@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'agent',
            'permissions' => ['dashboard'],
        ]);

        $this->actingAs($agent)
            ->post(route('admin.leads.import'), ['file' => $this->file("Name\nJane\n")])
            ->assertForbidden();

        $this->assertSame(0, Lead::count());
    }

    public function test_importing_is_written_down(): void
    {
        $this->import($this->file("Business\nRoof Co\n"));

        $this->assertTrue(SystemLog::where('action_type', 'Imported Leads')->exists());
    }
}
