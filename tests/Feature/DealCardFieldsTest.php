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

class DealCardFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup base configuration so dashboard and deals page doesn't break
        ScoreboardStat::create(['monthly_target' => 250000]);

        // Create some performers for closed_by and closer_by dropdowns
        SalesPerformer::create([
            'name' => 'Agent John',
            'is_closer' => false,
        ]);
        SalesPerformer::create([
            'name' => 'Closer Jane',
            'is_closer' => true,
        ]);

        Storage::fake('public');
    }

    public function test_admin_can_log_deal_with_card_details(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $dealData = [
            'name' => 'Project Alpha',
            'value' => 5000,
            'closed_by' => 'Agent John',
            'closer_by' => 'Closer Jane',
            'status' => 'WON',
            'business_owner_name' => 'John Doe',
            'business_number' => '1234567890',
            'business_email' => 'owner@example.com',
            'business_address' => '123 Main St',
            'services_offered' => 'Web Development',
            'card_name' => 'John Doe Card',
            'card_number' => '1111222233334444',
            'card_expiry' => '12/28',
            'card_cvv' => '123',
            'docusign_file' => UploadedFile::fake()->create('doc.pdf', 10),
            'proposal_pdf' => UploadedFile::fake()->create('proposal.pdf', 10),
            'payment_auth_file' => UploadedFile::fake()->create('auth.png', 10),
        ];

        $response = $this->actingAs($admin)->post(route('admin.deals.store'), $dealData);

        $response->assertStatus(302);
        
        $this->assertDatabaseHas('deals', ['name' => 'Project Alpha']);

        // Card details read back as entered, but are not sitting in the table in the
        // clear, so the database and its backups no longer carry usable card data.
        $deal = Deal::where('name', 'Project Alpha')->first();
        $this->assertSame('John Doe Card', $deal->card_name);
        $this->assertSame('1111222233334444', $deal->card_number);
        $this->assertSame('12/28', $deal->card_expiry);
        $this->assertSame('123', $deal->card_cvv);
        $this->assertStoredEncrypted('deals', $deal->id, 'card_number', '1111222233334444');
    }

    public function test_editor_cannot_log_deal_with_card_details(): void
    {
        $editor = User::create([
            'name' => 'Editor User',
            'email' => 'editor@test.com',
            'password' => bcrypt('password'),
            'role' => 'editor',
        ]);

        $dealData = [
            'name' => 'Project Beta',
            'value' => 3000,
            'closed_by' => 'Agent John',
            'closer_by' => 'Closer Jane',
            'status' => 'WON',
            'business_owner_name' => 'Jane Smith',
            'business_number' => '0987654321',
            'business_email' => 'owner2@example.com',
            'business_address' => '456 Elm St',
            'services_offered' => 'SEO',
            'card_name' => 'Should Not Save',
            'card_number' => '5555666677778888',
            'card_expiry' => '01/29',
            'card_cvv' => '999',
            'docusign_file' => UploadedFile::fake()->create('doc.pdf', 10),
            'proposal_pdf' => UploadedFile::fake()->create('proposal.pdf', 10),
            'payment_auth_file' => UploadedFile::fake()->create('auth.png', 10),
        ];

        $response = $this->actingAs($editor)->post(route('admin.deals.store'), $dealData);

        $response->assertStatus(302);

        $this->assertDatabaseHas('deals', [
            'name' => 'Project Beta',
        ]);

        // Assert card fields are null
        $deal = Deal::where('name', 'Project Beta')->first();
        $this->assertNull($deal->card_name);
        $this->assertNull($deal->card_number);
        $this->assertNull($deal->card_expiry);
        $this->assertNull($deal->card_cvv);
    }

    public function test_admin_can_update_deal_card_details(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $deal = Deal::create([
            'name' => 'Project Gamma',
            'value' => 1500,
            'closed_by' => 'Agent John',
            'closer_by' => 'Closer Jane',
            'status' => 'PENDING',
            'business_owner_name' => 'Bob Builder',
            'business_number' => '5551234',
            'business_email' => 'bob@example.com',
            'business_address' => '789 Oak Rd',
            'services_offered' => 'Consulting',
        ]);

        $updateData = [
            'name' => 'Project Gamma Updated',
            'value' => 2000,
            'closed_by' => 'Agent John',
            'closer_by' => 'Closer Jane',
            'status' => 'WON',
            'business_owner_name' => 'Bob Builder',
            'business_number' => '5551234',
            'business_email' => 'bob@example.com',
            'business_address' => '789 Oak Rd',
            'services_offered' => 'Consulting',
            'card_name' => 'Bob Builder Card',
            'card_number' => '9999888877776666',
            'card_expiry' => '05/30',
            'card_cvv' => '456',
            'docusign_file' => UploadedFile::fake()->create('doc.pdf', 10),
            'proposal_pdf' => UploadedFile::fake()->create('proposal.pdf', 10),
            'payment_auth_file' => UploadedFile::fake()->create('auth.png', 10),
        ];

        $response = $this->actingAs($admin)->put(route('admin.deals.update', $deal->id), $updateData);

        $response->assertStatus(302);
        
        $this->assertDatabaseHas('deals', ['id' => $deal->id, 'name' => 'Project Gamma Updated']);

        $deal->refresh();
        $this->assertSame('Bob Builder Card', $deal->card_name);
        $this->assertSame('9999888877776666', $deal->card_number);
        $this->assertSame('05/30', $deal->card_expiry);
        $this->assertSame('456', $deal->card_cvv);
        $this->assertStoredEncrypted('deals', $deal->id, 'card_number', '9999888877776666');
    }

    public function test_editor_cannot_update_deal_card_details(): void
    {
        $editor = User::create([
            'name' => 'Editor User',
            'email' => 'editor@test.com',
            'password' => bcrypt('password'),
            'role' => 'editor',
        ]);

        $deal = Deal::create([
            'name' => 'Project Delta',
            'value' => 4500,
            'closed_by' => 'Agent John',
            'closer_by' => 'Closer Jane',
            'status' => 'PENDING',
            'business_owner_name' => 'Alice Cooper',
            'business_number' => '7778888',
            'business_email' => 'alice@example.com',
            'business_address' => '321 Pine Ln',
            'services_offered' => 'Design',
            'card_name' => 'Original Card Name',
            'card_number' => '1234123412341234',
            'card_expiry' => '10/27',
            'card_cvv' => '777',
        ]);

        $updateData = [
            'name' => 'Project Delta Updated',
            'value' => 4500,
            'closed_by' => 'Agent John',
            'closer_by' => 'Closer Jane',
            'status' => 'WON',
            'business_owner_name' => 'Alice Cooper',
            'business_number' => '7778888',
            'business_email' => 'alice@example.com',
            'business_address' => '321 Pine Ln',
            'services_offered' => 'Design',
            'card_name' => 'Malicious Update',
            'card_number' => '0000000000000000',
            'card_expiry' => '01/25',
            'card_cvv' => '000',
            'docusign_file' => UploadedFile::fake()->create('doc.pdf', 10),
            'proposal_pdf' => UploadedFile::fake()->create('proposal.pdf', 10),
            'payment_auth_file' => UploadedFile::fake()->create('auth.png', 10),
        ];

        $response = $this->actingAs($editor)->put(route('admin.deals.update', $deal->id), $updateData);

        $response->assertStatus(302);

        // Assert other fields updated, but card fields remained unchanged
        $this->assertDatabaseHas('deals', ['id' => $deal->id, 'name' => 'Project Delta Updated']);

        $deal->refresh();
        $this->assertSame('Original Card Name', $deal->card_name);
        $this->assertSame('1234123412341234', $deal->card_number);
        $this->assertSame('10/27', $deal->card_expiry);
        $this->assertSame('777', $deal->card_cvv);
    }

    public function test_card_fields_view_visibility(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $editor = User::create([
            'name' => 'Editor User',
            'email' => 'editor@test.com',
            'password' => bcrypt('password'),
            'role' => 'editor',
        ]);

        // Asserted on the field names rather than the labels, which have been reworded
        // before and say "Name on Card" and "16-digit Card Number" today.
        $responseAdmin = $this->actingAs($admin)->get(route('admin.deals'));
        $responseAdmin->assertStatus(200);
        $responseAdmin->assertSee('name="card_name"', false);
        $responseAdmin->assertSee('name="card_number"', false);

        // An editor must not be offered the card inputs at all
        $responseEditor = $this->actingAs($editor)->get(route('admin.deals'));
        $responseEditor->assertStatus(200);
        $responseEditor->assertDontSee('name="card_name"', false);
        $responseEditor->assertDontSee('name="card_number"', false);
    }

    public function test_deals_search_filter_resolves_sudo_names(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $performer = SalesPerformer::create([
            'name' => 'George Harrison',
            'sudo_name' => 'George Sudo',
            'is_closer' => false,
        ]);

        $deal = Deal::create([
            'name' => 'Specific Deal Name',
            'value' => 5000,
            'closed_by' => 'George Harrison',
            'closer_by' => 'Closer Jane',
            'status' => 'WON',
            'business_owner_name' => 'Owner Name',
            'business_number' => '12345',
            'business_email' => 'owner@test.com',
            'business_address' => 'Addr',
            'services_offered' => 'Web',
        ]);

        // 1. Search by real name
        $response1 = $this->actingAs($admin)->get(route('admin.deals', ['search' => 'Harrison']));
        $response1->assertStatus(200);
        $response1->assertSee('Specific Deal Name');

        // 2. Search by sudo name
        $response2 = $this->actingAs($admin)->get(route('admin.deals', ['search' => 'Sudo']));
        $response2->assertStatus(200);
        $response2->assertSee('Specific Deal Name');

        // 3. Search by deal name
        $response3 = $this->actingAs($admin)->get(route('admin.deals', ['search' => 'Specific']));
        $response3->assertStatus(200);
        $response3->assertSee('Specific Deal Name');

        // 4. Search by business owner name
        $response4 = $this->actingAs($admin)->get(route('admin.deals', ['search' => 'Owner']));
        $response4->assertStatus(200);
        $response4->assertSee('Specific Deal Name');

        // 5. Search by non-existent term
        $response5 = $this->actingAs($admin)->get(route('admin.deals', ['search' => 'Nonexistent']));
        $response5->assertStatus(200);
        $response5->assertDontSee('Specific Deal Name');
    }

    public function test_performer_effective_transferred_includes_sales(): void
    {
        $performer = SalesPerformer::create([
            'name' => 'John Doe',
            'is_closer' => false,
            'number_of_transferred' => 5,
            'number_of_sales' => 2,
        ]);

        // Initially, sales is 2 (manual). So effective transfers is 5 + 2 = 7.
        $this->assertEquals(2, $performer->effective_sales);
        $this->assertEquals(7, $performer->effective_transferred);

        // Add won deals to increment sales dynamically
        Deal::create([
            'name' => 'Dynamic Deal 1',
            'value' => 1000,
            'closed_by' => 'John Doe',
            'closer_by' => 'Closer Jane',
            'status' => 'WON',
            'business_owner_name' => 'Owner',
            'business_number' => '123',
            'business_email' => 'owner@test.com',
            'business_address' => 'Addr',
            'services_offered' => 'Web',
        ]);
        Deal::create([
            'name' => 'Dynamic Deal 2',
            'value' => 2000,
            'closed_by' => 'John Doe',
            'closer_by' => 'Closer Jane',
            'status' => 'WON',
            'business_owner_name' => 'Owner',
            'business_number' => '123',
            'business_email' => 'owner@test.com',
            'business_address' => 'Addr',
            'services_offered' => 'Web',
        ]);
        Deal::create([
            'name' => 'Dynamic Deal 3',
            'value' => 3000,
            'closed_by' => 'John Doe',
            'closer_by' => 'Closer Jane',
            'status' => 'WON',
            'business_owner_name' => 'Owner',
            'business_number' => '123',
            'business_email' => 'owner@test.com',
            'business_address' => 'Addr',
            'services_offered' => 'Web',
        ]);

        // Refresh and check that sales and transfers updated
        $performer = $performer->fresh();

        // Now, deals count is 3. effective_sales is max(3, 2) = 3.
        // effective_transferred should be base transfers (5) + sales (3) = 8.
        $this->assertEquals(3, $performer->effective_sales);
        $this->assertEquals(8, $performer->effective_transferred);
    }
}


