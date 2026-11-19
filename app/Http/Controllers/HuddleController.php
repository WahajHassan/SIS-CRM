<?php

namespace App\Http\Controllers;

use App\Models\Huddle;
use App\Models\HuddleSignal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Quick Huddle.
 *
 * Somebody is stuck, somebody else can see it faster than it can be explained.
 * One asks, the other says yes, and from then on the helper is looking at the
 * same CRM — and, if the host allows it, clicking in it.
 *
 * What this controller does is arrange that and write down what was agreed. The
 * picture and the clicks go straight between the two browsers and never come
 * through here.
 *
 * Three rules hold the whole thing up, and they are enforced here rather than
 * in the page, because the page is the part that cannot be trusted:
 *
 *   1. Both people work for the same company.
 *   2. Nothing starts without the host answering a request that names the guest.
 *   3. Clicking is a separate yes from watching, and the host can take it back.
 */
class HuddleController extends Controller
{
    public function index()
    {
        $me = auth()->user();

        return view('admin.huddle.index', [
            'me' => $me,
            'people' => $this->colleagues($me),
            'live' => $this->liveFor($me),
        ]);
    }

    /**
     * Who could be asked, and who is at their desk.
     *
     * Polled while the page is open, so it is one query and no eager loading of
     * things the list does not show.
     */
    public function people()
    {
        $me = auth()->user();

        return response()->json([
            'people' => $this->colleagues($me),
            'live' => $this->liveFor($me),
        ]);
    }

    /**
     * The little window that holds the sharing.
     *
     * It is a window of its own because a capture belongs to the document that
     * started it, and the CRM page is the one being walked through: keep them
     * together and the first click that opens another section throws the
     * sharing away.
     */
    public function share(Request $request, Huddle $huddle)
    {
        $me = auth()->user();
        $this->mustBeTheHost($huddle, $me);
        abort_unless($huddle->isLive(), 410, 'That huddle is over.');

        return view('admin.huddle.share', [
            'huddle' => $huddle,
            'guest' => $huddle->guest?->display_name ?? 'a colleague',
        ]);
    }

    /** Asking somebody if you may look over their shoulder. */
    public function request(Request $request)
    {
        $me = auth()->user();

        $data = $request->validate([
            'host_id' => ['required', 'integer'],
        ]);

        $host = User::where('company_id', $me->company_id)->find($data['host_id']);

        if (!$host) {
            throw ValidationException::withMessages([
                'host_id' => 'That person is not on your team.',
            ]);
        }

        // The same rule the button on the page is drawn from. Asked again here,
        // because the page is the part that cannot be trusted.
        if ($why = Huddle::whyNotAsk($me, $host)) {
            throw ValidationException::withMessages(['host_id' => $why]);
        }

        if (!$host->isOnline()) {
            throw ValidationException::withMessages([
                'host_id' => $host->display_name . ' is not at their desk right now.',
            ]);
        }

        // One at a time, each way. Two people trying to watch the same screen,
        // or one person in two huddles, is a tangle nobody asked for. Pressing
        // Ask is rare, so this is the moment to look properly.
        $this->sweep();

        $busy = Huddle::live()
            ->where(function ($q) use ($host, $me) {
                $q->involving($host->id)->orWhere(fn ($w) => $w->involving($me->id));
            })
            ->first();

        if ($busy) {
            throw ValidationException::withMessages([
                'host_id' => $busy->host_id === $host->id || $busy->guest_id === $host->id
                    ? $host->display_name . ' is already in a huddle.'
                    : 'You are already in a huddle.',
            ]);
        }

        $huddle = Huddle::create([
            'host_id' => $host->id,
            'guest_id' => $me->id,
            'status' => Huddle::PENDING,
            'can_control' => false,
        ]);

        return response()->json(['huddle' => $huddle->fresh(['host', 'guest'])->forUser($me)]);
    }

    /**
     * What is waiting for me, and what I am already in.
     *
     * Polled by every page in the CRM, because a request has to find the host
     * wherever they happen to be rather than only on this screen.
     */
    public function mine()
    {
        $me = auth()->user();
        $this->sweepOccasionally();

        // Every open page asks this every few seconds, so it is one indexed
        // read. Nobody can be in two huddles at once, so the one row that comes
        // back answers both questions: am I being asked, and am I already in one.
        $huddle = Huddle::live()
            ->involving($me->id)
            ->with(['host', 'guest'])
            ->latest('id')
            ->first();

        $asking = $huddle
            && $huddle->status === Huddle::PENDING
            && $huddle->host_id === $me->id
            && $huddle->created_at->gte(now()->subSeconds(Huddle::REQUEST_EXPIRES_AFTER_SECONDS));

        return response()->json([
            'asking' => $asking ? [
                'id' => $huddle->id,
                'from' => $huddle->guest?->display_name,
                'role' => $huddle->guest?->role,
                'askedAt' => $huddle->created_at->toIso8601String(),
            ] : null,
            'live' => $huddle ? $huddle->forUser($me) : null,
        ]);
    }

    /** Yes or no, from the person being asked. */
    public function answer(Request $request, Huddle $huddle)
    {
        $me = auth()->user();
        $this->mustBeTheHost($huddle, $me);

        $data = $request->validate([
            'accept' => ['required', 'boolean'],
            // Saying yes to being watched is not saying yes to being clicked.
            'can_control' => ['sometimes', 'boolean'],
        ]);

        if ($huddle->status !== Huddle::PENDING) {
            return response()->json(['huddle' => $huddle->forUser($me)]);
        }

        if (!$data['accept']) {
            $huddle->update([
                'status' => Huddle::DECLINED,
                'answered_at' => now(),
                'ended_at' => now(),
                'ended_by' => 'host',
            ]);

            return response()->json(['huddle' => $huddle->fresh(['host', 'guest'])->forUser($me)]);
        }

        $huddle->update([
            'status' => Huddle::ACTIVE,
            'answered_at' => now(),
            'can_control' => (bool) ($data['can_control'] ?? false),
        ]);

        return response()->json(['huddle' => $huddle->fresh(['host', 'guest'])->forUser($me)]);
    }

    /** The host handing over, or taking back, the mouse. */
    public function control(Request $request, Huddle $huddle)
    {
        $me = auth()->user();
        $this->mustBeTheHost($huddle, $me);

        $data = $request->validate(['can_control' => ['required', 'boolean']]);

        $huddle->update(['can_control' => (bool) $data['can_control']]);

        return response()->json(['huddle' => $huddle->fresh(['host', 'guest'])->forUser($me)]);
    }

    /** Either of them can stop it, at any point, without asking. */
    public function end(Huddle $huddle)
    {
        $me = auth()->user();
        $this->mustBeInIt($huddle, $me);

        if ($huddle->isLive()) {
            $huddle->update([
                'status' => Huddle::ENDED,
                'ended_at' => now(),
                'ended_by' => $huddle->host_id === $me->id ? 'host' : 'guest',
            ]);
        }

        return response()->json(['huddle' => $huddle->fresh(['host', 'guest'])->forUser($me)]);
    }

    /** One of the few messages the two browsers swap to find each other. */
    public function signal(Request $request, Huddle $huddle)
    {
        $me = auth()->user();
        $this->mustBeInIt($huddle, $me);

        $data = $request->validate([
            'kind' => ['required', 'string', 'in:offer,answer,ice'],
            'payload' => ['required', 'string', 'max:20000'],
        ]);

        HuddleSignal::create([
            'huddle_id' => $huddle->id,
            'from_id' => $me->id,
            'kind' => $data['kind'],
            'payload' => $data['payload'],
        ]);

        return response()->json(['ok' => true]);
    }

    /** Everything the other side has said since the last thing I heard. */
    public function signals(Request $request, Huddle $huddle)
    {
        $me = auth()->user();
        $this->mustBeInIt($huddle, $me);

        $since = (int) $request->query('since', 0);

        // Both sides poll this while a huddle is on, so asking is the proof
        // that somebody is still there.
        $huddle->forceFill(['updated_at' => now()])->saveQuietly();

        $signals = HuddleSignal::where('huddle_id', $huddle->id)
            ->where('from_id', '!=', $me->id)
            ->where('id', '>', $since)
            ->orderBy('id')
            ->limit(80)
            ->get(['id', 'kind', 'payload']);

        return response()->json([
            'signals' => $signals,
            'status' => $huddle->status,
            'canControl' => (bool) $huddle->can_control,
        ]);
    }

    // ---- the plumbing -------------------------------------------------------

    /**
     * Everyone this person could ask, in one list.
     *
     * Their own company only, and never themselves. Who is online decides what
     * the button beside each name says, not whether the name is there at all —
     * a floor is easier to read when it does not reshuffle every few seconds.
     */
    private function colleagues(User $me): array
    {
        return User::where('company_id', $me->company_id)
            ->where('id', '!=', $me->id)
            ->orderBy('name')
            // display_name reads the sales performer behind the login when there
            // is one, so that relation comes along rather than being asked for
            // once per person.
            ->with('performer:id,name,sudo_name')
            // company_id comes along because the rule compares it, and a column
            // that is not selected reads as null — which made everyone look
            // like they were at another company.
            // The picture, where they have one, and the mark, where they have
            // been given one: this list is where somebody decides whose screen
            // to ask for, which is exactly when knowing who they are matters.
            ->get(['id', 'name', 'role', 'company_id', 'last_seen_at', 'sales_performer_id',
                   'avatar', 'avatar_tone', 'verified_at'])
            ->map(function (User $person) use ($me) {
                $why = Huddle::whyNotAsk($me, $person);

                return [
                    'id' => $person->id,
                    'name' => $person->display_name,
                    'role' => ucwords(str_replace('_', ' ', $person->role)),
                    'online' => $person->isOnline(),
                    'canAsk' => $why === null,
                    'why' => $why,
                    'face' => $person->avatar_url,
                    'initial' => $person->initial,
                    'tone' => $person->tone,
                    'verified' => $person->isVerified(),
                ];
            })
            ->values()
            ->all();
    }

    private function liveFor(User $me): ?array
    {
        $huddle = Huddle::live()
            ->involving($me->id)
            ->with(['host', 'guest'])
            ->latest()
            ->first();

        return $huddle ? $huddle->forUser($me) : null;
    }

    /**
     * The sweep, but not on every poll.
     *
     * Clearing up is worth doing; doing it six hundred times a minute to find
     * nothing is not. The first caller in each half minute does it for everybody
     * — Cache::add only succeeds for whoever gets there first.
     */
    private function sweepOccasionally(): void
    {
        if (!Cache::add('huddle:swept', 1, now()->addSeconds(30))) {
            return;
        }

        $this->sweep();
    }

    /**
     * A request nobody answered is not a request any more.
     */
    private function sweep(): void
    {
        Huddle::where('status', Huddle::PENDING)
            ->where('created_at', '<', now()->subSeconds(Huddle::REQUEST_EXPIRES_AFTER_SECONDS))
            ->update([
                'status' => Huddle::ENDED,
                'ended_at' => now(),
                'ended_by' => 'timeout',
            ]);

        // A closed laptop never says goodbye. Both sides poll for signals every
        // second or so while a huddle is on, so one that has gone quiet for a
        // minute is over whether or not anybody said so — and leaving it open
        // would lock both people out of starting another.
        Huddle::where('status', Huddle::ACTIVE)
            ->where('updated_at', '<', now()->subSeconds(Huddle::GOES_QUIET_AFTER_SECONDS))
            ->update([
                'status' => Huddle::ENDED,
                'ended_at' => now(),
                'ended_by' => 'timeout',
            ]);
    }

    private function mustBeInIt(Huddle $huddle, User $me): void
    {
        abort_unless($huddle->host_id === $me->id || $huddle->guest_id === $me->id, 403);
    }

    private function mustBeTheHost(Huddle $huddle, User $me): void
    {
        abort_unless($huddle->host_id === $me->id, 403);
    }
}
