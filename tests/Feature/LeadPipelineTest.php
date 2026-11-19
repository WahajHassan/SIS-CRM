<?php

namespace Tests\Feature;

use App\Models\BusinessCategory;
use App\Models\City;
use App\Models\Lead;
use App\Models\LeadStageLog;
use App\Models\TransferredLead;
use App\Models\UsaState;
use App\Models\User;
use App\Services\LeadPipeline;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The road from a call to a closed deal.
 *
 * These are the rules that are expensive to get wrong and awkward to check by
 * looking at a screen: a lead reaching the pool rather than vanishing, two
 * closers pressing Claim at the same moment, and a win carrying everything the
 * agent typed into the deal instead of asking for it twice.
 */
class LeadPipelineTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;
    private User $closer;
    private User $otherCloser;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-05 20:00'));

        $this->agent = $this->person('Agent Ali', 'agent');
        $this->closer = $this->person('Closer Sana', 'closer');
        $this->otherCloser = $this->person('Closer Bilal', 'closer');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function person(string $name, string $role): User
    {
        return User::create([
            'company_id' => $this->company->id,
            'name' => $name,
            'username' => strtolower(str_replace(' ', '', $name)),
            'email' => strtolower(str_replace(' ', '', $name)) . '@example.com',
            'password' => bcrypt('secret123'),
            'role' => $role,
        ]);
    }

    private function lead(): Lead
    {
        // The same trade and place every time: a test that hands two leads over
        // should not be creating Arizona twice.
        $trade = BusinessCategory::firstOrCreate(
            ['company_id' => $this->company->id, 'name' => 'Gutter'],
            ['is_active' => true]
        );

        $state = UsaState::firstOrCreate(
            ['company_id' => $this->company->id, 'name' => 'Arizona'],
            ['is_active' => true]
        );

        $city = City::firstOrCreate(
            ['company_id' => $this->company->id, 'usa_state_id' => $state->id, 'name' => 'Tucson'],
            ['is_active' => true]
        );

        return Lead::create([
            'company_id' => $this->company->id,
            'name' => 'Dave Owner',
            'business_name' => 'Tucson Gutter Pros',
            'phone' => '520-555-0101',
            'email' => 'dave@gutterpros.test',
            'business_category_id' => $trade->id,
            'usa_state_id' => $state->id,
            'city_id' => $city->id,
            'address' => '12 Speedway Blvd',
            'source' => 'Google Maps',
            'status' => 'Interested',
            'created_by' => $this->agent->id,
        ]);
    }

    private function handOver(?Lead $lead = null, array $extra = []): TransferredLead
    {
        return (new LeadPipeline())->transfer(array_merge([
            'company_id' => $this->company->id,
            'customer_name' => 'Dave Owner',
            'phone' => '520-555-0101',
            'scheduled_at' => now()->addHours(2),
        ], $extra), $this->agent, $lead ?: $this->lead());
    }

    public function test_a_lead_handed_over_with_nobody_named_waits_in_the_pool(): void
    {
        $transfer = $this->handOver();

        $this->assertSame('pool', $transfer->stage);
        $this->assertNull($transfer->closer_id);
        $this->assertNull($transfer->claimed_at);

        $board = (new LeadPipeline())->board($this->closer);
        $this->assertCount(1, $board['pool'], 'it is waiting for anybody to take');
        $this->assertCount(0, $board['mine'], 'and on nobody\'s desk yet');
    }

    public function test_what_the_agent_already_knows_travels_with_the_lead(): void
    {
        $lead = $this->lead();
        $transfer = $this->handOver($lead);

        $this->assertSame($lead->id, $transfer->lead_id);
        $this->assertSame('Tucson Gutter Pros', $transfer->business_name);
        $this->assertSame($lead->business_category_id, $transfer->business_category_id);
        $this->assertSame($lead->city_id, $transfer->city_id);
        $this->assertSame('Tucson, Arizona', $transfer->place());
    }

    public function test_naming_a_closer_puts_it_straight_on_their_desk(): void
    {
        $transfer = $this->handOver(null, ['closer_id' => $this->closer->id]);

        $this->assertSame('claimed', $transfer->stage);
        $this->assertNotNull($transfer->claimed_at);

        $board = (new LeadPipeline())->board($this->closer);
        $this->assertCount(0, $board['pool']);
        $this->assertCount(1, $board['mine']);
    }

    public function test_only_the_first_closer_to_claim_gets_it(): void
    {
        $transfer = $this->handOver();
        $pipeline = new LeadPipeline();

        $this->assertTrue($pipeline->claim($transfer, $this->closer), 'the first one takes it');

        $transfer->refresh();
        $this->assertFalse($pipeline->claim($transfer, $this->otherCloser), 'the second is too late');

        $transfer->refresh();
        $this->assertSame($this->closer->id, $transfer->closer_id, 'and it stays with whoever was first');
    }

    public function test_a_released_lead_goes_back_for_somebody_else(): void
    {
        $transfer = $this->handOver();
        $pipeline = new LeadPipeline();

        $pipeline->claim($transfer, $this->closer);
        $transfer->refresh();

        $pipeline->release($transfer, $this->closer, 'Not my patch');
        $transfer->refresh();

        $this->assertSame('pool', $transfer->stage);
        $this->assertNull($transfer->closer_id);
        $this->assertTrue($pipeline->claim($transfer, $this->otherCloser), 'and the next closer can take it');
    }

    public function test_every_move_is_recorded(): void
    {
        $transfer = $this->handOver();
        $pipeline = new LeadPipeline();

        $pipeline->claim($transfer, $this->closer);
        $transfer->refresh();
        $pipeline->moveTo($transfer, 'contacted', $this->closer);
        $transfer->refresh();
        $pipeline->moveTo($transfer, 'proposal', $this->closer, 'Sent the quote');

        $moves = LeadStageLog::where('transferred_lead_id', $transfer->id)
            ->orderBy('id')
            ->pluck('to_stage')
            ->all();

        $this->assertSame(['pool', 'claimed', 'contacted', 'proposal'], $moves);

        $last = LeadStageLog::where('transferred_lead_id', $transfer->id)->orderByDesc('id')->first();
        $this->assertSame($this->closer->id, $last->user_id, 'and who made each one');
        $this->assertSame('Sent the quote', $last->note);
    }

    public function test_marking_a_proposal_sent_is_remembered_on_the_transfer(): void
    {
        $transfer = $this->handOver(null, ['closer_id' => $this->closer->id]);

        (new LeadPipeline())->moveTo($transfer, 'proposal', $this->closer);
        $transfer->refresh();

        $this->assertTrue($transfer->is_proposal_sent, 'the old flag keeps up with the new stage');
    }

    public function test_losing_one_records_the_reason_and_closes_it(): void
    {
        $transfer = $this->handOver(null, ['closer_id' => $this->closer->id]);

        (new LeadPipeline())->moveTo($transfer, 'lost', $this->closer, 'Went with a cheaper quote');
        $transfer->refresh();

        $this->assertSame('lost', $transfer->stage);
        $this->assertSame('Went with a cheaper quote', $transfer->lost_reason);
        $this->assertNotNull($transfer->closed_at);
        $this->assertTrue($transfer->isClosed());

        $board = (new LeadPipeline())->board($this->closer);
        $this->assertCount(0, $board['mine'], 'and it is off the desk');
    }

    public function test_winning_one_opens_the_deal_filled_in_from_the_transfer(): void
    {
        $lead = $this->lead();
        $transfer = $this->handOver($lead, [
            'closer_id' => $this->closer->id,
            'email' => 'dave@gutterpros.test',
            'address' => '12 Speedway Blvd',
            'owner_name' => 'Dave Owner',
        ]);

        $deal = (new LeadPipeline())->convert($transfer, $this->closer);
        $transfer->refresh();

        $this->assertSame('Tucson Gutter Pros', $deal->name, 'the deal is named after the business');
        $this->assertSame('Dave Owner', $deal->business_owner_name);
        $this->assertSame('520-555-0101', $deal->business_number);
        $this->assertSame('12 Speedway Blvd', $deal->business_address);
        $this->assertSame('Tucson, Arizona', $deal->areas_covered);
        $this->assertSame('Gutter', $deal->services_offered);

        // and the chain holds in both directions
        $this->assertSame($transfer->id, $deal->transferred_lead_id);
        $this->assertSame($lead->id, $deal->lead_id);
        $this->assertSame($this->agent->id, $deal->agent_id);
        $this->assertSame($this->closer->id, $deal->closer_user_id);
        $this->assertSame($deal->id, $transfer->deal_id);
        $this->assertSame('won', $transfer->stage);
        $this->assertTrue($transfer->is_converted);
    }

    public function test_converting_the_same_transfer_twice_does_not_make_two_deals(): void
    {
        $transfer = $this->handOver(null, ['closer_id' => $this->closer->id]);
        $pipeline = new LeadPipeline();

        $first = $pipeline->convert($transfer, $this->closer);
        $transfer->refresh();
        $second = $pipeline->convert($transfer, $this->closer);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, \App\Models\Deal::count());
    }

    public function test_the_scoreboard_is_told_about_the_work(): void
    {
        // The board is built from sales performers, and a login points at one.
        $performer = \App\Models\SalesPerformer::create([
            'company_id' => $this->company->id,
            'name' => 'Closer Sana',
            'role' => 'Closer',
            'is_closer' => true,
            'goal' => 10000,
            'status' => 'ACTIVE',
        ]);

        $this->closer->update(['sales_performer_id' => $performer->id]);
        $this->closer->refresh();

        $transfer = $this->handOver(null, ['closer_id' => $this->closer->id]);
        (new LeadPipeline())->convert($transfer, $this->closer);

        $stat = \App\Models\MonthlyPerformerStat::where('sales_performer_id', $performer->id)
            ->where('month', now()->month)
            ->where('year', now()->year)
            ->first();

        $this->assertNotNull($stat, 'the month has a figure for them');
        $this->assertSame(1, (int) $stat->number_of_sales, 'and the sale is counted on it');

        $performer->refresh();
        $this->assertSame(1, (int) $performer->number_of_sales);
    }

    public function test_a_handover_is_counted_for_the_agent_who_made_it(): void
    {
        $performer = \App\Models\SalesPerformer::create([
            'company_id' => $this->company->id,
            'name' => 'Agent Ali',
            'role' => 'Sales Agent',
            'is_closer' => false,
            'goal' => 6000,
            'status' => 'ACTIVE',
        ]);

        $this->agent->update(['sales_performer_id' => $performer->id]);
        $this->agent->refresh();

        $this->handOver();
        $this->handOver();

        $stat = \App\Models\MonthlyPerformerStat::where('sales_performer_id', $performer->id)->first();

        $this->assertNotNull($stat);
        $this->assertSame(2, (int) $stat->number_of_transferred, 'both handovers reach the board');
    }

    public function test_how_long_a_lead_waited_is_answerable(): void
    {
        $transfer = $this->handOver();

        Carbon::setTestNow(now()->addMinutes(25));
        (new LeadPipeline())->claim($transfer, $this->closer);
        $transfer->refresh();

        $this->assertSame(25, $transfer->waitedMinutes());
    }
}
