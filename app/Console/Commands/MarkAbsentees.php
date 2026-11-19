<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\User;
use App\Services\AttendanceCalculator;
use App\Services\LateOffService;
use App\Services\ShiftResolver;
use App\Services\TenantManager;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Marks the people who never turned up.
 *
 * It runs after a shift has finished, so a day is only ever judged once it is
 * over — somebody who has not clocked in at 8 PM for a shift that runs to 4 AM
 * is late, not absent. Leave, holidays and weekly offs are all recorded as what
 * they are rather than as an absence.
 *
 * Runs for every company, and can be pointed at a particular date to fill in a
 * day the scheduler missed.
 */
class MarkAbsentees extends Command
{
    protected $signature = 'attendance:mark-absent
                            {--date= : The shift date to judge, instead of whatever has just finished}
                            {--company= : Only this company}';

    protected $description = 'Mark absent anyone with no check-in and no leave on a finished working day';

    public function handle(): int
    {
        $companies = Company::query()
            ->when($this->option('company'), fn ($q) => $q->where('id', $this->option('company')))
            ->get();

        $marked = 0;

        foreach ($companies as $company) {
            TenantManager::setTenant($company);

            $calculator = new AttendanceCalculator();

            if (!$calculator->rules()->auto_absent_enabled) {
                $this->line("{$company->name}: absent marking is switched off.");
                continue;
            }

            $dates = $this->datesToJudge($calculator->resolver());

            if (!$dates) {
                $this->line("{$company->name}: no shift has finished yet.");
                continue;
            }

            $staff = User::where('role', '!=', 'superadmin')->get();

            foreach ($dates as $date) {
                foreach ($staff as $employee) {
                    $marked += $this->judge($employee, $date, $calculator) ? 1 : 0;
                }
            }
        }

        $this->info("Marked {$marked} " . str('absence')->plural($marked) . '.');

        return self::SUCCESS;
    }

    /**
     * The shift dates to look at: the one asked for, or every date whose shift
     * has already ended.
     *
     * @return array<int, string>
     */
    private function datesToJudge(ShiftResolver $resolver): array
    {
        if ($date = $this->option('date')) {
            return [Carbon::parse($date)->toDateString()];
        }

        return collect($resolver->finishedShifts())
            ->pluck('date')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * One person, one date. Answers whether a new absence was recorded.
     */
    private function judge(User $employee, string $date, AttendanceCalculator $calculator): bool
    {
        $attendance = Attendance::where('user_id', $employee->id)->whereDate('date', $date)->first();

        if ($attendance && $attendance->clock_in) {
            return false;                                  // they were here; nothing to decide
        }

        $status = $calculator->emptyDayStatus($employee, $date);

        // Only a working day with no leave is an absence. The rest are recorded
        // as what they are, so the sheet reads honestly, but they cost nothing.
        if ($status !== Attendance::ABSENT) {
            return false;
        }

        if ($attendance) {
            if ($attendance->day_status === Attendance::ABSENT) {
                return false;                              // already marked
            }

            $attendance->update(['day_status' => Attendance::ABSENT]);

            return true;
        }

        Attendance::create([
            'user_id' => $employee->id,
            'date' => $date,
            'shift_id' => $employee->shiftOn($date)?->id,
            'status' => 'clocked_out',                     // the day is over, unworked
            'day_status' => Attendance::ABSENT,
            'worked_minutes' => 0,
            'source' => 'system',
            'remarks' => 'No check-in; marked by the daily sweep.',
        ]);

        // An absence changes nothing about lates, but a month that has just been
        // completed is worth re-checking cheaply.
        (new LateOffService())->sync($employee, Carbon::parse($date));

        return true;
    }
}
