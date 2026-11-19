<?php

namespace App\Http\Controllers;

use App\Models\SystemLog;
use App\Models\User;
use App\Services\IncentiveService;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * A payslip somebody can keep.
 *
 * The CRM has worked out salary for a long time — days credited, what was lost
 * to absence and lateness, the incentive an agent earned — and all of it lived
 * inside a table on the payroll screen. Nobody could be handed anything. A
 * payslip is what people ask their employer for when they rent a flat or open
 * an account, and "look at this spreadsheet" is not an answer.
 *
 * It has no figures of its own. Every number is read back out of PayrollService
 * and IncentiveService — the same two the payroll screen and the wallet read —
 * because a payslip that quietly disagreed with payroll would be worse than no
 * payslip at all. Somebody would believe it.
 *
 * It renders as a page meant for printing rather than a PDF, so it needs no
 * library on a shared host and the browser's own Save as PDF produces the file.
 */
class PayslipController extends Controller
{
    public function show(Request $request, int $user, PayrollService $payroll, IncentiveService $incentives)
    {
        $me = auth()->user();

        // withTrashed: somebody who has left still needs last month's payslip,
        // and refusing it is exactly when they need it most.
        $person = User::withTrashed()->findOrFail($user);

        abort_unless($this->maySee($me, $person), 403, 'That is not yours to look at.');

        $month = PayrollService::month($request->query('month'));

        $rows = $payroll->summary(collect([$person]), $month);
        $pay = $rows->firstWhere('user.id', $person->id) ?: $rows->first();

        abort_if(!$pay, 404, 'There is no payroll for that month.');

        // Incentives belong to agents and are counted off their own sales.
        $bonus = $person->role === 'agent' ? $incentives->summary($person, $month) : null;

        // Looking at somebody else's pay is worth a line in the log; looking at
        // your own is not.
        if ($me->id !== $person->id) {
            SystemLog::log(
                'Viewed Payslip',
                "Opened {$person->display_name}'s payslip for " . $month->format('F Y')
            );
        }

        return view('admin.payslip', [
            'person' => $person,
            'month' => $month,
            'pay' => $pay,
            'bonus' => $bonus,
            'company' => $person->company,
            'issuedBy' => $me,
        ]);
    }

    /**
     * Your own always; anybody else's only with the HR or payroll screens.
     *
     * Pay is the one thing in the CRM that a colleague has no business seeing
     * just because they are senior, so this is the section that owns it rather
     * than the role.
     */
    private function maySee(User $me, User $person): bool
    {
        if ($me->id === $person->id) {
            return true;
        }

        if ($me->isSuperAdmin()) {
            return true;
        }

        return $me->canAccess('hr_employees') || $me->canAccess('attendance');
    }
}
