<?php

namespace App\Services;

use App\Models\HrSetting;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;

/**
 * Which shift a moment belongs to, and which date that shift counts as.
 *
 * This is the piece the whole night-shift problem turns on. A shift that starts
 * at 7 PM on Friday and ends at 4 AM on Saturday is *Friday's* attendance: the
 * person worked one night, and it must show up as one day, on the day it began.
 * Clocking in a little early belongs to the same shift, so arriving at 6:40 PM
 * for a 7 PM start is not yesterday's shift and not a day of its own.
 *
 * Everything here answers in terms of a shift date, and every other part of the
 * HR panel takes that date as given rather than working it out again.
 */
class ShiftResolver
{
    private HrSetting $rules;

    public function __construct(?HrSetting $rules = null)
    {
        $this->rules = $rules ?: HrSetting::current();
    }

    /**
     * The shift and shift-date a moment falls in for this employee.
     *
     * Candidate dates are yesterday, today and tomorrow, because a single moment
     * can belong to any of them: 1 AM on Saturday is Friday's night shift, and
     * 11:30 PM on Friday is already Saturday's midnight shift when the early
     * window is two hours.
     *
     * @return array{shift: ?Shift, date: string}
     */
    public function resolve(User $employee, ?Carbon $at = null): array
    {
        $at = ($at ? $at->copy() : Carbon::now())->seconds(0);
        $early = (int) $this->rules->early_checkin_minutes;

        $candidates = [];

        foreach ([-1, 0, 1] as $offset) {
            $date = $at->copy()->startOfDay()->addDays($offset)->toDateString();
            $shift = $employee->shiftOn($date);

            if (!$shift) {
                continue;
            }

            $opens = $shift->startOn($date)->subMinutes($early);
            $closes = $shift->endOn($date);

            if ($at->betweenIncluded($opens, $closes)) {
                $candidates[] = [
                    'shift' => $shift,
                    'date' => $date,
                    // How far into the shift this is; the nearest start wins when
                    // two shifts overlap at the edges.
                    'distance' => abs($at->diffInMinutes($shift->startOn($date), false)),
                ];
            }
        }

        if ($candidates) {
            usort($candidates, fn ($a, $b) => $a['distance'] <=> $b['distance']);

            return ['shift' => $candidates[0]['shift'], 'date' => $candidates[0]['date']];
        }

        // Outside every shift window — somebody clocking in on a day off, or
        // hours late. Their own shift still decides which date that is.
        $shift = $employee->shiftOn($at->toDateString());

        return [
            'shift' => $shift,
            'date' => $shift ? $this->dateForLooseMoment($shift, $at) : $at->toDateString(),
        ];
    }

    /** Just the date, for callers that do not care which shift it was. */
    public function dateFor(User $employee, ?Carbon $at = null): string
    {
        return $this->resolve($employee, $at)['date'];
    }

    /**
     * A moment that falls outside the shift window still has to land somewhere.
     *
     * For a shift that runs through midnight, anything before it ends belongs to
     * the night that started yesterday; otherwise the moment's own date is right.
     */
    private function dateForLooseMoment(Shift $shift, Carbon $at): string
    {
        if (!$shift->crosses_midnight) {
            return $at->toDateString();
        }

        $endToday = $shift->endOn($at->copy()->subDay()->toDateString());

        return $at->lessThanOrEqualTo($endToday)
            ? $at->copy()->subDay()->toDateString()
            : $at->toDateString();
    }

    /**
     * Shifts that have finished by a given moment, with the date they covered —
     * what the absent sweep needs in order to judge only days that are over.
     *
     * @return array<int, array{shift: Shift, date: string}>
     */
    public function finishedShifts(?Carbon $at = null): array
    {
        $at = $at ? $at->copy() : Carbon::now();
        $finished = [];

        foreach (Shift::active()->get() as $shift) {
            foreach ([-1, 0] as $offset) {
                $date = $at->copy()->startOfDay()->addDays($offset)->toDateString();

                if ($shift->endOn($date)->lessThanOrEqualTo($at)) {
                    $finished[] = ['shift' => $shift, 'date' => $date];
                }
            }
        }

        return $finished;
    }

    public function rules(): HrSetting
    {
        return $this->rules;
    }
}
