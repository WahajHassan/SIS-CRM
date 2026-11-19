<?php

namespace Tests\Feature;

use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Where a profile picture is fetched from.
 *
 * It used to be Storage::url(), which returns a root-relative /storage/x.png.
 * That is right only when the CRM is the whole site. Installed under /crm, as
 * this one is, the browser resolved it against the domain, asked for
 * /storage/x.png, got nothing, and drew a broken-image icon with the alt text
 * spilling out beside it in the header.
 *
 * So what is tested is the shape of the address rather than the picture: it
 * must be built against wherever the application is.
 */
class AvatarUrlTest extends TestCase
{
    use RefreshDatabase;

    private function person(): User
    {
        return User::create([
            'company_id' => $this->company->id,
            'name' => 'Ali Agent',
            'username' => 'ali',
            'email' => 'ali@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'agent',
        ]);
    }

    public function test_no_picture_means_no_address(): void
    {
        $this->assertNull($this->person()->avatar_url);
    }

    public function test_the_address_is_absolute_not_root_relative(): void
    {
        $ali = $this->person();
        $ali->update(['avatar' => 'avatars/ali.png']);

        $url = $ali->fresh()->avatar_url;

        // The whole bug: a leading /storage loses the folder the CRM lives in.
        $this->assertFalse(str_starts_with($url, '/storage/'), 'the address must not be root-relative');
        $this->assertStringStartsWith('http', $url);
        $this->assertStringEndsWith('/storage/avatars/ali.png', $url);
    }

    public function test_it_follows_the_application_into_a_subfolder(): void
    {
        $ali = $this->person();
        $ali->update(['avatar' => 'avatars/ali.png']);

        // The shape of the real install: the CRM under /crm on a domain.
        config(['app.url' => 'https://example.com/crm']);
        \Illuminate\Support\Facades\URL::forceRootUrl('https://example.com/crm');

        // The folder is the whole point. The scheme is settled separately, by
        // URL::forceScheme in AppServiceProvider when APP_URL says https.
        $this->assertSame(
            '/crm/storage/avatars/ali.png',
            parse_url($ali->fresh()->avatar_url, PHP_URL_PATH)
        );
    }

    public function test_a_photograph_hr_took_is_used_when_there_is_nothing_else(): void
    {
        $ali = $this->person();

        EmployeeProfile::create([
            'user_id' => $ali->id,
            'company_id' => $this->company->id,
            'photo_path' => 'hr/photo/ali.jpg',
        ]);

        $this->assertStringEndsWith('/storage/hr/photo/ali.jpg', $ali->fresh()->avatar_url);
    }

    public function test_what_they_uploaded_themselves_wins(): void
    {
        $ali = $this->person();
        $ali->update(['avatar' => 'avatars/ali.png']);

        EmployeeProfile::create([
            'user_id' => $ali->id,
            'company_id' => $this->company->id,
            'photo_path' => 'hr/photo/ali.jpg',
        ]);

        // Theirs is the more recent of the two by definition.
        $this->assertStringEndsWith('/storage/avatars/ali.png', $ali->fresh()->avatar_url);
    }

    public function test_the_letter_is_always_drawn_so_a_broken_picture_is_not_a_broken_icon(): void
    {
        $ali = $this->person();
        $ali->update(['avatar' => 'avatars/gone.png']);

        $drawn = view('components.face', ['user' => $ali->fresh(), 'size' => 40])->render();

        // Both: the letter underneath, and the picture over it that removes
        // itself if it cannot load.
        $this->assertStringContainsString('face-letter', $drawn);
        $this->assertStringContainsString('face-pic', $drawn);
        $this->assertStringContainsString('onerror', $drawn);
    }
}
