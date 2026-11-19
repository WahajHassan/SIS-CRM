<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Events\MessageSent;

class ChatController extends Controller
{
    /** The reactions the UI offers. Validation is pinned to this list. */
    private const ALLOWED_REACTIONS = ['👍', '❤️', '😂', '😮', '😢', '🎉'];

    public function index()
    {
        $user = auth()->user();
        
        $conversations = $user->conversations()->with('users')->get();
        
        $all_users = User::with('attendances')
                         ->where('id', '!=', $user->id)
                         ->where('company_id', $user->company_id)
                         ->where('is_active', true)
                         ->get();

        return view('chat.index', compact('conversations', 'all_users'));
    }

    public function fetchMessages($id)
    {
        $user = auth()->user();
        $conversation = $user->conversations()->findOrFail($id);
        $conversation->users()->updateExistingPivot($user->id, ['last_read_at' => now()]);
        // withTrashed on purpose: a deleted message keeps its place in the
        // thread and is presented as a placeholder. Dropping the row instead
        // would silently reflow the conversation for everyone still reading it.
        $messages = $conversation->messages()->withTrashed()->with(['sender', 'reactions'])->get();

        return response()->json($messages->map(function ($m) {
            return $this->presentMessage($m);
        }));
    }
    
    public function poll()
    {
        $user = auth()->user();
        
        $conversations = $user->conversations()->with('users')->get()->map(function ($c) use ($user) {
            $pivot = $c->users->where('id', $user->id)->first()->pivot;
            $last_read = $pivot->last_read_at;
            
            $unreadCount = $c->messages()->where('sender_id', '!=', $user->id)
                ->when($last_read, function($query, $last_read) {
                    return $query->where('created_at', '>', $last_read);
                })->count();
                
            $max_last_read_at = $c->users->where('id', '!=', $user->id)->max('pivot.last_read_at');

            $typingUsers = [];
            foreach ($c->users as $member) {
                if ($member->id !== $user->id) {
                    if (\Cache::has("typing_{$c->id}_{$member->id}")) {
                        $typingUsers[] = $member->display_name;
                    }
                }
            }

            return [
                'id' => $c->id,
                'type' => $c->type,
                'name' => $c->name,
                'created_by' => $c->created_by,
                'member_names' => htmlspecialchars($c->users->pluck('display_name')->implode(', '), ENT_QUOTES),
                'member_ids' => json_encode($c->users->pluck('id')),
                'unread_count' => $unreadCount,
                'last_message_at' => $c->messages()->latest()->value('created_at'),
                'messages_count' => $c->messages()->count(),
                'max_last_read_at' => $max_last_read_at,
                'typing_users' => $typingUsers,
            ];
        });
        
        return response()->json($conversations);
    }

    public function typing(Request $request, $id)
    {
        $user = auth()->user();
        \Cache::put("typing_{$id}_{$user->id}", true, now()->addSeconds(3));
        return response()->json(['success' => true]);
    }

    public function sendMessage(Request $request, $id)
    {
        $request->validate([
            'body' => 'nullable|string',
            'attachment' => 'nullable|file|max:10240', // 10MB max
            // 'voice' marks a recording so the client renders a player rather
            // than a download link; anything else is treated as a plain file.
            'attachment_type' => 'nullable|string|in:file,voice',
        ]);

        if (!$request->body && !$request->hasFile('attachment')) {
            return response()->json(['error' => 'Message cannot be empty.'], 400);
        }

        $conversation = auth()->user()->conversations()->findOrFail($id);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $extension = $file->getClientOriginalExtension();
            
            // If it somehow doesn't have an extension, fallback to guessing
            if (!$extension) {
                $extension = $file->guessExtension() ?? 'txt';
            }
            
            // Clean original filename to use as part of the stored name for better UX
            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $cleanName = preg_replace('/[^A-Za-z0-9\-]/', '_', $originalName);
            
            $filename = uniqid() . '_' . $cleanName . '.' . $extension;
            $attachmentPath = $file->storeAs('chat_attachments', $filename, 'public');
        }

        $message = $conversation->messages()->create([
            'sender_id' => auth()->id(),
            'body' => $request->body,
            'attachment_path' => $attachmentPath,
            'attachment_type' => $attachmentPath
                ? ($request->input('attachment_type') === 'voice' ? 'voice' : 'file')
                : null,
        ]);

        $message->load('sender');

        if(class_exists(MessageSent::class)) {
            try {
                broadcast(new MessageSent($message))->toOthers();
            } catch (\Exception $e) {
                // Log or ignore broadcasting errors so messages still send
                \Log::error('Broadcasting failed: ' . $e->getMessage());
            }
        }

        return response()->json($message);
    }

    public function storeConversation(Request $request)
    {
        $request->validate([
            'type' => 'required|in:direct,group',
            'name' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->type === 'group' && $value) {
                        $exists = \App\Models\Conversation::where('type', 'group')
                                    ->where('company_id', auth()->user()->company_id)
                                    ->where('name', $value)
                                    ->exists();
                        if ($exists) {
                            $fail('A channel with this name already exists.');
                        }
                    }
                }
            ],
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id'
        ]);

        $user = auth()->user();

        // Anybody may start a channel. It used to be admins only, which meant a
        // team wanting a place to talk had to go and ask for one; whoever starts
        // it owns it, and renaming, membership and deleting still sit with them
        // and with the admins.
        

        $userIds = array_values(array_unique(array_merge($request->user_ids ?? [], [$user->id])));

        // Prevent duplicate direct conversations
        if ($request->type === 'direct' && count($userIds) === 2) {
            $otherUserId = array_values(array_diff($userIds, [$user->id]))[0] ?? null;
            if ($otherUserId) {
                $existing = $user->conversations()->where('type', 'direct')
                    ->whereHas('users', function($q) use ($otherUserId) {
                        $q->where('users.id', $otherUserId);
                    })->first();
                if ($existing) {
                    return response()->json($existing->load('users'));
                }
            }
        }

        $conversation = Conversation::create([
            'type' => $request->type,
            'name' => $request->name,
            'created_by' => $user->id,
            'company_id' => $user->company_id,
        ]);
        
        $conversation->users()->sync($userIds);

        return response()->json($conversation->load('users'));
    }

    public function updateConversation(Request $request, $id)
    {
        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($id) {
                    $exists = \App\Models\Conversation::where('type', 'group')
                                ->where('company_id', auth()->user()->company_id)
                                ->where('name', $value)
                                ->where('id', '!=', $id)
                                ->exists();
                    if ($exists) {
                        $fail('A channel with this name already exists.');
                    }
                }
            ],
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'exists:users,id'
        ]);

        $conversation = auth()->user()->conversations()->findOrFail($id);
        
        $user = auth()->user();
        if ($conversation->type === 'group') {
            if ($conversation->created_by !== $user->id && !in_array($user->role, ['admin', 'superadmin', 'floor_manager'])) {
                return response()->json(['error' => 'Unauthorized to rename this channel.'], 403);
            }
            $conversation->update(['name' => $request->name]);
            
            if ($request->has('user_ids')) {
                $userIds = $request->user_ids;
                $userIds[] = $conversation->created_by; // ensure creator is always in it
                $conversation->users()->sync(array_unique($userIds));
            }
            
            return response()->json($conversation);
        }
        
        return response()->json(['error' => 'Cannot update direct messages.'], 400);
    }

    /**
     * Leave a channel.
     *
     * Somebody added to a channel they have nothing to do with should be able to
     * step out of it themselves rather than asking whoever made it. The person
     * who started it stays — a channel with no owner has nobody to rename or
     * close it.
     */
    public function leaveConversation($id)
    {
        $user = auth()->user();
        $conversation = $user->conversations()->findOrFail($id);

        if ($conversation->type !== 'group') {
            return response()->json(['error' => 'Direct messages cannot be left.'], 400);
        }

        if ($conversation->created_by === $user->id) {
            return response()->json([
                'error' => 'You started this channel. Hand it over or delete it instead.',
            ], 400);
        }

        $conversation->users()->detach($user->id);

        return response()->json(['success' => true]);
    }

    public function deleteConversation($id)
    {
        $conversation = auth()->user()->conversations()->findOrFail($id);
        
        $user = auth()->user();
        if ($conversation->type === 'group') {
            if ($conversation->created_by !== $user->id && !in_array($user->role, ['admin', 'superadmin', 'floor_manager'])) {
                return response()->json(['error' => 'Unauthorized to delete this channel.'], 403);
            }
            $conversation->delete();
            return response()->json(['success' => true]);
        }

        return response()->json(['error' => 'Cannot delete direct messages this way.'], 400);
    }

    /**
     * Who is online right now, for the dots beside each person in Direct
     * Messages. Kept separate from poll() so that response keeps its shape for
     * the other page that reads it.
     */
    public function presence()
    {
        $user = auth()->user();

        $online = User::where('company_id', $user->company_id)
            ->where('id', '!=', $user->id)
            ->where('last_seen_at', '>=', now()->subSeconds(User::ONLINE_WINDOW_SECONDS))
            ->pluck('id');

        return response()->json(['online' => $online]);
    }

    /**
     * One message as the client wants it: sender, reaction summary, and a
     * deleted message reduced to a placeholder so its text never reaches the
     * browser after it has been removed.
     */
    private function presentMessage($m): array
    {
        $deleted = $m->trashed();

        return [
            'id'              => $m->id,
            'conversation_id' => $m->conversation_id,
            'sender_id'       => $m->sender_id,
            // Their face and their mark travel with the message. A chat is
            // where somebody is asked to do something, so who is asking is
            // part of the message, not decoration.
            'sender'          => $m->sender ? [
                'id' => $m->sender->id,
                'name' => $m->sender->display_name ?? $m->sender->name,
                'face' => $m->sender->avatar_url,
                'initial' => $m->sender->initial,
                'tone' => $m->sender->tone,
                'verified' => $m->sender->isVerified(),
            ] : null,
            'body'            => $deleted ? null : $m->body,
            'attachment_path' => $deleted ? null : $m->attachment_path,
            'attachment_type' => $deleted ? null : $m->attachment_type,
            'edited'          => (bool) $m->edited_at,
            // The time, not just the flag: a second edit leaves `edited` true, and
            // the client's change detection needs something that moves each time.
            'edited_at'       => $deleted ? null : optional($m->edited_at)->toIso8601String(),
            'deleted'         => $deleted,
            'reactions'       => $deleted ? [] : $m->reactionSummary(),
            'created_at'      => $m->created_at,
        ];
    }

    /** A message in a conversation the caller belongs to, or 404. */
    private function messageInMyConversation($id)
    {
        $conversationIds = auth()->user()->conversations()->pluck('conversations.id');

        return \App\Models\Message::withTrashed()
            ->whereIn('conversation_id', $conversationIds)
            ->findOrFail($id);
    }

    /**
     * Edit a message. Only its author, and only while it still exists: editing a
     * deleted message would resurrect text the sender has already withdrawn.
     */
    public function editMessage(Request $request, $id)
    {
        $data = $request->validate(['body' => 'required|string|max:5000']);

        $message = $this->messageInMyConversation($id);

        abort_if($message->sender_id !== auth()->id(), 403, 'You can only edit your own messages.');
        abort_if($message->trashed(), 404);

        $message->body = $data['body'];
        $message->edited_at = now();
        $message->save();

        $message->load(['sender', 'reactions']);

        return response()->json($this->presentMessage($message));
    }

    /**
     * Delete a message. Its author always may; an admin may also remove anyone's,
     * which is what makes moderation possible in a shared channel.
     */
    public function deleteMessage($id)
    {
        $message = $this->messageInMyConversation($id);

        $isMine = $message->sender_id === auth()->id();
        abort_if(!$isMine && !auth()->user()->isAdmin(), 403, 'You cannot delete this message.');

        $message->delete();

        return response()->json(['deleted' => true, 'id' => (int) $id]);
    }

    /**
     * Add or remove one emoji reaction. Clicking the same emoji again takes it
     * off, so the button is a toggle rather than a counter that only goes up.
     */
    public function toggleReaction(Request $request, $id)
    {
        $data = $request->validate([
            // Deliberately narrow: a fixed set keeps arbitrary text out of a
            // column that is rendered straight into everyone's chat window.
            'emoji' => 'required|string|in:' . implode(',', self::ALLOWED_REACTIONS),
        ]);

        $message = $this->messageInMyConversation($id);
        abort_if($message->trashed(), 404);

        $existing = \App\Models\MessageReaction::where('message_id', $message->id)
            ->where('user_id', auth()->id())
            ->where('emoji', $data['emoji'])
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            \App\Models\MessageReaction::create([
                'message_id' => $message->id,
                'user_id'    => auth()->id(),
                'emoji'      => $data['emoji'],
            ]);
        }

        $message->load('reactions');

        return response()->json([
            'id'        => $message->id,
            'reactions' => $message->reactionSummary(),
        ]);
    }
}
