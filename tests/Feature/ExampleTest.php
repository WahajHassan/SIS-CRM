<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        // Create the default ScoreboardStat model since the landing page looks it up
        \App\Models\ScoreboardStat::create(['monthly_target' => 250000]);

        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
