<?php

namespace Tests\Feature;

use App\Livewire\Dashboard\NotificationBell;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationBellTest extends TestCase
{
    use RefreshDatabase;

    public function test_bell_unread_count_reflects_unread_notifications(): void
    {
        $user = User::factory()->create();

        DatabaseNotification::create([
            'id' => Str::uuid(),
            'type' => 'App\Notifications\ApplicationSubmittedNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode([
                'type' => 'application_submitted',
                'tracking_number' => 'VA-2026-001',
                'message' => 'Your application was received.',
            ]),
            'read_at' => null,
        ]);

        Livewire::actingAs($user)
            ->test(NotificationBell::class)
            ->assertSet('unreadCount', 1);
    }

    public function test_bell_unread_count_is_zero_when_all_notifications_read(): void
    {
        $user = User::factory()->create();

        DatabaseNotification::create([
            'id' => Str::uuid(),
            'type' => 'App\Notifications\ApplicationSubmittedNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode([
                'type' => 'application_submitted',
                'tracking_number' => 'VA-2026-002',
                'message' => 'Read notification.',
            ]),
            'read_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(NotificationBell::class)
            ->assertSet('unreadCount', 0);
    }

    public function test_mark_all_as_read_sets_unread_count_to_zero(): void
    {
        $user = User::factory()->create();

        DatabaseNotification::create([
            'id' => Str::uuid(),
            'type' => 'App\Notifications\ApplicationSubmittedNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode([
                'type' => 'application_submitted',
                'tracking_number' => 'VA-2026-003',
                'message' => 'Your application was received.',
            ]),
            'read_at' => null,
        ]);

        Livewire::actingAs($user)
            ->test(NotificationBell::class)
            ->assertSet('unreadCount', 1)
            ->call('markAllAsRead')
            ->assertSet('unreadCount', 0);

        $this->assertDatabaseMissing('notifications', ['read_at' => null]);
    }

    public function test_bell_shows_empty_state_when_user_has_no_notifications(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(NotificationBell::class)
            ->assertSee('No notifications yet');
    }
}
