<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin', 'name' => 'Aina Admin']);
    $this->staff = User::factory()->create(['role' => 'staff', 'name' => 'Siti Staff']);
    $this->supplier = User::factory()->create(['role' => 'supplier', 'name' => 'Abu Supplier']);
});

test('every role can start a conversation with every other role', function (string $from, string $to) {
    $sender = $this->{$from};
    $recipient = $this->{$to};

    $this->actingAs($sender)->post(route('messages.start'), ['user_id' => $recipient->id])
        ->assertRedirect(route('messages.show', Conversation::between($sender, $recipient)));

    expect(Conversation::count())->toBe(1);
})->with([
    'admin to staff' => ['admin', 'staff'],
    'staff to supplier' => ['staff', 'supplier'],
    'supplier to admin' => ['supplier', 'admin'],
]);

test('starting a chat with the same person twice reuses the conversation', function () {
    $this->actingAs($this->staff)->post(route('messages.start'), ['user_id' => $this->supplier->id]);
    $this->actingAs($this->supplier)->post(route('messages.start'), ['user_id' => $this->staff->id]);

    expect(Conversation::count())->toBe(1);
});

test('you cannot message yourself or an account that is not approved', function () {
    $pending = User::factory()->create(['role' => 'supplier', 'approval_status' => 'pending']);

    $this->actingAs($this->staff)->post(route('messages.start'), ['user_id' => $this->staff->id])->assertSessionHasErrors('user_id');
    $this->actingAs($this->staff)->post(route('messages.start'), ['user_id' => $pending->id])->assertSessionHasErrors(['user_id' => 'That person is not available to message.']);

    $this->actingAs($this->staff)->get(route('messages.index'))->assertDontSee('<option value="'.$pending->id.'"', false);
});

test('messages are sent, shown, and polled as json', function () {
    $conversation = Conversation::between($this->staff, $this->supplier);

    $this->actingAs($this->staff)->postJson(route('messages.store', $conversation), ['body' => 'Can you deliver tomorrow?'])
        ->assertCreated()
        ->assertJson(['body' => 'Can you deliver tomorrow?', 'mine' => true]);

    $first = Message::sole();

    $this->actingAs($this->supplier)->getJson(route('messages.poll', [$conversation, 'after' => 0]))
        ->assertOk()
        ->assertJsonPath('messages.0.body', 'Can you deliver tomorrow?')
        ->assertJsonPath('messages.0.mine', false);

    $this->actingAs($this->supplier)->getJson(route('messages.poll', [$conversation, 'after' => $first->id]))
        ->assertJsonCount(0, 'messages');

    $this->actingAs($this->supplier)->get(route('messages.show', $conversation))
        ->assertOk()
        ->assertSee('Siti Staff')
        ->assertSee('Can you deliver tomorrow?');
});

test('unread messages are counted until the conversation is opened', function () {
    $conversation = Conversation::between($this->staff, $this->supplier);
    $conversation->send($this->staff, 'First');
    $conversation->send($this->staff, 'Second');

    expect($this->supplier->unreadMessageCount())->toBe(2)
        ->and($this->staff->unreadMessageCount())->toBe(0);

    $this->actingAs($this->supplier)->get(route('dashboard'))->assertSee('aria-label="2 unread"', false);

    $this->actingAs($this->supplier)->get(route('messages.show', $conversation));

    expect($this->supplier->unreadMessageCount())->toBe(0);
});

test('only the two people in a conversation can see or post to it', function () {
    $conversation = Conversation::between($this->staff, $this->supplier);
    $conversation->send($this->staff, 'Private note');

    $this->actingAs($this->admin)->get(route('messages.show', $conversation))->assertForbidden();
    $this->actingAs($this->admin)->post(route('messages.store', $conversation), ['body' => 'Hi'])->assertForbidden();
    $this->actingAs($this->admin)->getJson(route('messages.poll', $conversation))->assertForbidden();
});

test('an empty message is rejected', function () {
    $conversation = Conversation::between($this->staff, $this->supplier);

    $this->actingAs($this->staff)->postJson(route('messages.store', $conversation), ['body' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['body' => 'Please type a message or attach a photo.']);

    expect(Message::count())->toBe(0);
});

test('guests and unapproved accounts cannot use messages', function () {
    $this->get(route('messages.index'))->assertRedirect(route('login'));

    $pending = User::factory()->create(['role' => 'staff', 'approval_status' => 'pending']);
    $this->actingAs($pending)->get(route('messages.index'))->assertForbidden();
});

test('the conversation list shows the latest message and newest chats first', function () {
    Conversation::between($this->staff, $this->admin)->send($this->admin, 'Older chat');
    $this->travel(1)->minutes();
    Conversation::between($this->staff, $this->supplier)->send($this->supplier, 'Newer chat');

    $this->actingAs($this->staff)->get(route('messages.index'))
        ->assertSeeInOrder(['Abu Supplier', 'Newer chat', 'Aina Admin', 'Older chat']);
});

test('suppliers can only message admins and staff', function () {
    $otherSupplier = User::factory()->create(['role' => 'supplier', 'name' => 'Petal Wholesale']);

    $this->actingAs($this->supplier)->get(route('messages.index'))
        ->assertSee('<option value="'.$this->admin->id.'">', false)
        ->assertSee('<option value="'.$this->staff->id.'">', false)
        ->assertDontSee('<option value="'.$otherSupplier->id.'">', false);

    $this->actingAs($this->supplier)->post(route('messages.start'), ['user_id' => $otherSupplier->id])
        ->assertSessionHasErrors(['user_id' => 'That person is not available to message.']);

    expect(Conversation::count())->toBe(0);
});

test('admins and staff can still message suppliers', function (string $role) {
    $this->actingAs($this->{$role})->get(route('messages.index'))->assertSee('<option value="'.$this->supplier->id.'">', false);
    $this->actingAs($this->{$role})->post(route('messages.start'), ['user_id' => $this->supplier->id])->assertSessionHasNoErrors();
})->with(['admin', 'staff']);

test('a photo can be sent with or without text', function (?string $body) {
    Storage::fake('local');
    $conversation = Conversation::between($this->staff, $this->supplier);

    $response = $this->actingAs($this->staff)->post(route('messages.store', $conversation), array_filter([
        'body' => $body,
        'image' => UploadedFile::fake()->image('bruised-roses.jpg', 800, 600),
    ]), ['Accept' => 'application/json'])->assertCreated();

    $message = Message::sole();
    Storage::disk('local')->assertExists($message->image_path);
    expect($message->body)->toBe($body)
        ->and($response->json('image_url'))->toBe(route('messages.image', [$conversation, $message]));

    $this->actingAs($this->supplier)->get(route('messages.index'))->assertSee($body ?? 'Photo');
})->with([
    'photo only' => [null],
    'photo with text' => ['See the petals'],
]);

test('only the two people in the conversation can open its photos', function () {
    Storage::fake('local');
    $conversation = Conversation::between($this->staff, $this->supplier);
    $this->actingAs($this->staff)->post(route('messages.store', $conversation), ['image' => UploadedFile::fake()->image('roses.png')], ['Accept' => 'application/json']);
    $message = Message::sole();

    $this->actingAs($this->supplier)->get(route('messages.image', [$conversation, $message]))->assertOk();
    $this->actingAs($this->admin)->get(route('messages.image', [$conversation, $message]))->assertForbidden();
    auth()->logout();
    $this->get(route('messages.image', [$conversation, $message]))->assertRedirect(route('login'));
});

test('a photo from another conversation cannot be opened through this one', function () {
    Storage::fake('local');
    $theirs = Conversation::between($this->admin, $this->supplier);
    $this->actingAs($this->admin)->post(route('messages.store', $theirs), ['image' => UploadedFile::fake()->image('private.png')], ['Accept' => 'application/json']);
    $mine = Conversation::between($this->staff, $this->supplier);

    $this->actingAs($this->staff)->get(route('messages.image', [$mine, Message::sole()]))->assertNotFound();
});

test('attachments must be photos of 5 MB or less', function (UploadedFile $file, string $error) {
    Storage::fake('local');
    $conversation = Conversation::between($this->staff, $this->supplier);

    $this->actingAs($this->staff)->postJson(route('messages.store', $conversation), ['image' => $file])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['image' => $error]);

    expect(Message::count())->toBe(0);
})->with([
    'too large' => [fn () => UploadedFile::fake()->image('huge.jpg')->size(6000), 'Photos must be 5 MB or smaller.'],
    'not a photo' => [fn () => UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf'), 'The attachment must be a photo.'],
]);

test('every role sees the floating chat button with its unread count', function (string $role, string $page) {
    Conversation::between($this->{$role}, $role === 'admin' ? $this->staff : $this->admin)
        ->send($role === 'admin' ? $this->staff : $this->admin, 'Hello');

    $this->actingAs($this->{$role})->get(route($page))
        ->assertSee('id="chat-widget-toggle"', false)
        ->assertSee('aria-label="1 unread"', false)
        ->assertSee(route('messages.index', ['widget' => 1]));
})->with([
    'admin' => ['admin', 'admin.dashboard'],
    'staff' => ['staff', 'dashboard'],
    'supplier' => ['supplier', 'dashboard'],
]);

test('the floating button is not shown on the messages page itself', function () {
    $this->actingAs($this->staff)->get(route('messages.index'))->assertDontSee('id="chat-widget-toggle"', false);
});

test('the chat panel shows messages without the sidebar and keeps links inside the panel', function () {
    $conversation = Conversation::between($this->staff, $this->supplier);
    $conversation->send($this->supplier, 'Delivered at 10am');

    $this->actingAs($this->staff)->get(route('messages.index', ['widget' => 1]))
        ->assertOk()
        ->assertDontSee('mobile-sidebar', false)
        ->assertSee(route('messages.show', [$conversation, 'widget' => 1]));

    $this->actingAs($this->staff)->get(route('messages.show', [$conversation, 'widget' => 1]))
        ->assertSee('Delivered at 10am')
        ->assertSee('action="'.route('messages.store', [$conversation, 'widget' => 1]).'"', false);

    $this->actingAs($this->staff)->post(route('messages.start', ['widget' => 1]), ['user_id' => $this->admin->id])
        ->assertRedirect(route('messages.show', [Conversation::between($this->staff, $this->admin), 'widget' => 1]));
});

test('the unread count endpoint feeds the badge', function () {
    Conversation::between($this->staff, $this->supplier)->send($this->supplier, 'One');

    $this->actingAs($this->staff)->getJson(route('messages.unread'))->assertExactJson(['count' => 1]);
    auth()->logout();
    $this->getJson(route('messages.unread'))->assertUnauthorized();
});
