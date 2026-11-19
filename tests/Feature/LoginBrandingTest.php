<?php

namespace Tests\Feature;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The login page shows a logo.
 *
 * It used to fall straight from "the company has uploaded a logo" to a wordmark
 * built out of the company's name — so a company that had uploaded nothing saw
 * its logo in the sidebar, which falls back to the mark shipped with the CRM,
 * and its name as plain text on the way in. The two screens disagreed about
 * what the product looks like.
 */
class LoginBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_company_with_no_logo_still_gets_a_mark(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('whitelogo.png', false)
            ->assertDontSee('logo-word"><span>', false);
    }

    public function test_an_uploaded_dark_logo_is_used_on_the_dark_card(): void
    {
        // Written by Settings > Branding, and ignored by this page until now.
        $path = 'uploads/company_logos/test-dark.png';
        @mkdir(public_path('uploads/company_logos'), 0755, true);
        copy(public_path('uploads/company_logos/whitelogo.png'), public_path($path));

        Company::withoutGlobalScopes()->first()->update(['dark_logo' => $path]);

        try {
            $this->get(route('login'))->assertOk()->assertSee('test-dark.png', false);
        } finally {
            @unlink(public_path($path));
        }
    }

    public function test_an_ordinary_logo_is_used_when_there_is_no_dark_one(): void
    {
        $path = 'uploads/company_logos/test-plain.png';
        copy(public_path('uploads/company_logos/logo.webp'), public_path($path));

        Company::withoutGlobalScopes()->first()->update(['logo' => $path, 'dark_logo' => null]);

        try {
            $this->get(route('login'))->assertOk()->assertSee('test-plain.png', false);
        } finally {
            @unlink(public_path($path));
        }
    }

    public function test_a_logo_recorded_but_missing_from_disk_does_not_break_the_page(): void
    {
        Company::withoutGlobalScopes()->first()->update(['logo' => 'uploads/company_logos/deleted.png']);

        // Falls through to the shipped mark rather than drawing a broken image.
        $this->get(route('login'))->assertOk()->assertSee('whitelogo.png', false);
    }
}
