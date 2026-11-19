<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\EmployeeProfile;
use App\Models\Training;
use App\Models\TrainingComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The training room's rules.
 *
 * Who may put something up, who it reaches, what a pasted link turns into, and
 * what happens to text somebody types into the notes — that last one being the
 * place where a training module becomes a way to run a script in everybody's
 * browser if nobody checks.
 */
class TrainingTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;
    private User $sales;
    private User $tech;
    private Department $salesTeam;
    private Department $techTeam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salesTeam = Department::create([
            'company_id' => $this->company->id, 'name' => 'Sales', 'code' => 'SAL', 'is_active' => true,
        ]);

        $this->techTeam = Department::create([
            'company_id' => $this->company->id, 'name' => 'Tech', 'code' => 'TEC', 'is_active' => true,
        ]);

        $this->hr = $this->person('Hr Lead', 'hr', null);
        $this->sales = $this->person('Sales Agent', 'agent', $this->salesTeam);
        $this->tech = $this->person('Tech Person', 'agent', $this->techTeam);
    }

    private function person(string $name, string $role, ?Department $department): User
    {
        $user = User::create([
            'company_id' => $this->company->id,
            'name' => $name,
            'username' => strtolower(str_replace(' ', '', $name)),
            'email' => strtolower(str_replace(' ', '', $name)) . '@example.com',
            'password' => bcrypt('secret123'),
            'role' => $role,
        ]);

        if ($department) {
            EmployeeProfile::create([
                'company_id' => $this->company->id,
                'user_id' => $user->id,
                'department_id' => $department->id,
            ]);
        }

        return $user;
    }

    private function putUp(array $attributes = []): Training
    {
        return Training::create(array_merge([
            'company_id' => $this->company->id,
            'created_by' => $this->hr->id,
            'title' => 'Handling the first objection',
            'kind' => 'video',
            'media_url' => 'https://www.youtube.com/watch?v=abc123XYZ',
            'is_published' => true,
        ], $attributes));
    }

    // ---- who runs it --------------------------------------------------------

    public function test_hr_and_admins_put_training_up_and_nobody_else_does(): void
    {
        $admin = $this->person('An Admin', 'admin', null);

        $this->assertTrue(Training::canManage($this->hr));
        $this->assertTrue(Training::canManage($admin));
        $this->assertFalse(Training::canManage($this->sales));
        $this->assertFalse(Training::canManage(null));
    }

    public function test_an_agent_is_refused_when_they_try_to_put_training_up(): void
    {
        $this->actingAs($this->sales)
            ->post(route('admin.training.store'), [
                'title' => 'My own training',
                'kind' => 'text',
                'body' => 'Something',
            ])
            ->assertForbidden();

        $this->assertSame(0, Training::count());
    }

    // ---- who it reaches -----------------------------------------------------

    public function test_training_for_a_department_reaches_that_department_only(): void
    {
        $this->putUp(['title' => 'Sales only', 'department_id' => $this->salesTeam->id]);
        $this->putUp(['title' => 'Everybody', 'department_id' => null]);

        $forSales = Training::for($this->sales)->pluck('title')->all();
        $forTech = Training::for($this->tech)->pluck('title')->all();

        $this->assertContains('Sales only', $forSales);
        $this->assertContains('Everybody', $forSales);

        $this->assertNotContains('Sales only', $forTech, 'another department does not see it');
        $this->assertContains('Everybody', $forTech);
    }

    public function test_whoever_runs_it_sees_everything_including_what_is_not_published(): void
    {
        $this->putUp(['title' => 'Draft one', 'is_published' => false]);

        $this->assertCount(1, Training::for($this->hr)->get(), 'HR sees the unpublished one');
        $this->assertCount(0, Training::for($this->sales)->get(), 'the floor does not');
    }

    // ---- the written part ---------------------------------------------------

    public function test_the_marks_that_matter_become_formatting(): void
    {
        $training = $this->putUp([
            'kind' => 'text',
            'media_url' => null,
            'body' => "Open with your name.\n==Never ask how they are.==\n**Then** give a reason.\n- Ask why\n- Then wait",
        ]);

        $html = $training->bodyHtml();

        $this->assertStringContainsString('<mark>Never ask how they are.</mark>', $html);
        $this->assertStringContainsString('<strong>Then</strong>', $html);
        $this->assertStringContainsString('<li>Ask why</li>', $html);
    }

    public function test_anything_else_typed_in_stays_text(): void
    {
        $training = $this->putUp([
            'kind' => 'text',
            'media_url' => null,
            'body' => '<script>alert("x")</script> and <img src=x onerror=alert(1)>',
        ]);

        $html = $training->bodyHtml();

        $this->assertStringNotContainsString('<script>', $html, 'a pasted script is not a script');
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html, 'it is shown as what it is');
    }

    // ---- recordings ---------------------------------------------------------

    public function test_a_pasted_link_becomes_one_that_can_actually_be_played(): void
    {
        $cases = [
            'https://www.youtube.com/watch?v=abc123XYZ' => 'https://www.youtube.com/embed/abc123XYZ',
            'https://youtu.be/abc123XYZ' => 'https://www.youtube.com/embed/abc123XYZ',
            'https://vimeo.com/123456789' => 'https://player.vimeo.com/video/123456789',
            'https://drive.google.com/file/d/1AbC_dEf/view?usp=sharing' => 'https://drive.google.com/file/d/1AbC_dEf/preview',
            'https://www.loom.com/share/abcdef123456' => 'https://www.loom.com/embed/abcdef123456',
        ];

        foreach ($cases as $pasted => $expected) {
            $training = $this->putUp(['media_url' => $pasted]);

            $this->assertSame($expected, $training->embedUrl(), "for {$pasted}");
        }
    }

    public function test_a_link_nobody_knows_how_to_frame_is_left_alone(): void
    {
        $training = $this->putUp(['media_url' => 'https://files.example.com/training.mp4']);

        $this->assertNull($training->embedUrl(), 'nothing is invented for it');
        $this->assertSame('https://files.example.com/training.mp4', $training->mediaSrc(), 'but it can still be opened');
    }

    public function test_a_recording_needs_a_file_or_a_link(): void
    {
        $this->actingAs($this->hr)
            ->post(route('admin.training.store'), [
                'title' => 'Nothing attached',
                'kind' => 'video',
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, Training::count());
    }

    // ---- watching and asking ------------------------------------------------

    public function test_opening_it_counts_as_watching_it_once(): void
    {
        $training = $this->putUp();

        $this->actingAs($this->sales)->get(route('admin.training.show', $training->id));
        $this->actingAs($this->sales)->get(route('admin.training.show', $training->id));

        $this->assertSame(1, $training->views()->count(), 'twice through does not count twice');
        $this->assertTrue($training->fresh()->watchedBy($this->sales));
        $this->assertFalse($training->fresh()->watchedBy($this->tech));
    }

    public function test_the_floor_can_ask_about_it(): void
    {
        $training = $this->putUp();

        $this->actingAs($this->sales)
            ->postJson(route('admin.training.comment', $training->id), ['body' => 'What if they hang up?'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(1, $training->comments()->count());
    }

    public function test_a_comment_can_be_taken_down_by_its_author_or_by_hr_and_by_nobody_else(): void
    {
        $training = $this->putUp();

        $comment = TrainingComment::create([
            'company_id' => $this->company->id,
            'training_id' => $training->id,
            'user_id' => $this->sales->id,
            'body' => 'Mine',
        ]);

        $this->assertTrue($comment->canBeRemovedBy($this->sales), 'their own');
        $this->assertTrue($comment->canBeRemovedBy($this->hr), 'and whoever runs it');
        $this->assertFalse($comment->canBeRemovedBy($this->tech), 'but not somebody else entirely');

        $this->actingAs($this->tech)
            ->deleteJson(route('admin.training.comment.remove', $comment->id))
            ->assertForbidden();

        $this->actingAs($this->sales)
            ->deleteJson(route('admin.training.comment.remove', $comment->id))
            ->assertOk();

        $this->assertSame(0, $training->comments()->count());
    }

    // ---- recordings sent a piece at a time ----------------------------------

    public function test_a_recording_sent_in_pieces_is_put_back_together(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        // A real little wav, split in three, arriving one piece per request.
        $wav = $this->wavBytes();
        $pieces = str_split($wav, (int) ceil(strlen($wav) / 3));
        $uploadId = 'abcdef1234567890';
        $path = null;

        foreach ($pieces as $index => $piece) {
            $response = $this->actingAs($this->hr)->postJson(route('admin.training.chunk'), [
                'upload_id' => $uploadId,
                'index' => $index,
                'total' => count($pieces),
                'extension' => 'wav',
                'chunk' => \Illuminate\Http\UploadedFile::fake()->createWithContent('part', $piece),
            ]);

            $response->assertOk();
            $path = $response->json('path') ?: $path;
        }

        $this->assertNotNull($path, 'the last piece hands back where it was stored');
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($path);

        $this->assertSame(
            strlen($wav),
            \Illuminate\Support\Facades\Storage::disk('public')->size($path),
            'and it is the whole file, not one piece of it'
        );
    }

    public function test_a_file_that_is_not_a_recording_is_refused_once_it_is_whole(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $this->actingAs($this->hr)->postJson(route('admin.training.chunk'), [
            'upload_id' => 'fedcba0987654321',
            'index' => 0,
            'total' => 1,
            'extension' => 'wav',
            'chunk' => \Illuminate\Http\UploadedFile::fake()->createWithContent('part', '<?php echo "hello";'),
        ])->assertStatus(422);

        $this->assertEmpty(\Illuminate\Support\Facades\Storage::disk('public')->files('training'));
    }

    public function test_only_whoever_runs_training_may_send_pieces(): void
    {
        $this->actingAs($this->sales)->postJson(route('admin.training.chunk'), [
            'upload_id' => 'abcdef1234567890',
            'index' => 0,
            'total' => 1,
            'extension' => 'wav',
            'chunk' => \Illuminate\Http\UploadedFile::fake()->createWithContent('part', $this->wavBytes()),
        ])->assertForbidden();
    }

    public function test_training_can_only_be_pointed_at_a_file_that_came_in_that_way(): void
    {
        $this->actingAs($this->hr)->post(route('admin.training.store'), [
            'title' => 'Pointing somewhere else',
            'kind' => 'video',
            'uploaded_path' => '../../.env',
        ])->assertStatus(422);

        $this->assertSame(0, Training::count());
    }

    /** The smallest real wav there is: a moment of silence. */
    private function wavBytes(): string
    {
        $rate = 8000;
        $samples = str_repeat("\x00\x00", 800);

        return 'RIFF' . pack('V', 36 + strlen($samples)) . 'WAVEfmt '
            . pack('VvvVVvv', 16, 1, 1, $rate, $rate * 2, 2, 16)
            . 'data' . pack('V', strlen($samples)) . $samples;
    }

    public function test_somebody_cannot_comment_on_training_that_is_not_theirs_to_see(): void
    {
        $training = $this->putUp(['department_id' => $this->salesTeam->id]);

        $this->actingAs($this->tech)
            ->postJson(route('admin.training.comment', $training->id), ['body' => 'Sneaking in'])
            ->assertNotFound();

        $this->assertSame(0, $training->comments()->count());
    }
}
