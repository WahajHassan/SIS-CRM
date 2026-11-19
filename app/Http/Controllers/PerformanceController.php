<?php

namespace App\Http\Controllers;

use App\Models\JobDescription;
use App\Models\KpiTarget;
use App\Models\User;
use App\Services\PayrollService;
use App\Services\PerformanceService;
use Illuminate\Http\Request;

/**
 * What each person is here to do, and how it is going.
 *
 * Two halves of the same question: a job description says what the role is
 * (which the tech team mostly needs), and a monthly target says what the number
 * is (which the sales team mostly needs). Everyone can have both.
 */
class PerformanceController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeSection();

        $month = PayrollService::month($request->input('month'));

        $employees = User::with(['employeeProfile', 'jobDescription'])
            ->where('role', '!=', 'superadmin')
            ->orderBy('name')
            ->get();

        // Targets measured from the CRM re-read their figure here, so opening the
        // page is all it takes for the month's scores to be current.
        $targets = (new PerformanceService())
            ->refresh(KpiTarget::with('user')->where('period', $month->toDateString())->get())
            ->groupBy('user_id');

        $rows = $employees->map(function (User $user) use ($targets) {
            $kpis = $targets[$user->id] ?? collect();
            $weighted = $kpis->filter(fn ($k) => $k->target_value > 0);

            return [
                'user' => $user,
                'jd' => $user->jobDescription,
                'kpis' => $kpis,
                // The average of how far along each target is — one honest number
                // per person rather than a column of percentages to read across.
                'progress' => $weighted->count()
                    ? (int) round($weighted->avg(fn ($k) => $k->progress()))
                    : null,
            ];
        });

        $months = collect(range(0, 11))->map(fn ($i) => now()->startOfMonth()->subMonths($i));

        $totals = [
            'with_jd' => $rows->filter(fn ($r) => $r['jd'] && filled($r['jd']->responsibilities))->count(),
            'with_kpi' => $rows->filter(fn ($r) => $r['kpis']->count() > 0)->count(),
            'on_track' => $rows->filter(fn ($r) => $r['progress'] !== null && $r['progress'] >= 80)->count(),
            'behind' => $rows->filter(fn ($r) => $r['progress'] !== null && $r['progress'] < 50)->count(),
        ];

        $sources = PerformanceService::sources();

        return view('admin.hr.performance', compact('rows', 'month', 'months', 'totals', 'sources'));
    }

    /** Save a job description. One per person, created on first save. */
    public function saveJd(Request $request, $userId)
    {
        $this->authorizeSection();

        $employee = User::findOrFail($userId);

        $data = $request->validate([
            'title' => 'nullable|string|max:150',
            'reports_to' => 'nullable|string|max:150',
            'purpose' => 'nullable|string|max:2000',
            'responsibilities' => 'nullable|string|max:5000',
            'requirements' => 'nullable|string|max:5000',
            'tools' => 'nullable|string|max:1000',
        ]);

        JobDescription::updateOrCreate(
            ['user_id' => $employee->id],
            $data + ['updated_by' => auth()->id()]
        );

        return back()->with('success', $employee->display_name . "'s job description saved.");
    }

    /** Add a target for a month. */
    public function storeKpi(Request $request, $userId)
    {
        $this->authorizeSection();

        $employee = User::findOrFail($userId);

        $data = $request->validate([
            'period' => 'required|date',
            'metric' => 'required|string|max:120',
            'unit' => 'required|in:number,amount,percent',
            'source' => 'nullable|string|in:' . implode(',', array_keys(PerformanceService::sources())),
            'target_value' => 'required|numeric|min:0',
            'achieved_value' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $month = PayrollService::month(substr($data['period'], 0, 7));
        $source = $data['source'] ?? 'manual';
        $service = new PerformanceService();

        KpiTarget::create([
            'user_id' => $employee->id,
            'period' => $month->toDateString(),
            'metric' => $data['metric'],
            // A system-measured figure carries its source's own unit, so revenue
            // is never left reading as a plain count.
            'unit' => PerformanceService::isAuto($source)
                ? PerformanceService::sources()[$source]['unit']
                : $data['unit'],
            'source' => $source,
            'target_value' => $data['target_value'],
            'achieved_value' => PerformanceService::isAuto($source)
                ? $service->value($employee, $source, $month)
                : ($data['achieved_value'] ?? 0),
            'notes' => $data['notes'] ?? null,
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'Target added for ' . $employee->display_name . '.');
    }

    /** Update what has actually been achieved against a target. */
    public function updateKpi(Request $request, $id)
    {
        $this->authorizeSection();

        $data = $request->validate([
            'metric' => 'nullable|string|max:120',
            'target_value' => 'nullable|numeric|min:0',
            'achieved_value' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $kpi = KpiTarget::findOrFail($id);

        // A system-measured target owns its achieved figure; only the goal is
        // editable, otherwise the next page load would overwrite the typing.
        if ($kpi->isAuto()) {
            unset($data['achieved_value']);
        }

        $kpi->update(array_filter($data, fn ($v) => $v !== null) + ['updated_by' => auth()->id()]);

        return back()->with('success', 'Target updated.');
    }

    public function destroyKpi($id)
    {
        $this->authorizeSection();

        KpiTarget::findOrFail($id)->delete();

        return back()->with('success', 'Target removed.');
    }

    /** What the signed-in person is measured on, without HR access. */
    public function mine(Request $request)
    {
        $user = auth()->user();
        $month = PayrollService::month($request->input('month'));

        $kpis = (new PerformanceService())->refresh(
            KpiTarget::with('user')
                ->where('user_id', $user->id)
                ->where('period', $month->toDateString())
                ->orderBy('metric')
                ->get()
        );

        $jd = $user->jobDescription;
        $months = collect(range(0, 11))->map(fn ($i) => now()->startOfMonth()->subMonths($i));

        return view('admin.hr.my_performance', compact('kpis', 'jd', 'month', 'months'));
    }
}
