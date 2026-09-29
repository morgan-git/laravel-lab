<?php

use App\Models\User;
use App\Models\WebhookRequest;

/**
 * A WebhookRequest row shaped like a real Discord slash command
 * interaction. All IDs and the token are fake.
 */
function makeDiscordWebhookRequest(array $overrides = []): WebhookRequest
{
    return WebhookRequest::create(array_merge([
        'provider' => 'discord',
        'requester_id' => '1000000000000000001',
        'requester_type' => 'user',
        'action' => 'foodporn',
        'status' => 'success',
        'payload_in' => [
            'application_id' => '1000000000000000002',
            'channel_id' => '1000000000000000003',
            'guild_id' => '1000000000000000004',
            'data' => [
                'id' => '1000000000000000005',
                'name' => 'foodporn',
                'type' => 1,
            ],
            'member' => [
                'user' => [
                    'id' => '1000000000000000001',
                    'global_name' => 'TestUser',
                    'username' => 'test_user',
                ],
            ],
            'token' => 'fake-interaction-token',
            'type' => 2,
            'version' => 1,
        ],
        'payload_out' => [
            'embeds' => [
                [
                    'title' => 'Test post title',
                    'url' => 'https://example.com/post',
                    'image' => ['url' => 'https://example.com/image.jpg'],
                ],
            ],
        ],
    ], $overrides));
}

it('shows webhook requests to an admin', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    makeDiscordWebhookRequest(['provider' => 'discord']);
    makeDiscordWebhookRequest(['provider' => 'bluesky', 'status' => 'error']);

    $this->actingAs($admin)
        ->get(route('admin.webhook-requests.index'))
        ->assertOk()
        ->assertSee('discord')
        ->assertSee('bluesky')
        ->assertViewHas('requests', fn ($requests) => $requests->count() === 2);
});

it('passes both payloads through to the page', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    makeDiscordWebhookRequest();

    $this->actingAs($admin)
        ->get(route('admin.webhook-requests.index'))
        ->assertOk()
        ->assertSee('Test post title')
        ->assertSee('fake-interaction-token');
});

it('lists the newest requests first', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $older = makeDiscordWebhookRequest(['created_at' => now()->subHour()]);
    $newer = makeDiscordWebhookRequest(['created_at' => now()]);

    $this->actingAs($admin)
        ->get(route('admin.webhook-requests.index'))
        ->assertViewHas('requests', fn ($requests) => $requests->first()->id === $newer->id
            && $requests->last()->id === $older->id);
});

it('returns 404 for non-admin users', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->get(route('admin.webhook-requests.index'))
        ->assertNotFound();
});

it('redirects guests to login', function () {
    $this->get(route('admin.webhook-requests.index'))
        ->assertRedirect(route('login'));
});
