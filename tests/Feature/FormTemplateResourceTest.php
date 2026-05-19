<?php

namespace Tests\Feature;

use App\Domain\Applications\Models\FormTemplate;
use App\Filament\Resources\FormTemplates\FormTemplateResource;
use App\Filament\Resources\FormTemplates\Pages\EditFormTemplate;
use App\Filament\Resources\FormTemplates\Pages\ListFormTemplates;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FormTemplateResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('super_admin');
    }

    public function test_admin_can_list_form_templates(): void
    {
        $this->actingAs($this->admin)
            ->get(FormTemplateResource::getUrl('index'))
            ->assertSuccessful();
    }

    public function test_draft_template_is_editable(): void
    {
        $template = FormTemplate::factory()->create(['published_at' => null, 'is_active' => false]);

        $this->assertTrue(FormTemplateResource::canEdit($template));
    }

    public function test_published_template_cannot_be_edited(): void
    {
        $template = FormTemplate::factory()->create(['published_at' => now()]);

        $this->assertFalse(FormTemplateResource::canEdit($template));
    }

    public function test_published_template_cannot_be_deleted(): void
    {
        $template = FormTemplate::factory()->create(['published_at' => now()]);

        $this->assertFalse(FormTemplateResource::canDelete($template));
    }

    public function test_draft_template_can_be_deleted(): void
    {
        $template = FormTemplate::factory()->create(['published_at' => null, 'is_active' => false]);

        $this->assertTrue(FormTemplateResource::canDelete($template));
    }

    public function test_publish_action_sets_published_at_and_activates_template(): void
    {
        $template = FormTemplate::factory()->create(['published_at' => null, 'is_active' => false]);

        $this->actingAs($this->admin);

        Livewire::test(EditFormTemplate::class, ['record' => $template->ulid])
            ->callAction('publish')
            ->assertNotified();

        $template->refresh();
        $this->assertNotNull($template->published_at);
        $this->assertTrue($template->is_active);
    }

    public function test_publish_action_archives_previous_active_template_for_same_visa_type(): void
    {
        $activeTemplate = FormTemplate::factory()->create([
            'published_at' => now()->subDay(),
            'is_active' => true,
        ]);
        $newTemplate = FormTemplate::factory()->create([
            'visa_type_id' => $activeTemplate->visa_type_id,
            'published_at' => null,
            'is_active' => false,
        ]);

        $this->actingAs($this->admin);

        Livewire::test(EditFormTemplate::class, ['record' => $newTemplate->ulid])
            ->callAction('publish')
            ->assertNotified();

        $activeTemplate->refresh();
        $newTemplate->refresh();

        $this->assertFalse($activeTemplate->is_active);
        $this->assertTrue($newTemplate->is_active);
    }

    public function test_form_templates_list_renders(): void
    {
        FormTemplate::factory()->count(3)->create();

        $this->actingAs($this->admin);

        Livewire::test(ListFormTemplates::class)
            ->assertCanSeeTableRecords(FormTemplate::all());
    }
}
