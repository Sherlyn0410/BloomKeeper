<?php

namespace App\Models;

use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['user_one_id', 'user_two_id', 'user_one_last_read_id', 'user_two_last_read_id', 'last_message_at'])]
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    /**
     * The one conversation between two people, created the first time either of them starts it.
     */
    public static function between(User $first, User $second): self
    {
        [$userOneId, $userTwoId] = $first->id < $second->id ? [$first->id, $second->id] : [$second->id, $first->id];

        return self::firstOrCreate(['user_one_id' => $userOneId, 'user_two_id' => $userTwoId]);
    }

    /**
     * @param  Builder<Conversation>  $query
     */
    public function scopeInvolving(Builder $query, User $user): void
    {
        $query->where(fn (Builder $query) => $query->where('user_one_id', $user->id)->orWhere('user_two_id', $user->id));
    }

    public function includes(User $user): bool
    {
        return in_array($user->id, [$this->user_one_id, $this->user_two_id], true);
    }

    public function otherParticipant(User $user): User
    {
        return $this->user_one_id === $user->id ? $this->userTwo : $this->userOne;
    }

    public function lastReadIdFor(User $user): int
    {
        return (int) ($this->user_one_id === $user->id ? $this->user_one_last_read_id : $this->user_two_last_read_id);
    }

    public function unreadCountFor(User $user): int
    {
        return $this->messages()->where('user_id', '!=', $user->id)->where('id', '>', $this->lastReadIdFor($user))->count();
    }

    /**
     * Mark everything in the conversation as read for this person.
     */
    public function markReadFor(User $user): void
    {
        $latestId = (int) $this->messages()->max('id');
        $column = $this->user_one_id === $user->id ? 'user_one_last_read_id' : 'user_two_last_read_id';

        if ($latestId > $this->{$column}) {
            $this->update([$column => $latestId]);
        }
    }

    public function send(User $sender, ?string $body, ?string $imagePath = null): Message
    {
        $message = $this->messages()->create(['user_id' => $sender->id, 'body' => $body, 'image_path' => $imagePath]);
        $column = $this->user_one_id === $sender->id ? 'user_one_last_read_id' : 'user_two_last_read_id';
        $this->update(['last_message_at' => $message->created_at, $column => $message->id]);

        return $message;
    }

    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }
}
