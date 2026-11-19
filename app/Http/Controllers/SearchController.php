<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\Lead;
use App\Models\Training;
use App\Models\TransferredLead;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * One place to look for anything.
 *
 * Every screen had its own filter box and the CRM had no way to answer "where
 * is Gutter Co" without knowing first whether Gutter Co was a lead, a handover
 * or a deal. Somebody with a customer on the phone does not know that, and
 * should not have to.
 *
 * The part worth being careful about is who sees what. This searches across
 * sections, so it would be the easiest place in the whole CRM to leak
 * something: an agent typing a name and finding a salary, or one company
 * finding another's customer. Every group below is behind the same canAccess()
 * the sidebar uses, and the company scope the models carry is never removed.
 */
class SearchController extends Controller
{
    /** Short enough to be fast, long enough to be useful. */
    private const PER_GROUP = 5;

    public function __invoke(Request $request)
    {
        $me = auth()->user();
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['term' => $term, 'groups' => []]);
        }

        $like = '%' . $term . '%';

        $groups = array_values(array_filter([
            $this->leads($me, $like),
            $this->handovers($me, $like),
            $this->deals($me, $like),
            $this->people($me, $like),
            $this->training($me, $like),
        ]));

        return response()->json([
            'term' => $term,
            'groups' => $groups,
            'total' => array_sum(array_map(fn ($g) => count($g['items']), $groups)),
        ]);
    }

    /* ---- the groups ---------------------------------------------------------- */

    private function leads(User $me, string $like): ?array
    {
        if (!$me->canAccess('leads')) {
            return null;
        }

        $rows = Lead::with(['category', 'city', 'state'])
            ->where(fn ($q) => $q
                ->where('name', 'like', $like)
                ->orWhere('business_name', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('address', 'like', $like))
            ->latest()
            ->limit(self::PER_GROUP)
            ->get();

        return $this->group('Leads', 'fa-user-plus', $rows, fn (Lead $l) => [
            'title' => $l->business_name ?: $l->name,
            'detail' => collect([$l->name, $l->phone, $l->category?->name])->filter()->implode(' · '),
            'url' => route('admin.leads', ['search' => $l->business_name ?: $l->name]),
        ]);
    }

    private function handovers(User $me, string $like): ?array
    {
        if (!$me->canAccess('transferred_leads')) {
            return null;
        }

        $rows = TransferredLead::with(['agent', 'closer', 'deal'])
            ->where(fn ($q) => $q
                ->where('business_name', 'like', $like)
                ->orWhere('customer_name', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('email', 'like', $like))
            ->latest()
            ->limit(self::PER_GROUP)
            ->get();

        return $this->group('Handovers', 'fa-arrow-right-arrow-left', $rows, fn (TransferredLead $t) => [
            'title' => $t->displayName(),
            'detail' => collect([
                $t->stageLabel(),
                $t->deal ? '$' . number_format((float) $t->deal->value) : null,
                $t->closer?->display_name,
            ])->filter()->implode(' · '),
            'url' => route('admin.transferred_leads', ['search' => $t->displayName()]),
        ]);
    }

    private function deals(User $me, string $like): ?array
    {
        if (!$me->canAccess('deals')) {
            return null;
        }

        $rows = Deal::where(fn ($q) => $q
                ->where('name', 'like', $like)
                ->orWhere('business_owner_name', 'like', $like)
                ->orWhere('business_number', 'like', $like)
                ->orWhere('business_email', 'like', $like))
            ->latest()
            ->limit(self::PER_GROUP)
            ->get();

        return $this->group('Deals', 'fa-file-signature', $rows, fn (Deal $d) => [
            'title' => $d->name,
            'detail' => '$' . number_format((float) $d->value, 2) . ' · ' . $d->status
                . ($d->closer_by ? ' · ' . $d->closer_by : ''),
            'url' => route('admin.deals', ['search' => $d->name]),
        ]);
    }

    private function people(User $me, string $like): ?array
    {
        // The staff list is behind User Management or HR, never open to the floor.
        if (!$me->canAccess('users') && !$me->canAccess('hr_employees')) {
            return null;
        }

        $rows = User::where(fn ($q) => $q
                ->where('name', 'like', $like)
                ->orWhere('username', 'like', $like)
                ->orWhere('email', 'like', $like))
            ->orderBy('name')
            ->limit(self::PER_GROUP)
            ->get();

        return $this->group('People', 'fa-user', $rows, fn (User $u) => [
            'title' => $u->display_name,
            'detail' => collect([
                ucwords(str_replace('_', ' ', $u->role)),
                $u->email,
                $u->isVerified() ? 'verified' : null,
            ])->filter()->implode(' · '),
            'url' => $me->canAccess('users')
                ? route('admin.users', ['search' => $u->username])
                : route('admin.hr.employees', ['search' => $u->name]),
        ]);
    }

    private function training(User $me, string $like): ?array
    {
        if (!$me->canAccess('training')) {
            return null;
        }

        $rows = Training::where(fn ($q) => $q
                ->where('title', 'like', $like)
                ->orWhere('body', 'like', $like))
            ->latest()
            ->limit(self::PER_GROUP)
            ->get();

        return $this->group('Training', 'fa-graduation-cap', $rows, fn (Training $t) => [
            'title' => $t->title,
            'detail' => \Illuminate\Support\Str::limit(strip_tags((string) $t->body), 60),
            // Straight to the training itself rather than the list.
            'url' => route('admin.training.show', $t->id),
        ]);
    }

    // ---- the plumbing ---------------------------------------------------------

    private function group(string $label, string $icon, $rows, callable $shape): ?array
    {
        if ($rows->isEmpty()) {
            return null;
        }

        return [
            'label' => $label,
            'icon' => $icon,
            'items' => $rows->map($shape)->values()->all(),
        ];
    }
}
