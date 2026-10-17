<?php

namespace App\Models;

use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['conversation_id', 'user_id', 'body', 'image_path'])]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    /**
     * The shape the chat page's polling script renders.
     *
     * @return array{id: int, body: ?string, image_url: ?string, mine: bool, time: string}
     */
    public function toChatArray(User $viewer): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'image_url' => $this->image_path ? route('messages.image', [$this->conversation_id, $this->id]) : null,
            'mine' => $this->user_id === $viewer->id,
            'time' => $this->created_at->timezone('Asia/Kuala_Lumpur')->format('d M, h:i A'),
        ];
    }

    /**
     * Short text for the conversation list, so a photo-only message still has a preview.
     */
    public function preview(): string
    {
        return $this->body ?? 'Photo';
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
