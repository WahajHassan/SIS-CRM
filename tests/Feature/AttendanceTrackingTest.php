<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Attendance;
use App\Models\AttendanceBreak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;
use Tests\TestCase;

class AttendanceTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_can_clock_in_out_and_track_breaks(): void
    {
        $agent = User::create([
            'name' => 'Agent Melvin',
            'email' => 'melvin@test.com',
            'password' => bcrypt('password'),
            'role' => 'agent',
        ]);

        // 1. Clock In
        $responseClockIn = $this->actingAs($agent)->post(route('admin.attendance.clock-in'));
        $responseClockIn->assertStatus(302);
        
        $attendance = Attendance::where('user_id', $agent->id)->first();
        $this->assertNotNull($attendance);
        $this->assertEquals('clocked_in', $attendance->status);
        $this->assertNotNull($attendance->clock_in);
        $this->assertNull($attendance->clock_out);

        // 2. Start Break. Breaks run in a fixed sequence and the type has to be named:
        // the first one may be short_1 or half_1, and short breaks additionally need an
        // approved request, so half_1 is the one an agent can take unaided.
        $responseStartBreak = $this->actingAs($agent)->post(route('admin.attendance.start-break'), [
            'type' => 'half_1',
        ]);
        $responseStartBreak->assertStatus(302);

        $attendance->refresh();
        $this->assertEquals('on_break', $attendance->status);
        
        $activeBreak = AttendanceBreak::where('attendance_id', $attendance->id)->first();
        $this->assertNotNull($activeBreak);
        $this->assertNotNull($activeBreak->break_start);
        $this->assertNull($activeBreak->break_end);

        // 3. End Break
        $responseEndBreak = $this->actingAs($agent)->post(route('admin.attendance.end-break'));
        $responseEndBreak->assertStatus(302);

        $attendance->refresh();
        $this->assertEquals('clocked_in', $attendance->status);

        $activeBreak->refresh();
        $this->assertNotNull($activeBreak->break_end);

        // 4. Clock Out
        $responseClockOut = $this->actingAs($agent)->post(route('admin.attendance.clock-out'));
        $responseClockOut->assertStatus(302);

        $attendance->refresh();
        $this->assertEquals('clocked_out', $attendance->status);
        $this->assertNotNull($attendance->clock_out);
    }

    public function test_admin_can_view_attendance_but_restricted_users_cannot(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $agent = User::create([
            'name' => 'Agent User',
            'email' => 'agent@test.com',
            'password' => bcrypt('password'),
            'role' => 'agent',
        ]);

        // Admin can access
        $responseAdmin = $this->actingAs($admin)->get(route('admin.attendance'));
        $responseAdmin->assertStatus(200);

        // Agent gets 403
        $responseAgent = $this->actingAs($agent)->get(route('admin.attendance'));
        $responseAgent->assertStatus(403);
    }

    public function test_admin_can_export_csv_and_print_preview(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $agent = User::create([
            'name' => 'Agent Melvin',
            'email' => 'melvin@test.com',
            'password' => bcrypt('password'),
            'role' => 'agent',
        ]);

        // Create attendance log
        Attendance::create([
            'user_id' => $agent->id,
            'date' => Carbon::today()->toDateString(),
            'clock_in' => Carbon::now(),
            'status' => 'clocked_in',
        ]);

        // 1. Export CSV
        $responseCsv = $this->actingAs($admin)->get(route('admin.attendance.export', [
            'user_id' => $agent->id,
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->toDateString(),
        ]));
        $responseCsv->assertStatus(200);
        $responseCsv->assertHeader('Content-Disposition', 'attachment; filename=attendance_report_' . now()->format('Y-m-d') . '.csv');

        // 2. Print Preview
        $responsePrint = $this->actingAs($admin)->get(route('admin.attendance.print', [
            'user_id' => $agent->id,
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->toDateString(),
        ]));
        $responsePrint->assertStatus(200);
        $responsePrint->assertSee('Agent Melvin');
        $responsePrint->assertSee('PDF Print Preview Mode');
    }
}
