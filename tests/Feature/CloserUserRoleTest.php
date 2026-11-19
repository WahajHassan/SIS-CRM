<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Deal;
use App\Models\SalesPerformer;
use App\Models\ScoreboardStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CloserUserRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup base target
        ScoreboardStat::create(['monthly_target' => 250000]);

        Storage::fake('public');
    }

    public function test_admin_can_create_closer_user(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $performer = SalesPerformer::create([
            'name' => 'Closer Melvin',
            'is_closer' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Melvin Brown',
            'username' => 'melvinb',
            'email' => 'melvin@test.com',
            'password' => 'melvin123',
            'role' => 'closer',
            'sales_performer_id' => $performer->id,
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('users', [
            'email' => 'melvin@test.com',
            'role' => 'closer',
            'sales_performer_id' => $performer->id,
        ]);
    }

    public function test_admin_cannot_create_closer_user_with_agent_performer(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $performer = SalesPerformer::create([
            'name' => 'Agent Melvin',
            'is_closer' => false,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Melvin Brown',
            'username' => 'melvinb',
            'email' => 'melvin@test.com',
            'password' => 'melvin123',
            'role' => 'closer',
            'sales_performer_id' => $performer->id,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('sales_performer_id');
        $this->assertDatabaseMissing('users', [
            'email' => 'melvin@test.com',
        ]);
    }

    public function test_admin_cannot_create_agent_user_with_closer_performer(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $performer = SalesPerformer::create([
            'name' => 'Closer Melvin',
            'is_closer' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Melvin Brown',
            'username' => 'melvinb',
            'email' => 'melvin@test.com',
            'password' => 'melvin123',
            'role' => 'agent',
            'sales_performer_id' => $performer->id,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('sales_performer_id');
        $this->assertDatabaseMissing('users', [
            'email' => 'melvin@test.com',
        ]);
    }

    public function test_admin_cannot_update_user_to_closer_with_agent_performer(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $agent = User::create([
            'name' => 'Melvin Brown',
            'email' => 'melvin@test.com',
            'password' => bcrypt('password'),
            'role' => 'agent',
        ]);

        $performer = SalesPerformer::create([
            'name' => 'Agent Melvin',
            'is_closer' => false,
        ]);

        $response = $this->actingAs($admin)->put(route('admin.users.update', $agent->id), [
            'username' => 'melvinb',
            'role' => 'closer',
            'sales_performer_id' => $performer->id,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('sales_performer_id');
    }


    public function test_closer_dashboard_analytics_filtering(): void
    {
        $performerMelvin = SalesPerformer::create([
            'name' => 'Melvin Brown',
            'is_closer' => true,
        ]);

        $performerOther = SalesPerformer::create([
            'name' => 'Other Agent',
            'is_closer' => false,
        ]);

        $closerUser = User::create([
            'name' => 'Melvin Brown',
            'email' => 'melvin@test.com',
            'password' => bcrypt('password'),
            'role' => 'closer',
            'sales_performer_id' => $performerMelvin->id,
        ]);

        // Deal closed by Melvin Brown
        $deal1 = Deal::create([
            'name' => 'Project Melvin',
            'value' => 5000,
            'closed_by' => 'Other Agent',
            'closer_by' => 'Melvin Brown',
            'status' => 'WON',
            'business_owner_name' => 'John Doe',
            'business_number' => '1234567890',
            'business_email' => 'owner@example.com',
            'business_address' => '123 Main St',
            'services_offered' => 'Web Dev',
        ]);

        // Deal not associated with Melvin Brown
        $deal2 = Deal::create([
            'name' => 'Project Other',
            'value' => 3000,
            'closed_by' => 'Other Agent',
            'closer_by' => 'Some Other Closer',
            'status' => 'WON',
            'business_owner_name' => 'Jane Smith',
            'business_number' => '0987654321',
            'business_email' => 'owner2@example.com',
            'business_address' => '456 Elm St',
            'services_offered' => 'SEO',
        ]);

        $response = $this->actingAs($closerUser)->get(route('admin.dashboard'));

        $response->assertStatus(200);
        // Melvin should see his deal's revenue ($5,000)
        $response->assertSee('$5,000');
        // Melvin should NOT see the total revenue ($8,000) as he's filtered to his own
        $response->assertDontSee('$8,000');
    }

    public function test_closer_profile_management(): void
    {
        $performerMelvin = SalesPerformer::create([
            'name' => 'Melvin Brown',
            'is_closer' => true,
        ]);

        $closerUser = User::create([
            'name' => 'Melvin Brown',
            'email' => 'melvin@test.com',
            'password' => bcrypt('password'),
            'role' => 'closer',
            'sales_performer_id' => $performerMelvin->id,
        ]);

        // Load profile
        $responseGet = $this->actingAs($closerUser)->get(route('admin.my_profile'));
        $responseGet->assertStatus(200);
        $responseGet->assertSee('Melvin Brown');

        // Update profile
        $responsePut = $this->actingAs($closerUser)->put(route('admin.my_profile.update'), [
            'name' => 'Melvin Brown Updated',
            'email' => 'melvin@test.com',
            'sudo_name' => 'Melvin Sudo',
            'employee_id_number' => 'EMP123',
            'id_card_number' => 'ID123',
            'contract_type' => 'permanent',
        ]);

        $responsePut->assertStatus(302);

        // The profile form only offers the pseudo name and the document uploads; the
        // employee id, id card number and contract type are shown disabled because an
        // admin owns them, so posting them must not change anything.
        $this->assertDatabaseHas('sales_performers', [
            'id' => $performerMelvin->id,
            'sudo_name' => 'Melvin Sudo',
        ]);

        // The performer name is deliberately left alone: deals are matched to a
        // performer by that name, so renaming here would detach their sales history.
        $performerMelvin->refresh();
        $this->assertSame('Melvin Brown', $performerMelvin->name);
        $this->assertNull($performerMelvin->employee_id_number);
        $this->assertNull($performerMelvin->id_card_number);
        $this->assertNull($performerMelvin->contract_type);

        // The user's own name does change.
        $closerUser->refresh();
        $this->assertSame('Melvin Brown Updated', $closerUser->name);
    }

    public function test_closer_restricted_access(): void
    {
        $performerMelvin = SalesPerformer::create([
            'name' => 'Melvin Brown',
            'is_closer' => true,
        ]);

        $closerUser = User::create([
            'name' => 'Melvin Brown',
            'email' => 'melvin@test.com',
            'password' => bcrypt('password'),
            'role' => 'closer',
            'sales_performer_id' => $performerMelvin->id,
        ]);

        // Cannot view settings
        $this->actingAs($closerUser)->get(route('admin.settings'))->assertStatus(403);

        // Cannot view users list
        $this->actingAs($closerUser)->get(route('admin.users'))->assertStatus(403);

        // Cannot create performers
        $this->actingAs($closerUser)->post(route('admin.performers.store'), [
            'name' => 'New Guy',
        ])->assertStatus(403);
    }

    public function test_closer_can_log_deal(): void
    {
        $performerMelvin = SalesPerformer::create([
            'name' => 'Melvin Brown',
            'is_closer' => true,
        ]);

        $closerUser = User::create([
            'name' => 'Melvin Brown',
            'email' => 'melvin@test.com',
            'password' => bcrypt('password'),
            'role' => 'closer',
            'sales_performer_id' => $performerMelvin->id,
        ]);

        $response = $this->actingAs($closerUser)->post(route('admin.deals.store'), [
            'name' => 'Test Deal Closer',
            'value' => 5000,
            'closed_by' => 'Melvin Brown',
            'closer_by' => 'Melvin Brown',
            'status' => 'WON',
            'business_owner_name' => 'Alice Owner',
            'business_number' => '1234567890',
            'business_email' => 'alice@example.com',
            'business_address' => '789 Pine Rd',
            'services_offered' => 'Marketing',
            'docusign_file' => UploadedFile::fake()->create('docusign.pdf', 100),
            'proposal_pdf' => UploadedFile::fake()->create('proposal.pdf', 100),
            'payment_auth_file' => UploadedFile::fake()->create('auth.png', 100),
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('deals', [
            'name' => 'Test Deal Closer',
            'value' => 5000,
            'closed_by' => 'Melvin Brown',
            'closer_by' => 'Melvin Brown',
            'status' => 'WON',
        ]);
    }

    public function test_closer_can_update_deal(): void
    {
        $performerMelvin = SalesPerformer::create([
            'name' => 'Melvin Brown',
            'is_closer' => true,
        ]);

        $closerUser = User::create([
            'name' => 'Melvin Brown',
            'email' => 'melvin@test.com',
            'password' => bcrypt('password'),
            'role' => 'closer',
            'sales_performer_id' => $performerMelvin->id,
        ]);

        $deal = Deal::create([
            'name' => 'Original Deal',
            'value' => 3000,
            'closed_by' => 'Melvin Brown',
            'closer_by' => 'Melvin Brown',
            'status' => 'PENDING',
            'business_owner_name' => 'Bob Owner',
            'business_number' => '0987654321',
            'business_email' => 'bob@example.com',
            'business_address' => '321 Oak St',
            'services_offered' => 'Web Dev',
        ]);

        $response = $this->actingAs($closerUser)->put(route('admin.deals.update', $deal->id), [
            'name' => 'Updated Deal Title',
            'value' => 4500,
            'closed_by' => 'Melvin Brown',
            'closer_by' => 'Melvin Brown',
            'status' => 'WON',
            'business_owner_name' => 'Bob Owner Updated',
            'business_number' => '0987654321',
            'business_email' => 'bob@example.com',
            'business_address' => '321 Oak St',
            'services_offered' => 'Web Dev & SEO',
            'docusign_file' => UploadedFile::fake()->create('docusign.pdf', 100),
            'proposal_pdf' => UploadedFile::fake()->create('proposal.pdf', 100),
            'payment_auth_file' => UploadedFile::fake()->create('auth.png', 100),
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('deals', [
            'id' => $deal->id,
            'name' => 'Updated Deal Title',
            'value' => 4500,
            'status' => 'WON',
            'business_owner_name' => 'Bob Owner Updated',
            'services_offered' => 'Web Dev & SEO',
        ]);
    }

    public function test_agent_cannot_log_deal(): void
    {
        $performerJohn = SalesPerformer::create([
            'name' => 'John Agent',
            'is_closer' => false,
        ]);

        $agentUser = User::create([
            'name' => 'John Agent',
            'email' => 'john@test.com',
            'password' => bcrypt('password'),
            'role' => 'agent',
            'sales_performer_id' => $performerJohn->id,
        ]);

        $response = $this->actingAs($agentUser)->post(route('admin.deals.store'), [
            'name' => 'Test Deal Agent',
            'value' => 2000,
            'closed_by' => 'John Agent',
            'closer_by' => 'Closer Melvin',
            'status' => 'WON',
            'business_owner_name' => 'Charlie Owner',
            'business_number' => '5555555555',
            'business_email' => 'charlie@example.com',
            'business_address' => '111 Cedar Ln',
            'services_offered' => 'Consulting',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('deals', [
            'name' => 'Test Deal Agent',
        ]);
    }

    public function test_agent_cannot_update_deal(): void
    {
        $performerJohn = SalesPerformer::create([
            'name' => 'John Agent',
            'is_closer' => false,
        ]);

        $agentUser = User::create([
            'name' => 'John Agent',
            'email' => 'john@test.com',
            'password' => bcrypt('password'),
            'role' => 'agent',
            'sales_performer_id' => $performerJohn->id,
        ]);

        $deal = Deal::create([
            'name' => 'Agent Viewable Deal',
            'value' => 3000,
            'closed_by' => 'John Agent',
            'closer_by' => 'Closer Melvin',
            'status' => 'WON',
            'business_owner_name' => 'Charlie Owner',
            'business_number' => '5555555555',
            'business_email' => 'charlie@example.com',
            'business_address' => '111 Cedar Ln',
            'services_offered' => 'Consulting',
        ]);

        $response = $this->actingAs($agentUser)->put(route('admin.deals.update', $deal->id), [
            'name' => 'Hacked Deal Title',
            'value' => 90000,
            'closed_by' => 'John Agent',
            'closer_by' => 'Closer Melvin',
            'status' => 'WON',
            'business_owner_name' => 'Charlie Owner Hacked',
            'business_number' => '5555555555',
            'business_email' => 'charlie@example.com',
            'business_address' => '111 Cedar Ln',
            'services_offered' => 'Consulting',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('deals', [
            'id' => $deal->id,
            'name' => 'Agent Viewable Deal',
            'value' => 3000,
        ]);
    }

    public function test_closer_can_log_and_update_deal_with_card_details(): void
    {
        $performerMelvin = SalesPerformer::create([
            'name' => 'Melvin Brown',
            'is_closer' => true,
        ]);

        $closerUser = User::create([
            'name' => 'Melvin Brown',
            'email' => 'melvin@test.com',
            'password' => bcrypt('password'),
            'role' => 'closer',
            'sales_performer_id' => $performerMelvin->id,
        ]);

        // 1. Log deal with card details
        $response = $this->actingAs($closerUser)->post(route('admin.deals.store'), [
            'name' => 'Closer Card Deal',
            'value' => 5000,
            'closed_by' => 'Melvin Brown',
            'closer_by' => 'Melvin Brown',
            'status' => 'WON',
            'business_owner_name' => 'Alice Owner',
            'business_number' => '1234567890',
            'business_email' => 'alice@example.com',
            'business_address' => '789 Pine Rd',
            'services_offered' => 'Marketing',
            'card_name' => 'Alice Card',
            'card_number' => '1111222233334444',
            'card_expiry' => '12/28',
            'card_cvv' => '123',
            'docusign_file' => UploadedFile::fake()->create('docusign.pdf', 100),
            'proposal_pdf' => UploadedFile::fake()->create('proposal.pdf', 100),
            'payment_auth_file' => UploadedFile::fake()->create('auth.png', 100),
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('deals', ['name' => 'Closer Card Deal']);

        $deal = Deal::where('name', 'Closer Card Deal')->first();

        // Card details are readable through the model but encrypted in the table.
        $this->assertSame('Alice Card', $deal->card_name);
        $this->assertSame('1111222233334444', $deal->card_number);
        $this->assertSame('12/28', $deal->card_expiry);
        $this->assertSame('123', $deal->card_cvv);
        $this->assertStoredEncrypted('deals', $deal->id, 'card_number', '1111222233334444');

        // 2. Update deal card details
        $response2 = $this->actingAs($closerUser)->put(route('admin.deals.update', $deal->id), [
            'name' => 'Closer Card Deal Updated',
            'value' => 5000,
            'closed_by' => 'Melvin Brown',
            'closer_by' => 'Melvin Brown',
            'status' => 'WON',
            'business_owner_name' => 'Alice Owner',
            'business_number' => '1234567890',
            'business_email' => 'alice@example.com',
            'business_address' => '789 Pine Rd',
            'services_offered' => 'Marketing',
            'card_name' => 'Alice Card Updated',
            'card_number' => '5555666677778888',
            'card_expiry' => '10/29',
            'card_cvv' => '999',
            'docusign_file' => UploadedFile::fake()->create('docusign.pdf', 100),
            'proposal_pdf' => UploadedFile::fake()->create('proposal.pdf', 100),
            'payment_auth_file' => UploadedFile::fake()->create('auth.png', 100),
        ]);

        $response2->assertStatus(302);
        $this->assertDatabaseHas('deals', ['id' => $deal->id, 'name' => 'Closer Card Deal Updated']);

        $deal->refresh();
        $this->assertSame('Alice Card Updated', $deal->card_name);
        $this->assertSame('5555666677778888', $deal->card_number);
        $this->assertSame('10/29', $deal->card_expiry);
        $this->assertSame('999', $deal->card_cvv);
        $this->assertStoredEncrypted('deals', $deal->id, 'card_number', '5555666677778888');
    }
}

