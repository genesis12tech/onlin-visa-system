<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Livewire\Dashboard\NotificationBell;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ApplicantPanelShellTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::create(['name' => 'applicant', 'guard_name' => 'web']);

        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $country = Country::factory()->create();
        $this->user->assignRole('applicant');

        ApplicantProfile::factory()->create([
            'user_id' => $this->user->id,
            'nationality_id' => $country->id,
            'country_of_residence_id' => $country->id,
        ]);
    }

    // -----------------------------------------------------------------------
    // HTTP route tests
    // -----------------------------------------------------------------------

    public function test_dashboard_route_is_accessible(): void
    {
        $this->actingAs($this->user)->get('/dashboard')->assertOk();
    }

    public function test_applications_index_route_is_accessible(): void
    {
        $this->actingAs($this->user)->get('/applications')->assertOk();
    }

    public function test_documents_route_is_accessible(): void
    {
        $this->actingAs($this->user)->get('/documents')->assertOk();
    }

    public function test_payments_route_is_accessible(): void
    {
        $this->actingAs($this->user)->get('/payments')->assertOk();
    }

    public function test_notifications_route_is_accessible(): void
    {
        $this->actingAs($this->user)->get('/notifications')->assertOk();
    }

    public function test_profile_route_is_accessible(): void
    {
        $this->actingAs($this->user)->get('/profile')->assertOk();
    }

    // -----------------------------------------------------------------------
    // Unauthenticated redirect
    // -----------------------------------------------------------------------

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    // -----------------------------------------------------------------------
    // Sidebar content
    // -----------------------------------------------------------------------

    public function test_sidebar_shows_brand_name_and_all_nav_labels(): void
    {
        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('VisaPortal')
            ->assertSee('Dashboard')
            ->assertSee('My Applications')
            ->assertSee('New Application')
            ->assertSee('Documents')
            ->assertSee('Payments')
            ->assertSee('Track Status')
            ->assertSee('Notifications')
            ->assertSee('My Profile');
    }

    // -----------------------------------------------------------------------
    // NotificationBell — unread dot shown
    // -----------------------------------------------------------------------

    public function test_notification_bell_shows_amber_dot_when_unread_notifications_exist(): void
    {
        DatabaseNotification::create([
            'id' => Str::uuid()->toString(),
            'type' => 'App\Notifications\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
            'data' => json_encode(['message' => 'You have a new update.', 'type' => 'application_approved']),
            'read_at' => null,
        ]);

        Livewire::actingAs($this->user)
            ->test(NotificationBell::class)
            ->assertSet('unreadCount', 1)
            ->assertSeeHtml('background:var(--portal-amber)');
    }

    // -----------------------------------------------------------------------
    // NotificationBell — no dot when no unread notifications
    // -----------------------------------------------------------------------

    public function test_notification_bell_hides_amber_dot_when_no_unread_notifications(): void
    {
        Livewire::actingAs($this->user)
            ->test(NotificationBell::class)
            ->assertSet('unreadCount', 0)
            ->assertDontSeeHtml('background:var(--portal-amber)');
    }

    // -----------------------------------------------------------------------
    // NotificationBell — markAllAsRead action
    // -----------------------------------------------------------------------

    public function test_mark_all_as_read_sets_unread_count_to_zero(): void
    {
        DatabaseNotification::create([
            'id' => Str::uuid()->toString(),
            'type' => 'App\Notifications\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
            'data' => json_encode(['message' => 'Another update.', 'type' => 'additional_info_requested']),
            'read_at' => null,
        ]);

        Livewire::actingAs($this->user)
            ->test(NotificationBell::class)
            ->assertSet('unreadCount', 1)
            ->call('markAllAsRead')
            ->assertSet('unreadCount', 0);
    }
}
