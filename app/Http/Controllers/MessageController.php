<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MessageController extends Controller
{
    public function index(Request $request): View
    {
        return view('messages', $this->pageData($request->user()));
    }

    public function show(Request $request, Conversation $conversation): View
    {
        $user = $request->user();
        $conversation->markReadFor($user);

        return view('messages', [
            ...$this->pageData($user),
            'conversation' => $conversation,
            'otherUser' => $conversation->otherParticipant($user),
            'messages' => $conversation->messages()->latest('id')->limit(100)->get()->reverse()->values(),
        ]);
    }

    /**
     * Open the conversation with someone, starting it if this is the first message between them.
     */
    public function start(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')->where('approval_status', 'approved')->whereNot('id', $request->user()->id)
                ->when($request->user()->role === 'supplier', fn ($rule) => $rule->whereNot('role', 'supplier'))],
        ], [
            'user_id.required' => 'Please select who to message.',
            'user_id.exists' => 'That person is not available to message.',
        ]);

        return redirect()->route('messages.show', Conversation::between($request->user(), User::query()->findOrFail($data['user_id'])));
    }

    public function store(Request $request, Conversation $conversation): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'body' => ['nullable', 'required_without:image', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ], [
            'body.required_without' => 'Please type a message or attach a photo.',
            'image.image' => 'The attachment must be a photo.',
            'image.mimes' => 'Photos must be JPG, PNG, WEBP, or GIF.',
            'image.max' => 'Photos must be 5 MB or smaller.',
        ]);

        $body = isset($data['body']) && trim($data['body']) !== '' ? trim($data['body']) : null;
        $imagePath = $request->file('image')?->store("chat/{$conversation->id}", 'local');

        $message = $conversation->send($request->user(), $body, $imagePath);

        return $request->expectsJson()
            ? response()->json($message->toChatArray($request->user()), 201)
            : redirect()->route('messages.show', $conversation);
    }

    /**
     * Photos live on the private disk and are only served to the two people in the conversation.
     */
    public function image(Conversation $conversation, Message $message): StreamedResponse
    {
        abort_unless($message->conversation_id === $conversation->id && $message->image_path, 404);

        return Storage::disk('local')->response($message->image_path);
    }

    /**
     * New messages after the last one the page already shows; opening them marks them as read.
     */
    public function poll(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        $messages = $conversation->messages()->where('id', '>', $request->integer('after'))->orderBy('id')->get();
        $conversation->markReadFor($user);

        return response()->json([
            'messages' => $messages->map->toChatArray($user)->values(),
        ]);
    }

    /**
     * @return array{conversations: Collection<int, array{conversation: Conversation, other: User, unread: int}>, contacts: Collection<int, User>}
     */
    private function pageData(User $user): array
    {
        return [
            'conversations' => Conversation::query()->involving($user)->whereNotNull('last_message_at')
                ->with(['userOne', 'userTwo', 'latestMessage'])
                ->orderByDesc('last_message_at')
                ->get()
                ->map(fn (Conversation $conversation): array => [
                    'conversation' => $conversation,
                    'other' => $conversation->otherParticipant($user),
                    'unread' => $conversation->unreadCountFor($user),
                ]),
            // Suppliers talk to the shop, not to each other.
            'contacts' => User::query()->where('approval_status', 'approved')->whereKeyNot($user->id)
                ->when($user->role === 'supplier', fn ($query) => $query->where('role', '!=', 'supplier'))
                ->orderByRaw("CASE role WHEN 'admin' THEN 0 WHEN 'staff' THEN 1 ELSE 2 END")->orderBy('name')->get(),
        ];
    }
}
