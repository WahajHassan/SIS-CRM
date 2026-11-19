<?php

namespace App\Services;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Handing somebody their own data back.
 *
 * Everything in the CRM was easy to put in and impossible to get out: there was
 * an export for attendance and nothing at all for leads, deals or handovers.
 * That matters twice over — a customer evaluating the CRM wants to know they
 * can leave with their data, and the floor wants a spreadsheet to work in.
 *
 * Streamed rather than built in memory, because an export is run against the
 * whole table by definition and the whole table is the one thing that will not
 * fit in a PHP request. Rows are fetched in chunks and written as they come, so
 * the memory used is the same for a hundred rows as for a hundred thousand.
 *
 * The leading BOM is what makes Excel read UTF-8 properly; without it every
 * accented name arrives mangled, which is the kind of thing that makes people
 * distrust the whole file.
 */
class Spreadsheet
{
    /**
     * @param  iterable<array>  $headings  the first row
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  callable  $row  turns one model into one line
     */
    public static function stream(string $filename, array $headings, $query, callable $row): StreamedResponse
    {
        return response()->stream(
            function () use ($headings, $query, $row) {
                $out = fopen('php://output', 'w');

                // Excel needs to be told this is UTF-8, in the only way it listens to.
                fwrite($out, "\xEF\xBB\xBF");

                fputcsv($out, $headings);

                $query->chunk(500, function ($rows) use ($out, $row) {
                    foreach ($rows as $one) {
                        fputcsv($out, array_map(
                            // A cell beginning with =, +, - or @ is run as a formula
                            // when the file is opened. A phone number saved as
                            // "+1 555..." is enough to trigger it, and a malicious
                            // one is a real attack on whoever opens the sheet.
                            fn ($cell) => self::defuse($cell),
                            $row($one)
                        ));
                    }

                    flush();
                });

                fclose($out);
            },
            200,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                // Some proxies will otherwise hold the whole stream to buffer it.
                'X-Accel-Buffering' => 'no',
                'Cache-Control' => 'no-store, no-cache',
            ]
        );
    }

    /** Make a cell a value rather than an instruction. */
    private static function defuse($cell): string
    {
        $cell = (string) ($cell ?? '');

        return $cell !== '' && str_contains("=+-@\t\r", $cell[0])
            ? "'" . $cell
            : $cell;
    }

    /** A filename somebody can find again a month later. */
    public static function name(string $what, Request $request): string
    {
        $bits = [$what, now()->format('Y-m-d')];

        if ($request->filled('search')) {
            $bits[] = 'search-' . preg_replace('/[^A-Za-z0-9]+/', '-', (string) $request->input('search'));
        }

        if ($request->filled('status')) {
            $bits[] = strtolower((string) $request->input('status'));
        }

        return substr(implode('-', $bits), 0, 90) . '.csv';
    }
}
