<?php

namespace App\Http\Controllers;

use App\Models\FloorMoment;
use Illuminate\Http\Request;

/**
 * Calling something out to the floor, and deciding what can be called out.
 *
 * Rush hour was one hour with one message, written into a controller. The floor
 * wanted a break, and prayer time, and a lunch that is forty-five minutes at one
 * company and thirty at another — none of which is a code change, so none of it
 * is in code any more.
 *
 * Starting one is for whoever runs the floor. Deciding what the list contains is
 * for an admin.
 */
class FloorMomentController extends Controller
{
    /** What can be called out, and what is running. */
    public function index()
    {
        $me = auth()->user();

        return response()->json([
            'moments' => FloorMoment::live()->inOrder()->get()
                ->map(fn (FloorMoment $m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'message' => $m->message,
                    'icon' => $m->icon,
                    'tone' => $m->tone,
                    'minutes' => $m->minutes,
                ])->values(),
            'running' => $this->runningFor($me->company_id),
        ]);
    }

    public function start(Request $request, FloorMoment $moment)
    {
        $this->mustRunTheFloor();

        $data = $request->validate([
            // Whatever is typed in the box beside the button wins over the
            // moment's own wording, which is how rush hour already worked.
            'message' => ['nullable', 'string', 'max:160'],
        ]);

        abort_unless($moment->is_active, 404);

        $moment->begin($data['message'] ?? null);

        return response()->json(['running' => $this->runningFor($moment->company_id)]);
    }

    public function stop()
    {
        $this->mustRunTheFloor();

        FloorMoment::stop(auth()->user()->company_id);

        return response()->json(['running' => null]);
    }

    /* ---- deciding what the list contains ---------------------------------- */

    public function settings()
    {
        return view('admin.settings.moments', [
            'moments' => FloorMoment::inOrder()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->mustBeAdmin();

        $data = $this->validated($request);

        FloorMoment::create($data + [
            'position' => (int) FloorMoment::max('position') + 1,
        ]);

        return back()->with('success', $data['name'] . ' can be called out now.');
    }

    public function update(Request $request, FloorMoment $moment)
    {
        $this->mustBeAdmin();

        $moment->update($this->validated($request));

        return back()->with('success', $moment->name . ' updated.');
    }

    public function destroy(FloorMoment $moment)
    {
        $this->mustBeAdmin();

        $name = $moment->name;
        $moment->delete();

        return back()->with('success', $name . ' removed.');
    }

    // ---- the plumbing -------------------------------------------------------

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'message' => ['required', 'string', 'max:160'],
            'icon' => ['nullable', 'string', 'max:40'],
            'tone' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            // Long enough to be useful, short enough that a mis-typed number
            // does not lock the scoreboard up for a week.
            'minutes' => ['required', 'integer', 'min:1', 'max:480'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['icon'] = $data['icon'] ?: 'fa-bullhorn';
        $data['tone'] = $data['tone'] ?: '#F99D4E';
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function runningFor(?int $companyId): ?array
    {
        if (!$companyId) {
            return null;
        }

        $running = FloorMoment::running($companyId);

        if (!$running) {
            return null;
        }

        return [
            'name' => $running['moment'] ?? 'Rush Hour',
            'message' => $running['message'] ?? '',
            'icon' => $running['icon'] ?? 'fa-rocket',
            'tone' => $running['tone'] ?? '#F99D4E',
            'endsAt' => $running['ends_at']->toIso8601String(),
        ];
    }

    private function mustRunTheFloor(): void
    {
        $me = auth()->user();
        abort_unless($me && ($me->isAdmin() || $me->isCloser()), 403);
        abort_unless($me->company_id, 403, 'Pick a company first.');
    }

    private function mustBeAdmin(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }
}
