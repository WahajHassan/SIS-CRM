<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Attendance;
use App\Models\AttendanceBreak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;
use Tests\TestCase;

class AttendanceAutoCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_attendance_is_auto_checked_out_after_9_hours(): void
    {
        $agent = User::create([
            'name' => 'Agent Melvin',
            'email' => 'melvin@test.com',
            'password' => bcrypt('password'),
            'role' => 'agent',
        ]);

        $clockInTime = Carbon::now()->subHours(10);

        // Create an active attendance from 10 hours ago
        $attendance = Attendance::create([
            'user_id' => $agent->id,
            'date' => $clockInTime->toDateString(),
            'clock_in' => $clockInTime,
            'status' => 'clocked_in',
        ]);

        // Trigger auto-checkout
        Attendance::autoCheckoutExpired();

        $attendance->refresh();

        $this->assertEquals('clocked_out', $attendance->status);
        $this->assertNotNull($attendance->clock_out);
        
        // Clock out must be exactly 9 hours after clock in
        $expectedClockOut = $clockInTime->copy()->addHours(9);
        $this->assertEquals($expectedClockOut->toDateTimeString(), $attendance->clock_out->toDateTimeString());
    }

    public function test_active_attendance_remains_clocked_in_under_9_hours(): void
    {
        $agent = User::create([
            'name' => 'Agent Melvin',
            'email' => 'melvin@test.com',
            'password' => bcrypt('password'),
            'role' => 'agent',
        ]);

        $clockInTime = Carbon::now()->subHours(5);

        $attendance = Attendance::create([
            'user_id' => $agent->id,
            'date' => $clockInTime->toDateString(),
            'clock_in' => $clockInTime,
            'status' => 'clocked_in',
        ]);

        Attendance::autoCheckoutExpired();

        $attendance->refresh();

        $this->assertEquals('clocked_in', $attendance->status);
        $this->assertNull($attendance->clock_out);
    }

    public function test_open_break_is_closed_during_auto_checkout(): void
    {
        $agent = User::create([
            'name' => 'Agent Melvin',
            'email' => 'melvin@test.com',
            'password' => bcrypt('password'),
            'role' => 'agent',
        ]);

        $clockInTime = Carbon::now()->subHours(11);
        $breakStartTime = Carbon::now()->subHours(6);

        $attendance = Attendance::create([
            'user_id' => $agent->id,
            'date' => $clockInTime->toDateString(),
            'clock_in' => $clockInTime,
            'status' => 'on_break',
        ]);

        $break = AttendanceBreak::create([
            'attendance_id' => $attendance->id,
            'break_start' => $breakStartTime,
        ]);

        Attendance::autoCheckoutExpired();

        $attendance->refresh();
        $break->refresh();

        $this->assertEquals('clocked_out', $attendance->status);
        
        $expectedClockOut = $clockInTime->copy()->addHours(9);
        $this->assertEquals($expectedClockOut->toDateTimeString(), $attendance->clock_out->toDateTimeString());
        
        // Break end must be set to the clock out time
        $this->assertNotNull($break->break_end);
        $this->assertEquals($expectedClockOut->toDateTimeString(), $break->break_end->toDateTimeString());
    }

    public function test_auto_checkout_triggered_via_today_attendance_check(): void
    {
        $agent = User::create([
            'name' => 'Agent Melvin',
            'email' => 'melvin@test.com',
            'password' => bcrypt('password'),
            'role' => 'agent',
        ]);

        $clockInTime = Carbon::now()->subHours(12);

        $attendance = Attendance::create([
            'user_id' => $agent->id,
            'date' => $clockInTime->toDateString(),
            'clock_in' => $clockInTime,
            'status' => 'clocked_in',
        ]);

        // Accessing todayAttendance() should run auto-checkout in the background
        $agent->todayAttendance();

        $attendance->refresh();
        $this->assertEquals('clocked_out', $attendance->status);
    }

    public function test_artisan_command_triggers_auto_checkout(): void
    {
        $agent = User::create([
            'name' => 'Agent Melvin',
            'email' => 'melvin@test.com',
            'password' => bcrypt('password'),
            'role' => 'agent',
        ]);

        $clockInTime = Carbon::now()->subHours(10);

        $attendance = Attendance::create([
            'user_id' => $agent->id,
            'date' => $clockInTime->toDateString(),
            'clock_in' => $clockInTime,
            'status' => 'clocked_in',
        ]);

        // Run Artisan command
        $this->artisan('attendance:auto-checkout')
            ->expectsOutput('Expired attendances checked out successfully.')
            ->assertExitCode(0);

        $attendance->refresh();
        $this->assertEquals('clocked_out', $attendance->status);
    }
}
