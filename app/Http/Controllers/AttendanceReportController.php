<?php

namespace App\Http\Controllers;

use App\Exports\AttendanceSheetWriter;
use App\Models\Department;
use App\Models\ReportPreset;
use App\Models\Shift;
use App\Models\User;
use App\Services\AttendanceReportService;
use App\Services\PayrollService;
use Illuminate\Http\Request;

/**
 * The month's attendance as a file somebody can send on.
 *
 * Two reports, three formats, and a set of ticks that decides what actually
 * lands in the file — a sheet for payroll and a sheet for a meeting are not the
 * same sheet, and nobody should have to delete columns afterwards.
 */
class AttendanceReportController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeSection();

        $service = new AttendanceReportService();
        $month = PayrollService::month($request->input('month'));

        $departments = Department::active()->orderBy('name')->get();
        $shifts = Shift::active()->orderBy('start_time')->get();
        $staff = $service->staff([]);
        $months = collect(range(0, 11))->map(fn ($i) => now()->startOfMonth()->subMonths($i));

        // What the page shows is the same report that would be downloaded, so
        // nobody has to open a file to find out whether they picked the right month.
        $report = $service->overview($month, $this->filters($request), AttendanceReportService::normaliseSections(null));

        $presets = ReportPreset::where('user_id', auth()->id())->orderBy('name')->get();

        return view('admin.hr.reports', compact(
            'report', 'month', 'months', 'departments', 'shifts', 'staff', 'presets'
        ) + ['sections' => AttendanceReportService::SECTIONS]);
    }

    /** Build the file and hand it over. */
    public function download(Request $request)
    {
        $this->authorizeSection();

        $data = $request->validate([
            'report_type' => 'required|in:overview,single',
            'month' => 'required|date',
            'format' => 'required|in:xlsx,pdf,csv',
            'department_id' => 'nullable|exists:departments,id',
            'shift_id' => 'nullable|exists:shifts,id',
            'user_id' => 'nullable|exists:users,id',
            'sections' => 'nullable|array',
            'sections.*' => 'string',
        ]);

        $service = new AttendanceReportService();
        $month = PayrollService::month(substr($data['month'], 0, 7));
        $sections = AttendanceReportService::normaliseSections($data['sections'] ?? []);
        $filters = $this->filters($request);

        if ($data['report_type'] === 'single') {
            if (empty($data['user_id'])) {
                return back()->with('error', 'Choose whose month you want before downloading.');
            }

            $employee = User::findOrFail($data['user_id']);
            $report = $service->single($employee, $month, $sections);
            $name = $this->filename('Single', str_replace(' ', '-', $employee->name), $month, $data['format']);
            $pdfView = 'admin.hr.reports_pdf_single';
        } else {
            $report = $service->overview($month, $filters, $sections);
            $name = $this->filename('Overview', $report['filters']['department_slug'], $month, $data['format']);
            $pdfView = 'admin.hr.reports_pdf_overview';
        }

        if ($data['format'] === 'pdf') {
            // Wide months need landscape, or a 31-day sheet comes out unreadable.
            return \Barryvdh\DomPDF\Facade\Pdf::loadView($pdfView, ['report' => $report])
                ->setPaper('a4', $data['report_type'] === 'overview' ? 'landscape' : 'portrait')
                ->download($name);
        }

        $writer = new AttendanceSheetWriter($report);
        $book = $data['report_type'] === 'single' ? $writer->single() : $writer->overview();

        return $writer->download($book, $name, $data['format']);
    }

    /** Save this set of ticks and filters under a name, to use again next month. */
    public function storePreset(Request $request)
    {
        $this->authorizeSection();

        $data = $request->validate([
            'name' => 'required|string|max:60',
            'report_type' => 'required|in:overview,single',
            'format' => 'required|in:xlsx,pdf,csv',
            'department_id' => 'nullable|exists:departments,id',
            'shift_id' => 'nullable|exists:shifts,id',
            'user_id' => 'nullable|exists:users,id',
            'sections' => 'nullable|array',
        ]);

        ReportPreset::updateOrCreate(
            ['user_id' => auth()->id(), 'name' => $data['name']],
            [
                'report_type' => $data['report_type'],
                // The month is deliberately not saved: a preset is a shape, and
                // next month is the whole point of saving one.
                'filters' => [
                    'format' => $data['format'],
                    'department_id' => $data['department_id'] ?? null,
                    'shift_id' => $data['shift_id'] ?? null,
                    'user_id' => $data['user_id'] ?? null,
                ],
                'columns' => $data['sections'] ?? [],
            ]
        );

        return back()->with('success', 'Saved as “' . $data['name'] . '”.');
    }

    public function destroyPreset($id)
    {
        $this->authorizeSection();

        ReportPreset::where('user_id', auth()->id())->findOrFail($id)->delete();

        return back()->with('success', 'Preset removed.');
    }

    private function filters(Request $request): array
    {
        return array_filter([
            'department_id' => $request->input('department_id'),
            'shift_id' => $request->input('shift_id'),
            'user_id' => $request->input('user_id'),
        ]);
    }

    /** Attendance_{ReportType}_{Department}_{YYYY-MM}.{ext} */
    private function filename(string $type, ?string $scope, $month, string $format): string
    {
        $scope = preg_replace('/[^A-Za-z0-9\-]/', '', $scope ?: 'All') ?: 'All';

        return sprintf('Attendance_%s_%s_%s.%s', $type, $scope, $month->format('Y-m'), $format);
    }
}
