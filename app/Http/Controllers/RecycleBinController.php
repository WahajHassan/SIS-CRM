<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\SalesPerformer;
use App\Models\SystemLog;
use App\Models\TransferredLead;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Where deleted things wait.
 *
 * Deleting used to be final and silent. It is now a mark on the row, which
 * means there has to be somewhere to see the marks and take them off — a bin
 * nobody can open is the same as no bin at all.
 *
 * Two different actions live here and they are deliberately not alike. Putting
 * something back is ordinary and anybody who could delete it can do it.
 * Destroying it for good is the one thing in the CRM that cannot be undone, so
 * it is the owner's alone, and it is the only place in the application that
 * ever issues a real DELETE on these tables.
 */
class RecycleBinController extends Controller
{
    /** What the bin holds, in the order somebody would look for it. */
    private const KINDS = [
        'users' => ['label' => 'People', 'model' => User::class, 'icon' => 'user'],
        'leads' => ['label' => 'Leads', 'model' => Lead::class, 'icon' => 'user-plus'],
        'transfers' => ['label' => 'Handovers', 'model' => TransferredLead::class, 'icon' => 'arrow-right-arrow-left'],
        'deals' => ['label' => 'Deals', 'model' => Deal::class, 'icon' => 'file-signature'],
        'performers' => ['label' => 'Scoreboard members', 'model' => SalesPerformer::class, 'icon' => 'chart-line'],
        'companies' => ['label' => 'Companies', 'model' => Company::class, 'icon' => 'building'],
    ];

    public function index(Request $request)
    {
        $me = auth()->user();

        abort_unless($me->canAccess('users'), 403, 'Unauthorized action.');

        $bins = [];

        foreach (self::KINDS as $key => $kind) {
            // Companies are the owner's business and nobody else's.
            if ($key === 'companies' && !$me->isSuperAdmin()) {
                continue;
            }

            $rows = $kind['model']::onlyTrashed()
                ->withoutGlobalScope('company')
                ->when(!$me->isSuperAdmin(), function ($q) use ($me) {
                    // Everybody else sees only their own company's bin. Done by
                    // hand because the company scope was dropped above to let
                    // the owner see across all of them.
                    return $q->where('company_id', $me->company_id);
                })
                ->latest('deleted_at')
                ->limit(200)
                ->get();

            $bins[$key] = [
                'label' => $kind['label'],
                'icon' => $kind['icon'],
                'rows' => $rows->map(fn ($row) => [
                    'id' => $row->id,
                    'name' => $this->nameOf($row),
                    'detail' => $this->detailOf($row),
                    'when' => $row->deleted_at,
                ]),
            ];
        }

        return view('admin.recycle_bin', compact('bins'));
    }

    /** Take the mark off. */
    public function restore(Request $request, string $kind, int $id)
    {
        $model = $this->modelFor($kind);
        $me = auth()->user();

        abort_unless($me->canAccess('users'), 403, 'Unauthorized action.');
        abort_if($kind === 'companies' && !$me->isSuperAdmin(), 403, 'Unauthorized action.');

        $row = $model::onlyTrashed()->withoutGlobalScope('company')->findOrFail($id);

        $this->mustBeTheirs($row);

        $row->restore();

        SystemLog::log('Restored ' . rtrim(self::KINDS[$kind]['label'], 's'), 'Put back: ' . $this->nameOf($row));

        return back()->with('success', $this->nameOf($row) . ' is back.');
    }

    /**
     * Destroy it properly.
     *
     * This is the one action in the CRM that really cannot be undone: the row
     * goes, and every foreign key wired to cascade goes with it. Owner only,
     * and said plainly on the button.
     */
    public function purge(Request $request, string $kind, int $id)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403, 'Only the owner can destroy something for good.');

        $model = $this->modelFor($kind);
        $row = $model::onlyTrashed()->withoutGlobalScope('company')->findOrFail($id);

        $name = $this->nameOf($row);

        SystemLog::log(
            'Destroyed ' . rtrim(self::KINDS[$kind]['label'], 's'),
            "Permanently deleted: {$name}. This could not be undone."
        );

        $row->forceDelete();

        return back()->with('success', $name . ' has been destroyed for good.');
    }

    // ---- the plumbing -------------------------------------------------------

    private function modelFor(string $kind): string
    {
        abort_unless(isset(self::KINDS[$kind]), 404);

        return self::KINDS[$kind]['model'];
    }

    /** Nobody reaches into another company's bin. */
    private function mustBeTheirs($row): void
    {
        $me = auth()->user();

        if ($me->isSuperAdmin() || !isset($row->company_id)) {
            return;
        }

        abort_unless($row->company_id === $me->company_id, 403, 'That is not yours.');
    }

    private function nameOf($row): string
    {
        return $row->display_name
            ?? $row->name
            ?? ($row instanceof TransferredLead ? $row->displayName() : null)
            ?? ('#' . $row->id);
    }

    /** One line saying what else would have gone with it. */
    private function detailOf($row): string
    {
        if ($row instanceof User) {
            return trim(($row->username ? '@' . $row->username . ' · ' : '')
                . ucwords(str_replace('_', ' ', (string) $row->role)));
        }

        if ($row instanceof Deal) {
            return '$' . number_format((float) $row->value, 2) . ' · ' . $row->status;
        }

        if ($row instanceof TransferredLead) {
            return $row->stageLabel() . ($row->agent ? ' · from ' . $row->agent->display_name : '');
        }

        if ($row instanceof Lead) {
            return (string) ($row->business_name ?: $row->phone ?: $row->source);
        }

        if ($row instanceof Company) {
            return (string) $row->slug;
        }

        return '';
    }
}
