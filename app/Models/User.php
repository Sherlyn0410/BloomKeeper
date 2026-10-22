<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

#[Fillable(['name', 'email', 'role', 'approval_status', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $attributes = [
        'role' => 'staff',
        'approval_status' => 'approved',
    ];

    /**
     * Send the verification email without letting a slow or unreachable mail server break the page.
     * Returns false when the email could not be sent, so the page can ask the user to resend it.
     */
    public function trySendingVerificationEmail(): bool
    {
        try {
            $this->sendEmailVerificationNotification();

            return true;
        } catch (TransportExceptionInterface $exception) {
            report($exception);

            return false;
        }
    }

    /**
     * Messages from other people that this user hasn't opened yet, across every conversation.
     */
    public function unreadMessageCount(): int
    {
        return Message::query()
            ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
            ->where('messages.user_id', '!=', $this->id)
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query->where('conversations.user_one_id', $this->id)->whereColumn('messages.id', '>', 'conversations.user_one_last_read_id'))
                ->orWhere(fn ($query) => $query->where('conversations.user_two_id', $this->id)->whereColumn('messages.id', '>', 'conversations.user_two_last_read_id')))
            ->count();
    }

    /**
     * Time-of-day greeting in Malaysia time with the user's first name, e.g. "Good afternoon, Sherlyn."
     */
    public function greeting(): string
    {
        $hour = now('Asia/Kuala_Lumpur')->hour;
        $timeOfDay = match (true) {
            $hour < 12 => 'Good morning',
            $hour < 18 => 'Good afternoon',
            default => 'Good evening',
        };

        return $timeOfDay.', '.Str::of($this->name)->before(' ').'.';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
