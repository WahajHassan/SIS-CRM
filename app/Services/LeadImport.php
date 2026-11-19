<?php

namespace App\Services;

use App\Models\BusinessCategory;
use App\Models\City;
use App\Models\Lead;
use App\Models\UsaState;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Bringing a list of businesses in from a spreadsheet.
 *
 * A new customer arrives with their leads in a file and no way to get them into
 * the CRM but typing. That is the difference between trying it on Monday and
 * trying it next month.
 *
 * Three decisions worth stating:
 *
 *   - The file is read twice. Everything is checked before anything is written,
 *     so a bad row at line 400 does not leave 399 half-imported leads behind
 *     and no clear way to know which.
 *   - Trade, state and city are matched to the lists the company already keeps,
 *     never created. A typo would otherwise quietly add "Arizonaa" to the state
 *     list, and the reports built on those lists would never recover.
 *   - A business already on file is skipped, not added again. Duplicate leads
 *     are the thing that makes a calling list stop being trusted — two agents
 *     ring the same business and the second one looks like a fool.
 *
 * The column names are the ones the export writes, so a floor can export, fix a
 * column, and put it back.
 */
class LeadImport
{
    /** What the file may say, and what it means. */
    private const COLUMNS = [
        'name' => ['name', 'contact', 'contact name', 'customer', 'customer name'],
        'business_name' => ['business', 'business name', 'company', 'company name'],
        'phone' => ['phone', 'number', 'phone number', 'contact number'],
        'email' => ['email', 'email address'],
        'category' => ['trade', 'category', 'business category', 'service'],
        'state' => ['state', 'usa state'],
        'city' => ['city', 'town'],
        'address' => ['address', 'street', 'location'],
        'website' => ['website', 'site', 'url'],
        'source' => ['source', 'where from', 'lead source'],
        'status' => ['status', 'call status', 'outcome'],
        'notes' => ['notes', 'note', 'comments'],
    ];

    /** A row has to have at least one of these to be a lead at all. */
    private const MUST_HAVE_ONE_OF = ['name', 'business_name', 'phone'];

    private const AT_MOST = 5000;

    public function __construct(private User $importer)
    {
    }

    /**
     * Read it, check it, and — unless this is a dry run — write it.
     *
     * @return array{added:int,skipped:int,problems:array<string>,ready:array}
     */
    public function run(string $path, bool $dryRun = false): array
    {
        $handle = fopen($path, 'r');

        if (!$handle) {
            return $this->nothing(['That file could not be opened.']);
        }

        $heading = fgetcsv($handle);

        if (!$heading) {
            fclose($handle);

            return $this->nothing(['That file is empty.']);
        }

        // Excel writes a byte order mark that otherwise becomes part of the
        // first column's name, so the first column never matches anything.
        $heading[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $heading[0]);

        $map = $this->readHeading($heading);

        if ($map === null) {
            fclose($handle);

            return $this->nothing([
                'None of the columns in that file were recognised. The first row should name the columns — '
                . 'Name, Business, Phone, Email, Trade, State, City, Address, Website, Source, Status, Notes.',
            ]);
        }

        $trades = BusinessCategory::pluck('id', DB::raw('LOWER(name)'))->all();
        $states = UsaState::pluck('id', DB::raw('LOWER(name)'))->all();
        $cities = City::pluck('id', DB::raw('LOWER(name)'))->all();

        // Everything already on file, to recognise a business we already have.
        $known = Lead::query()
            ->select('phone', 'business_name')
            ->get()
            ->flatMap(fn ($l) => array_filter([
                $this->asPhone($l->phone),
                $l->business_name ? mb_strtolower(trim($l->business_name)) : null,
            ]))
            ->flip();

        $problems = [];
        $ready = [];
        $skipped = 0;
        $line = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $line++;

            if (count(array_filter($row, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;                                    // a blank line at the end
            }

            if (count($ready) >= self::AT_MOST) {
                $problems[] = sprintf('Stopped at %s rows. Split the file and import the rest separately.', self::AT_MOST);
                break;
            }

            $lead = $this->readRow($row, $map);

            if (!$this->enoughToBeALead($lead)) {
                $problems[] = "Line {$line}: no name, business or phone — skipped.";
                continue;
            }

            $fingerprints = array_filter([
                $this->asPhone($lead['phone'] ?? null),
                !empty($lead['business_name']) ? mb_strtolower(trim($lead['business_name'])) : null,
            ]);

            foreach ($fingerprints as $f) {
                if ($known->has($f)) {
                    $skipped++;
                    continue 2;                              // already on the list
                }
            }

            // Matched against the company's own lists, never added to them.
            foreach ([['category', $trades, 'business_category_id', 'trade'],
                      ['state', $states, 'usa_state_id', 'state'],
                      ['city', $cities, 'city_id', 'city']] as [$from, $lookup, $to, $what]) {
                $said = trim((string) ($lead[$from] ?? ''));
                unset($lead[$from]);

                if ($said === '') {
                    continue;
                }

                $found = $lookup[mb_strtolower($said)] ?? null;

                if ($found) {
                    $lead[$to] = $found;
                } else {
                    $problems[] = "Line {$line}: no {$what} called \"{$said}\" — imported without it.";
                }
            }

            $lead['company_id'] = $this->importer->company_id;
            $lead['created_by'] = $this->importer->id;
            // A file need not have every column, so these are read as "whatever
            // was there, if anything".
            $lead['source'] = ($lead['source'] ?? '') ?: 'Imported';
            $lead['status'] = ($lead['status'] ?? '') ?: 'No Answer';

            // Plenty of real lists have a business and no contact — the name on
            // the row is the business. The column will not take a null, and
            // refusing the row over it would throw away a usable lead.
            $lead['name'] = ($lead['name'] ?? '') ?: ($lead['business_name'] ?? '');

            $ready[] = array_filter($lead, fn ($v) => $v !== null && $v !== '');

            // Within the file as well as against the table.
            foreach ($fingerprints as $f) {
                $known[$f] = true;
            }
        }

        fclose($handle);

        if ($dryRun) {
            return ['added' => 0, 'skipped' => $skipped, 'problems' => $problems, 'ready' => $ready];
        }

        // One transaction: either the file goes in or it does not.
        DB::transaction(function () use ($ready) {
            foreach (array_chunk($ready, 200) as $batch) {
                foreach ($batch as $lead) {
                    Lead::create($lead);
                }
            }
        });

        return ['added' => count($ready), 'skipped' => $skipped, 'problems' => $problems, 'ready' => []];
    }

    // ---- reading ------------------------------------------------------------

    /** Which column of the file is which field, or null if none of them are. */
    private function readHeading(array $heading): ?array
    {
        $map = [];

        foreach ($heading as $at => $said) {
            $said = mb_strtolower(trim((string) $said));

            foreach (self::COLUMNS as $field => $names) {
                if (in_array($said, $names, true)) {
                    $map[$field] = $at;
                    break;
                }
            }
        }

        return $map ? $map : null;
    }

    private function readRow(array $row, array $map): array
    {
        $lead = [];

        foreach ($map as $field => $at) {
            $lead[$field] = isset($row[$at]) ? trim((string) $row[$at]) : null;
        }

        return $lead;
    }

    private function enoughToBeALead(array $lead): bool
    {
        foreach (self::MUST_HAVE_ONE_OF as $field) {
            if (!empty($lead[$field])) {
                return true;
            }
        }

        return false;
    }

    /**
     * A phone number as the thing it is, rather than as it was typed.
     *
     * "(555) 000-1111" and "5550001111" are the same business, and a list that
     * cannot see that fills up with the same people.
     */
    private function asPhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if (strlen($digits) < 7) {
            return null;
        }

        // Last ten, so a leading 1 or +1 does not make it a different number.
        return substr($digits, -10);
    }

    private function nothing(array $problems): array
    {
        return ['added' => 0, 'skipped' => 0, 'problems' => $problems, 'ready' => []];
    }
}
