<?php

namespace App\Http\Controllers;

use App\Models\BusinessCategory;
use App\Models\City;
use App\Models\FreeSignup;
use App\Models\HrSetting;
use App\Models\UsaState;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Viewrec free signups.
 *
 * An agent fills this in while the business is still on the phone, so the form
 * asks for what can be said in a sentence. Everything anybody does lands in one
 * list, and the only figure the floor is judged on — five a day — is counted off
 * that list rather than kept anywhere separately.
 */
class FreeSignupController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeSection();

        $me = auth()->user();
        $manages = $this->manages($me);

        $query = FreeSignup::with(['agent', 'category', 'state', 'city'])->latest();

        // An agent is shown their own. A team lead answers for their department
        // and is shown that; admins and HR see the floor.
        if (!$manages) {
            $query->takenBy($me->id);
        } else {
            $mine = $this->peopleUnder($me);

            if ($mine !== null) {
                $query->whereIn('created_by', $mine);
            }

            if ($request->filled('agent_id')) {
                $query->takenBy($request->input('agent_id'));
            }
        }

        foreach (['status', 'business_category_id', 'usa_state_id', 'city_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }

        if ($request->filled('on')) {
            $query->onDay($request->input('on'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('business_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $signups = $query->paginate(20)->withQueryString();

        return view('admin.viewrec.index', [
            'signups' => $signups,
            'today' => FreeSignup::todayFor($me),
            'board' => $manages ? $this->todaysBoard() : collect(),
            'manages' => $manages,
            'agents' => $manages ? $this->floor($me) : collect(),
            'categories' => BusinessCategory::active()->orderBy('name')->get(),
            'states' => UsaState::active()->orderBy('name')->get(),
            'cities' => $request->filled('usa_state_id')
                ? City::active()->ofState($request->input('usa_state_id'))->get()
                : collect(),
            'monthTotal' => FreeSignup::inMonth(now())->count(),
            // For whoever is not asked for five of their own: what the floor
            // took today, which is the number they actually want.
            'todayOnTheFloor' => FreeSignup::onDay(now()->toDateString())->count(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeSection();

        $data = $request->validate([
            'name' => 'required|string|max:160',
            'business_name' => 'nullable|string|max:160',
            'email' => 'nullable|email|max:190',
            'phone' => 'nullable|string|max:60',
            'website' => 'nullable|string|max:190',
            'business_category_id' => 'nullable|exists:business_categories,id',
            'usa_state_id' => 'nullable|exists:usa_states,id',
            'city_id' => 'nullable|exists:cities,id',
            'notes' => 'nullable|string|max:2000',
        ]);

        if (empty($data['email']) && empty($data['phone'])) {
            return back()->withInput()->with('error', 'A signup needs an email or a number — otherwise nobody can be reached.');
        }

        $data['created_by'] = auth()->id();
        $data['status'] = 'new';

        $signup = FreeSignup::create($data);

        $today = FreeSignup::todayFor(auth()->user());

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'id' => $signup->id, 'today' => $today]);
        }

        return back()->with('success', $today['left'] > 0
            ? "Signed up. {$today['done']} of {$today['target']} today — {$today['left']} to go."
            : "Signed up. That is {$today['done']} today, the day's five are done.");
    }

    /** Where a signup got to after it was taken. */
    public function updateStatus(Request $request, $id)
    {
        $this->authorizeSection();

        $data = $request->validate([
            'status' => 'required|in:' . implode(',', array_keys(FreeSignup::STATUSES)),
        ]);

        $signup = FreeSignup::findOrFail($id);

        abort_unless($this->manages(auth()->user()) || $signup->created_by === auth()->id(),
            403, 'That signup is not yours to change.');

        $signup->update($data);

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Updated.');
    }

    public function destroy(Request $request, $id)
    {
        $this->authorizeSection();

        abort_unless($this->manages(auth()->user()), 403, 'Only admins and HR can remove a signup.');

        FreeSignup::findOrFail($id)->delete();

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Removed.');
    }

    /** The list as a spreadsheet, for whoever asked for it. */
    public function export(Request $request)
    {
        $this->authorizeSection();

        abort_unless($this->manages(auth()->user()), 403, 'Unauthorized action.');

        $signups = FreeSignup::with(['agent', 'category', 'state', 'city'])
            ->when($request->filled('on'), fn ($q) => $q->onDay($request->input('on')))
            ->when($request->filled('agent_id'), fn ($q) => $q->takenBy($request->input('agent_id')))
            ->latest()
            ->get();

        $filename = 'Viewrec_signups_' . now()->format('Y-m-d') . '.csv';

        return response()->stream(function () use ($signups) {
            $file = fopen('php://output', 'w');

            fputcsv($file, ['Date', 'Name', 'Business', 'Email', 'Phone', 'Trade', 'Where', 'Status', 'Signed up by']);

            foreach ($signups as $signup) {
                fputcsv($file, [
                    $signup->created_at->format('Y-m-d H:i'),
                    $signup->name,
                    $signup->business_name,
                    $signup->email,
                    $signup->phone,
                    $signup->category?->name,
                    $signup->place(),
                    $signup->statusLabel(),
                    $signup->agent?->display_name,
                ]);
            }

            fclose($file);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename={$filename}",
        ]);
    }

    /**
     * Today, per person, against what each was asked for.
     *
     * The question whoever runs the floor actually asks at five o'clock: who has
     * done their five and who has not.
     */
    private function todaysBoard()
    {
        $target = (int) (HrSetting::current()->free_signups_per_day ?: 5);

        $counts = FreeSignup::onDay(now()->toDateString())
            ->selectRaw('created_by, count(*) as total')
            ->groupBy('created_by')
            ->pluck('total', 'created_by');

        return $this->floor(auth()->user())
            ->map(function (User $user) use ($counts, $target) {
                $done = (int) ($counts[$user->id] ?? 0);

                return [
                    'user' => $user,
                    'done' => $done,
                    'target' => $target,
                    'progress' => $target > 0 ? min(100, (int) round($done / $target * 100)) : 0,
                    'met' => $done >= $target,
                ];
            })
            ->sortByDesc('done')
            ->values();
    }

    private function manages(?User $user): bool
    {
        return $user && ($user->isAdmin() || $user->isHr() || $user->isTeamLead());
    }

    /**
     * Whose signups this person may look at.
     *
     * Null for anybody who sees the whole floor. A team lead answers for their
     * own department and sees that — the same line drawn everywhere else a team
     * lead is given somebody else's work to look at.
     */
    private function peopleUnder(User $user): ?array
    {
        if ($user->isAdmin() || $user->isHr()) {
            return null;
        }

        $departments = $user->leadsDepartments()->pluck('id');

        if ($departments->isEmpty()) {
            return [$user->id];
        }

        return \App\Models\EmployeeProfile::whereIn('department_id', $departments)
            ->pluck('user_id')
            ->push($user->id)
            ->unique()
            ->values()
            ->all();
    }

    /** The people whose day is worth showing to this person. */
    private function floor(User $user)
    {
        $under = $this->peopleUnder($user);

        return User::whereIn('role', ['agent', 'closer'])
            ->when($under !== null, fn ($q) => $q->whereIn('id', $under))
            ->orderBy('name')
            ->get();
    }
}
