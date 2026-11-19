<?php

namespace App\Http\Controllers;

use App\Models\Note;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The floating notepad.
 *
 * Everything here is scoped to the signed-in user, not merely to their company:
 * a note is personal, and two colleagues in the same company must never see each
 * other's. `mine()` is the only way rows are looked up, so there is no path that
 * can return someone else's note even with a guessed id.
 *
 * The editor saves as the user types, so store/update are called often and kept
 * deliberately cheap.
 */
class NoteController extends Controller
{
    /** Notes belonging to the current user, in their chosen order. */
    private function mine()
    {
        return Note::where('user_id', auth()->id());
    }

    public function index(): JsonResponse
    {
        $notes = $this->mine()
            ->orderBy('position')
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'body', 'position', 'updated_at']);

        return response()->json(['notes' => $notes]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:200',
            'body'  => 'nullable|string|max:100000',
        ]);

        $note = Note::create([
            'user_id'    => auth()->id(),
            'company_id' => auth()->user()->company_id,
            'title'      => $data['title'] ?? null,
            'body'       => $data['body'] ?? null,
            // New notes go to the top; existing positions are left alone.
            'position'   => (int) $this->mine()->min('position') - 1,
        ]);

        return response()->json(['note' => $note->only(['id', 'title', 'body', 'position', 'updated_at'])], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:200',
            'body'  => 'nullable|string|max:100000',
        ]);

        $note = $this->mine()->findOrFail($id);
        $note->fill($data)->save();

        return response()->json([
            'saved_at' => $note->updated_at->toIso8601String(),
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $this->mine()->findOrFail($id)->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * Persist a hand-sorted order in one request rather than one per note.
     * Ids that are not the caller's are ignored rather than rejected, so a stale
     * tab cannot fail the whole reorder.
     */
    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids'   => 'required|array|max:500',
            'ids.*' => 'integer',
        ]);

        $mine = $this->mine()->pluck('id')->flip();

        foreach ($data['ids'] as $position => $id) {
            if ($mine->has($id)) {
                $this->mine()->where('id', $id)->update(['position' => $position]);
            }
        }

        return response()->json(['ok' => true]);
    }
}
