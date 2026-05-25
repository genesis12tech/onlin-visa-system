<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Livewire\Notifications\NotificationsPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class NotificationsPageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'applicant', 'guard_name' => 'web']);

        $country = Country::factory()->create();
        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $this->user->assignRole('applicant');
        ApplicantProfile::factory()->create([
            'user_id' => $this->user->id,
            'nationality_id' => $country->id,
            'country_of_residence_id' => $country->id,
        ]);
    }

    private function createNotification(User $user, string $type, bool $read = false): DatabaseNotification
    {
        return DatabaseNotification::create([
            'id' => Str::uuid()->toString(),
            'type' => 'App\Notifications\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => [
                'type' => $type,
                'message' => "Notification: {$type}",
                'tracking_number' => 'VA-2026-TEST',
            ],
            'read_at' => $read ? now() : null,
        ]);
    }

    public function test_notifications_page_requires_authentication(): void
    {
        $this->get(route('notifications'))->assertRedirect(route('login'));
    }

    public function test_authenticated_applicant_can_access_notifications_page(): void
    {
        $this->actingAs($this->user)->get(route('notifications'))->assertOk();
    }

    public function test_empty_state_shown_when_no_notifications(): void
    {
        Livewire::actingAs($this->user)
            ->test(NotificationsPage::class)
            ->assertSee('No notifications yet');
    }

    public function test_unread_notification_shows_new_badge(): void
    {
        $this->createNotification($this->user, 'application_approved', false);

        Livewire::actingAs($this->user)
            ->test(NotificationsPage::class)
            ->assertSee('NEW');
    }

    public function test_read_notification_does_not_show_new_badge(): void
    {
        $this->createNotification($this->user, 'application_approved', true);

        Livewire::actingAs($this->user)
            ->test(NotificationsPage::class)
            ->assertDontSee('NEW');
    }

    public function test_mark_all_read_updates_unread_count_to_zero(): void
    {
        $this->createNotification($this->user, 'application_approved', false);
        $this->createNotification($this->user, 'payment_succeeded', false);

        Livewire::actingAs($this->user)
            ->test(NotificationsPage::class)
            ->assertSet('unreadCount', 2)
            ->call('markAllRead')
            ->assertSet('unreadCount', 0);

        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $this->user->id,
            'read_at' => null,
        ]);
    }

    public function test_notification_message_is_visible(): void
    {
        $this->createNotification($this->user, 'application_approved', false);

        Livewire::actingAs($this->user)
            ->test(NotificationsPage::class)
            ->assertSee('Notification: application_approved');
    }

    public function test_mark_all_read_button_only_shown_when_unread_exist(): void
    {
        $this->createNotification($this->user, 'application_approved', true);

        Livewire::actingAs($this->user)
            ->test(NotificationsPage::class)
            ->assertDontSee('Mark all as read');
    }

    public function test_other_users_notifications_are_not_visible(): void
    {
        $otherUser = User::factory()->create();
        $this->createNotification($otherUser, 'application_approved', false);

        Livewire::actingAs($this->user)
            ->test(NotificationsPage::class)
            ->assertSee('No notifications yet');
    }
}
