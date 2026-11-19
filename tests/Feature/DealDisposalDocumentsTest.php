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

class DealDisposalDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup base config
        ScoreboardStat::create(['monthly_target' => 250000]);

        // Create performers
        SalesPerformer::create(['name' => 'Agent John', 'is_closer' => false]);
        SalesPerformer::create(['name' => 'Closer Melvin', 'is_closer' => true]);

        Storage::fake('public');
    }

    public function test_won_deal_requires_at_least_one_document_on_creation(): void
    {
        $closer = User::create([
            'name' => 'Closer Melvin',
            'email' => 'melvin@test.com',
            'password' => bcrypt('password'),
            'role' => 'closer',
        ]);

        $dealData = [
            'name' => 'Disposed Sale 1',
            'value' => 6000,
            'closed_by' => 'Agent John',
            'closer_by' => 'Closer Melvin',
            'status' => 'WON',
            'business_owner_name' => 'John Owner',
            'business_number' => '1234567890',
            'business_email' => 'owner@example.com',
            'business_address' => '123 Address',
            'services_offered' => 'Web Dev',
        ];

        // 1. Try to save WON deal without any files -> should fail validation
        $response = $this->actingAs($closer)->post(route('admin.deals.store'), $dealData);
        $response->assertSessionHasErrors(['docusign_file']);
        $this->assertDatabaseMissing('deals', ['name' => 'Disposed Sale 1']);

        // 2. Add just ONE fake file and try again -> should succeed
        $dealData['docusign_file'] = UploadedFile::fake()->create('docusign.pdf', 100);

        $response2 = $this->actingAs($closer)->post(route('admin.deals.store'), $dealData);
        $response2->assertStatus(302);
        
        $deal = Deal::where('name', 'Disposed Sale 1')->first();
        $this->assertNotNull($deal);
        $this->assertNotNull($deal->docusign_file);
        $this->assertNull($deal->proposal_pdf);
        $this->assertNull($deal->payment_auth_file);

        // Verify storage exists
        Storage::disk('public')->assertExists($deal->docusign_file);
    }

    public function test_pending_deal_does_not_require_documents_on_creation(): void
    {
        $closer = User::create([
            'name' => 'Closer Melvin',
            'email' => 'melvin@test.com',
            'password' => bcrypt('password'),
            'role' => 'closer',
        ]);

        $dealData = [
            'name' => 'Pending Sale 1',
            'value' => 3000,
            'closed_by' => 'Agent John',
            'closer_by' => 'Closer Melvin',
            'status' => 'PENDING',
            'business_owner_name' => 'John Owner',
            'business_number' => '1234567890',
            'business_email' => 'owner@example.com',
            'business_address' => '123 Address',
            'services_offered' => 'Web Dev',
        ];

        $response = $this->actingAs($closer)->post(route('admin.deals.store'), $dealData);
        $response->assertStatus(302);
        
        $deal = Deal::where('name', 'Pending Sale 1')->first();
        $this->assertNotNull($deal);
        $this->assertNull($deal->docusign_file);
        $this->assertNull($deal->proposal_pdf);
        $this->assertNull($deal->payment_auth_file);
    }

    public function test_updating_pending_deal_to_won_requires_at_least_one_document(): void
    {
        $closer = User::create([
            'name' => 'Closer Melvin',
            'email' => 'melvin@test.com',
            'password' => bcrypt('password'),
            'role' => 'closer',
        ]);

        $deal = Deal::create([
            'name' => 'Active Deal',
            'value' => 3000,
            'closed_by' => 'Agent John',
            'closer_by' => 'Closer Melvin',
            'status' => 'PENDING',
            'business_owner_name' => 'John Owner',
            'business_number' => '1234567890',
            'business_email' => 'owner@example.com',
            'business_address' => '123 Address',
            'services_offered' => 'Web Dev',
        ]);

        $updateData = [
            'name' => 'Active Deal',
            'value' => 3000,
            'closed_by' => 'Agent John',
            'closer_by' => 'Closer Melvin',
            'status' => 'WON',
            'business_owner_name' => 'John Owner',
            'business_number' => '1234567890',
            'business_email' => 'owner@example.com',
            'business_address' => '123 Address',
            'services_offered' => 'Web Dev',
        ];

        // 1. Try updating to WON without uploading documents -> should fail
        $response = $this->actingAs($closer)->put(route('admin.deals.update', $deal->id), $updateData);
        $response->assertSessionHasErrors(['docusign_file']);
        
        $deal = $deal->fresh();
        $this->assertEquals('PENDING', $deal->status);

        // 2. Try with uploading one file -> should succeed
        $updateData['proposal_pdf'] = UploadedFile::fake()->create('proposal.pdf', 150);

        $response2 = $this->actingAs($closer)->put(route('admin.deals.update', $deal->id), $updateData);
        $response2->assertStatus(302);

        $deal = $deal->fresh();
        $this->assertEquals('WON', $deal->status);
        $this->assertNull($deal->docusign_file);
        $this->assertNotNull($deal->proposal_pdf);
        $this->assertNull($deal->payment_auth_file);
    }

    public function test_updating_already_won_deal_does_not_require_uploading_files_again(): void
    {
        $closer = User::create([
            'name' => 'Closer Melvin',
            'email' => 'melvin@test.com',
            'password' => bcrypt('password'),
            'role' => 'closer',
        ]);

        $deal = Deal::create([
            'name' => 'Won Deal',
            'value' => 3000,
            'closed_by' => 'Agent John',
            'closer_by' => 'Closer Melvin',
            'status' => 'WON',
            'business_owner_name' => 'John Owner',
            'business_number' => '1234567890',
            'business_email' => 'owner@example.com',
            'business_address' => '123 Address',
            'services_offered' => 'Web Dev',
            'docusign_file' => 'deals/documents/doc.pdf',
        ]);

        $updateData = [
            'name' => 'Won Deal Updated Title',
            'value' => 3200,
            'closed_by' => 'Agent John',
            'closer_by' => 'Closer Melvin',
            'status' => 'WON',
            'business_owner_name' => 'John Owner',
            'business_number' => '1234567890',
            'business_email' => 'owner@example.com',
            'business_address' => '123 Address',
            'services_offered' => 'Web Dev',
        ];

        // Update other fields while keeping status WON without submitting new files -> should succeed
        $response = $this->actingAs($closer)->put(route('admin.deals.update', $deal->id), $updateData);
        $response->assertStatus(302);

        $deal = $deal->fresh();
        $this->assertEquals('Won Deal Updated Title', $deal->name);
        $this->assertEquals(3200, $deal->value);
        $this->assertEquals('deals/documents/doc.pdf', $deal->docusign_file);
    }
}
