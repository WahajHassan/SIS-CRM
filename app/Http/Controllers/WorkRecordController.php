<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use App\Services\WorkRecordService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * The team's work record: who gave how many transfers, who took them, what was
 * won, and who was in to do it — day by day for whatever period is asked for.
 */
class WorkRecordController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeSection();

        [$from, $to] = $this->period($request);

        $service = new WorkRecordService();
        $filters = array_filter([
            'department_id' => $request->input('department_id'),
            'user_id' => $request->input('user_id'),
        ]);

        $report = $service->report($from, $to, $filters);
        $departments = Department::active()->orderBy('name')->get();
        $metrics = WorkRecordService::METRICS;

        // One person asked for by name gets their own day-by-day strip as well.
        $employee = $request->input('user_id') ? User::find($request->input('user_id')) : null;

        return view('admin.hr.work_record', compact(
            'report', 'from', 'to', 'departments', 'metrics', 'employee', 'filters'
        ));
    }

    /** The same figures as a spreadsheet, for whoever asked for them. */
    public function export(Request $request)
    {
        $this->authorizeSection();

        [$from, $to] = $this->period($request);

        $report = (new WorkRecordService())->report($from, $to, array_filter([
            'department_id' => $request->input('department_id'),
            'user_id' => $request->input('user_id'),
        ]));

        $columns = array_merge(['Employee', 'Department', 'Role'],
            array_map(fn ($m) => $m['label'], WorkRecordService::METRICS));

        $filename = sprintf('Work_Record_%s_to_%s.csv', $from->format('Y-m-d'), $to->format('Y-m-d'));

        return response()->stream(function () use ($report, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($report['rows'] as $row) {
                $line = [$row['user']->name, $row['department'], strtoupper($row['role'])];

                foreach (array_keys(WorkRecordService::METRICS) as $metric) {
                    $line[] = $row[$metric];
                }

                fputcsv($file, $line);
            }

            fclose($file);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename={$filename}",
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * The period being looked at: this month unless asked otherwise, and never
     * backwards.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function period(Request $request): array
    {
        try {
            $from = $request->input('from') ? Carbon::parse($request->input('from')) : Carbon::now()->startOfMonth();
            $to = $request->input('to') ? Carbon::parse($request->input('to')) : Carbon::now()->endOfMonth();
        } catch (\Exception $e) {
            $from = Carbon::now()->startOfMonth();
            $to = Carbon::now()->endOfMonth();
        }

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        // A year at a time is plenty; anything longer is a report, not a page.
        if ($from->diffInDays($to) > 366) {
            $from = $to->copy()->subYear();
        }

        return [$from, $to];
    }
}
