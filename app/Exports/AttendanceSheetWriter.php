<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Writes the attendance reports as a real spreadsheet.
 *
 * PhpSpreadsheet is used directly rather than through a wrapper package: the
 * wrapper's current release does not run on this framework version, and all
 * that is wanted here is a sheet with coloured cells.
 *
 * The day cells are filled with the colour of what they say, because a month of
 * letters is unreadable otherwise — a row of red is a problem you can see from
 * across the desk. A legend goes underneath so nobody has to be told what LE
 * means.
 */
class AttendanceSheetWriter
{
    private array $report;
    private array $sections;

    public function __construct(array $report)
    {
        $this->report = $report;
        $this->sections = $report['sections'] ?? [];
    }

    /** Everybody's month: a row per person, a column per day. */
    public function overview(): Spreadsheet
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle($this->report['month']->format('M Y'));

        $days = $this->report['days'];
        $totals = $this->totalHeadings();

        $sheet->setCellValue('A1', 'Attendance — ' . $this->report['month']->format('F Y'));
        $sheet->setCellValue('A2', sprintf(
            '%s · %s · generated %s',
            $this->report['filters']['department'] ?? 'All departments',
            $this->report['filters']['shift'] ?? 'All shifts',
            now()->format('d M Y, h:i A')
        ));

        $headerRow = 4;
        $columns = array_merge(['Employee', 'ID', 'Department', 'Shift'], array_map(fn ($d) => $d['number'], $days), $totals);
        $dayStart = 5;                                     // column E is the first day

        foreach ($columns as $i => $label) {
            $sheet->setCellValue([$i + 1, $headerRow], $label);
        }

        $lastColumn = count($columns);
        $row = $headerRow + 1;

        foreach ($this->report['rows'] as $line) {
            $sheet->setCellValue([1, $row], $line['user']->name);
            $sheet->setCellValue([2, $row], $line['id']);
            $sheet->setCellValue([3, $row], $line['department']);
            $sheet->setCellValue([4, $row], $line['shift']);

            foreach ($days as $i => $day) {
                $cell = $line['cells'][$day['date']] ?? ['code' => '', 'colour' => null];
                $column = $dayStart + $i;

                $sheet->setCellValue([$column, $row], $cell['code']);
                $sheet->getStyle([$column, $row, $column, $row])->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                if (!empty($cell['colour'])) {
                    $sheet->getStyle([$column, $row, $column, $row])->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB(ltrim($cell['colour'], '#'));
                }
            }

            foreach (array_values($this->totalValues($line['totals'])) as $i => $value) {
                $sheet->setCellValue([$dayStart + count($days) + $i, $row], $value);
            }

            $row++;
        }

        // Header and title styling.
        $sheet->mergeCells([1, 1, max(4, $lastColumn), 1]);
        $sheet->getStyle([1, 1, 1, 1])->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle([1, 2, 1, 2])->getFont()->setItalic(true)->setSize(9);

        $sheet->getStyle([1, $headerRow, $lastColumn, $headerRow])->getFont()->setBold(true);
        $sheet->getStyle([1, $headerRow, $lastColumn, $headerRow])->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
        $sheet->getStyle([1, $headerRow, $lastColumn, $headerRow])->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->freezePane('E' . ($headerRow + 1));

        foreach ([1 => 26, 2 => 14, 3 => 16, 4 => 15] as $column => $width) {
            $sheet->getColumnDimensionByColumn($column)->setWidth($width);
        }
        for ($i = 0; $i < count($days); $i++) {
            $sheet->getColumnDimensionByColumn($dayStart + $i)->setWidth(4.5);
        }
        for ($i = 0; $i < count($totals); $i++) {
            $sheet->getColumnDimensionByColumn($dayStart + count($days) + $i)->setWidth(13);
        }

        $this->writeLegend($sheet, $row + 2);

        return $book;
    }

    /** One person's month: a line per day, then the totals. */
    public function single(): Spreadsheet
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $employee = $this->report['employee'];
        $sheet->setTitle(substr(preg_replace('/[^A-Za-z0-9 ]/', '', $employee->name), 0, 20) ?: 'Attendance');

        $sheet->setCellValue('A1', 'Attendance — ' . $employee->name);
        $facts = [
            ['Month', $this->report['month']->format('F Y')],
            ['Department', $this->report['department']],
            ['Shift', $this->report['shift']],
            ['Designation', $employee->employeeProfile?->designation ?: '—'],
            ['Generated', now()->format('d M Y, h:i A')],
        ];

        $row = 2;
        foreach ($facts as $fact) {
            $sheet->setCellValue([1, $row], $fact[0]);
            $sheet->setCellValue([2, $row], $fact[1]);
            $sheet->getStyle([1, $row, 1, $row])->getFont()->setBold(true);
            $row++;
        }

        $row++;
        $headerRow = $row;
        $headings = $this->singleHeadings();

        foreach ($headings as $i => $label) {
            $sheet->setCellValue([$i + 1, $headerRow], $label);
        }

        $statusColumn = array_search('Status', $headings, true) + 1;
        $row++;

        foreach ($this->report['rows'] as $day) {
            if (in_array($day['status_key'], ['weekend', 'holiday'], true) && !($this->sections['weekends'] ?? true)) {
                continue;
            }

            foreach (array_values($this->singleValues($day)) as $i => $value) {
                $sheet->setCellValue([$i + 1, $row], $value);
            }

            if (!empty($day['colour'])) {
                $sheet->getStyle([$statusColumn, $row, $statusColumn, $row])->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB(ltrim($day['colour'], '#'));
            }

            $row++;
        }

        $sheet->getStyle([1, 1, 1, 1])->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle([1, $headerRow, count($headings), $headerRow])->getFont()->setBold(true);
        $sheet->getStyle([1, $headerRow, count($headings), $headerRow])->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');

        for ($i = 1; $i <= count($headings); $i++) {
            $sheet->getColumnDimensionByColumn($i)->setWidth($headings[$i - 1] === 'Remarks' ? 30 : 14);
        }

        if ($this->sections['totals'] ?? true) {
            $row += 2;
            $sheet->setCellValue([1, $row], 'Summary');
            $sheet->getStyle([1, $row, 1, $row])->getFont()->setBold(true);
            $row++;

            foreach ($this->summaryLines() as $line) {
                $sheet->setCellValue([1, $row], $line[0]);
                $sheet->setCellValue([2, $row], $line[1]);
                $row++;
            }
        }

        return $book;
    }

    /** Stream a book as a download, in the format asked for. */
    public function download(Spreadsheet $book, string $filename, string $format): StreamedResponse
    {
        $writer = $format === 'csv' ? new CsvWriter($book) : new XlsxWriter($book);

        $contentType = $format === 'csv'
            ? 'text/csv'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'max-age=0, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    // ---- which columns were asked for --------------------------------------

    private function totalHeadings(): array
    {
        $headings = ['Present'];

        if ($this->sections['late'] ?? true) { $headings[] = 'Late'; $headings[] = 'Late mins'; }
        if ($this->sections['early_off'] ?? true) { $headings[] = 'Early Off'; $headings[] = 'Early mins'; }
        if ($this->sections['absents'] ?? true) { $headings[] = 'Absent'; }
        if ($this->sections['leaves'] ?? true) { $headings[] = 'Leave days'; }
        if ($this->sections['late_offs'] ?? true) { $headings[] = 'Late-deducted offs'; }
        if ($this->sections['weekends'] ?? true) { $headings[] = 'Offs'; }
        if ($this->sections['working_hours'] ?? true) { $headings[] = 'Hours worked'; }

        return $headings;
    }

    private function totalValues(array $totals): array
    {
        $values = [$totals['present'] + $totals['half_day']];

        if ($this->sections['late'] ?? true) { $values[] = $totals['late']; $values[] = $totals['late_minutes']; }
        if ($this->sections['early_off'] ?? true) { $values[] = $totals['early_off']; $values[] = $totals['early_minutes']; }
        if ($this->sections['absents'] ?? true) { $values[] = $totals['absent']; }
        if ($this->sections['leaves'] ?? true) { $values[] = $totals['leave']; }
        if ($this->sections['late_offs'] ?? true) { $values[] = $totals['late_offs']; }
        if ($this->sections['weekends'] ?? true) { $values[] = $totals['off']; }
        if ($this->sections['working_hours'] ?? true) { $values[] = round($totals['worked_minutes'] / 60, 1); }

        return $values;
    }

    private function singleHeadings(): array
    {
        $headings = ['Date', 'Day', 'Shift'];

        if ($this->sections['check_times'] ?? true) { $headings[] = 'Check in'; $headings[] = 'Check out'; }
        if ($this->sections['working_hours'] ?? true) { $headings[] = 'Worked'; }

        $headings[] = 'Status';

        if ($this->sections['late'] ?? true) { $headings[] = 'Late mins'; }
        if ($this->sections['early_off'] ?? true) { $headings[] = 'Early off mins'; }
        if ($this->sections['leaves'] ?? true) { $headings[] = 'Leave type'; }
        if ($this->sections['remarks'] ?? true) { $headings[] = 'Remarks'; }

        return $headings;
    }

    private function singleValues(array $day): array
    {
        $values = [$day['date'], $day['day'], $day['shift']];

        if ($this->sections['check_times'] ?? true) { $values[] = $day['in'] ?: '—'; $values[] = $day['out'] ?: '—'; }
        if ($this->sections['working_hours'] ?? true) { $values[] = $day['worked']; }

        $values[] = $day['status'];

        if ($this->sections['late'] ?? true) { $values[] = $day['late_minutes'] ?: ''; }
        if ($this->sections['early_off'] ?? true) { $values[] = $day['early_minutes'] ?: ''; }
        if ($this->sections['leaves'] ?? true) { $values[] = $day['leave_type']; }
        if ($this->sections['remarks'] ?? true) { $values[] = $day['remarks']; }

        return $values;
    }

    private function summaryLines(): array
    {
        $summary = $this->report['summary'];
        $lines = [
            ['Days present', $summary['present']],
            ['Half days', $summary['half_day']],
        ];

        if ($this->sections['absents'] ?? true) { $lines[] = ['Absent', $summary['absent']]; }
        if ($this->sections['late'] ?? true) { $lines[] = ['Lates', $summary['late'] . ' (' . $summary['late_minutes'] . ' mins)']; }
        if ($this->sections['early_off'] ?? true) { $lines[] = ['Early offs', $summary['early_off'] . ' (' . $summary['early_minutes'] . ' mins)']; }
        if ($this->sections['late_offs'] ?? true) { $lines[] = ['Late-deducted offs', $summary['late_offs']]; }
        if ($this->sections['working_hours'] ?? true) { $lines[] = ['Hours worked', round($summary['worked_minutes'] / 60, 1)]; }

        if ($this->sections['leaves'] ?? true) {
            foreach ($summary['leave_by_type'] as $name => $days) {
                $lines[] = ['Leave — ' . $name, $days];
            }
        }

        return $lines;
    }

    private function writeLegend($sheet, int $startRow): void
    {
        $sheet->setCellValue([1, $startRow], 'Legend');
        $sheet->getStyle([1, $startRow, 1, $startRow])->getFont()->setBold(true);

        $row = $startRow + 1;

        foreach (array_chunk($this->report['legend'], 6) as $chunk) {
            $column = 1;

            foreach ($chunk as $entry) {
                $sheet->setCellValue([$column, $row], $entry['code']);
                $sheet->setCellValue([$column + 1, $row], $entry['label']);
                $sheet->getStyle([$column, $row, $column, $row])->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB(ltrim($entry['colour'], '#'));
                $sheet->getStyle([$column, $row, $column, $row])->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $column += 2;
            }

            $row++;
        }
    }
}
