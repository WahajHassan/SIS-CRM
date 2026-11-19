<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\Lead;
use App\Models\SystemLog;
use App\Models\TransferredLead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Getting the work back out.
 *
 * The two things that make an export untrustworthy are a file that ignores the
 * filters on screen — so the spreadsheet disagrees with the page and nobody can
 * tell which is right — and a file that carries something it should not. Both
 * are checked here, along with the formula escaping, which is the one way a
 * CSV export can actually hurt the person who opens it.
 */
class ExportTest extends TestCase
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

    private function lead(array $extra = []): Lead
    {
        return Lead::create(array_merge([
            'company_id' => $this->company->id,
            'name' => 'Jane Roof',
            'business_name' => 'Roof Co',
            'phone' => '5550001111',
            'source' => 'Cold call',
            'status' => 'Interested',
            'created_by' => $this->owner->id,
        ], $extra));
    }

    /** What actually came down the wire. */
    private function body($response): string
    {
        ob_start();
        $response->sendContent();

        return ob_get_clean();
    }

    /* ---- leads --------------------------------------------------------------- */

    public function test_the_leads_file_holds_the_leads(): void
    {
        $this->lead();

        $response = $this->actingAs($this->owner)->get(route('admin.leads.export'));
        $response->assertOk();

        $csv = $this->body($response);

        $this->assertStringContainsString('Jane Roof', $csv);
        $this->assertStringContainsString('Roof Co', $csv);
        $this->assertStringContainsString('Name,Business,Phone', $csv);
    }

    public function test_it_comes_down_as_a_file_excel_can_read(): void
    {
        $this->lead();

        $response = $this->actingAs($this->owner)->get(route('admin.leads.export'));

        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('leads-', $response->headers->get('Content-Disposition'));

        // Without the mark, Excel reads UTF-8 as something else and every
        // accented name arrives mangled.
        $this->assertStringStartsWith("\xEF\xBB\xBF", $this->body($response));
    }

    public function test_the_file_matches_what_is_on_the_screen(): void
    {
        $this->lead(['name' => 'Jane Roof', 'status' => 'Interested']);
        $this->lead(['name' => 'Bob Siding', 'business_name' => 'Siding Co', 'status' => 'Not Interested']);

        $response = $this->actingAs($this->owner)->get(route('admin.leads.export', ['status' => 'Interested']));
        $csv = $this->body($response);

        // An export that quietly ignores the filters is worse than none.
        $this->assertStringContainsString('Jane Roof', $csv);
        $this->assertStringNotContainsString('Bob Siding', $csv);
    }

    public function test_a_search_narrows_it_too(): void
    {
        $this->lead(['name' => 'Jane Roof']);
        $this->lead(['name' => 'Bob Siding', 'business_name' => 'Siding Co']);

        $csv = $this->body($this->actingAs($this->owner)->get(route('admin.leads.export', ['search' => 'Siding'])));

        $this->assertStringContainsString('Bob Siding', $csv);
        $this->assertStringNotContainsString('Jane Roof', $csv);
    }

    /* ---- the thing that could hurt somebody ---------------------------------- */

    public function test_a_cell_cannot_run_as_a_formula(): void
    {
        // Typed into a lead by anybody who can add one. Opened in Excel without
        // escaping, this runs.
        $this->lead(['name' => '=HYPERLINK("http://evil","click")']);

        $csv = $this->body($this->actingAs($this->owner)->get(route('admin.leads.export')));

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
    }

    /* ---- deals --------------------------------------------------------------- */

    public function test_the_deals_file_leaves_the_card_details_behind(): void
    {
        Deal::create([
            'company_id' => $this->company->id,
            'name' => 'Roof Co',
            'value' => 2500,
            'status' => 'WON',
            'business_owner_name' => 'Jane Roof',
            'card_number' => '4111111111111111',
            'card_cvv' => '123',
        ]);

        $csv = $this->body($this->actingAs($this->owner)->get(route('admin.deals.export')));

        $this->assertStringContainsString('Roof Co', $csv);
        $this->assertStringContainsString('2500', $csv);

        // They are encrypted at rest precisely so they do not leave the system.
        $this->assertStringNotContainsString('4111111111111111', $csv);
        $this->assertStringNotContainsString('Card', $csv);
    }

    public function test_an_agent_cannot_download_the_deals(): void
    {
        $agent = User::create([
            'company_id' => $this->company->id,
            'name' => 'Ali',
            'username' => 'ali',
            'email' => 'ali@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'agent',
            'permissions' => ['dashboard', 'deals'],
        ]);

        $this->actingAs($agent)->get(route('admin.deals.export'))->assertForbidden();
    }

    /* ---- handovers ----------------------------------------------------------- */

    public function test_the_handovers_file_carries_what_each_one_came_to(): void
    {
        $deal = Deal::create([
            'company_id' => $this->company->id,
            'name' => 'Gutter Co',
            'value' => 4321,
            'status' => 'PENDING',
        ]);

        TransferredLead::create([
            'company_id' => $this->company->id,
            'agent_id' => $this->owner->id,
            'customer_name' => 'Gutter Co',
            'business_name' => 'Gutter Co',
            'stage' => 'won',
            'status' => 'Completed',
            'deal_id' => $deal->id,
            'scheduled_at' => now(),
        ]);

        $csv = $this->body($this->actingAs($this->owner)->get(route('admin.transferred_leads.export')));

        $this->assertStringContainsString('Gutter Co', $csv);
        $this->assertStringContainsString('4321', $csv);
        $this->assertStringContainsString('Won', $csv);
    }

    /* ---- and it is written down ---------------------------------------------- */

    public function test_downloading_the_list_is_written_down(): void
    {
        $this->lead();

        $this->actingAs($this->owner)->get(route('admin.leads.export'));

        // Somebody taking a copy of every lead in the company is worth a line
        // in the log.
        $this->assertTrue(SystemLog::where('action_type', 'Exported Leads')->exists());
    }

    /* ---- nobody else's --------------------------------------------------------- */

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

        $this->actingAs($agent)->get(route('admin.leads.export'))->assertForbidden();
    }
}
