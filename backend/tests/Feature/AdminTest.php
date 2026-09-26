<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_read_site_settings(): void
    {
        $this->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('data.site_name', 'Base')
            ->assertJsonPath('data.contact_address', '')
            ->assertJsonPath('data.contact_phone', '')
            ->assertJsonPath('data.contact_email', '')
            ->assertJsonMissingPath('data.footer_text');
    }

    public function test_admin_can_update_site_settings(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->putJson('/api/admin/settings', [
            'site_name' => 'Kunszt',
            'contact_address' => "1234 Budapest\nPélda utca 1.",
            'contact_phone' => '+36 30 123 4567',
            'contact_email' => 'hello@example.com',
        ])->assertOk()
            ->assertJsonPath('data.site_name', 'Kunszt')
            ->assertJsonPath('data.contact_address', "1234 Budapest\nPélda utca 1.")
            ->assertJsonPath('data.contact_phone', '+36 30 123 4567')
            ->assertJsonPath('data.contact_email', 'hello@example.com');

        $this->getJson('/api/settings')
            ->assertJsonPath('data.site_name', 'Kunszt')
            ->assertJsonPath('data.contact_email', 'hello@example.com');

        $this->putJson('/api/admin/settings', [
            'site_name' => 'Kunszt',
        ])->assertOk()
            ->assertJsonPath('data.contact_email', 'hello@example.com');
    }

    public function test_contact_email_must_be_valid(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->putJson('/api/admin/settings', [
            'site_name' => 'Base',
            'contact_email' => 'nem-email',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('contact_email');
    }

    public function test_user_cannot_update_site_settings(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/admin/settings', [
            'site_name' => 'Más',
        ])->assertForbidden();
    }

    public function test_site_name_is_required(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->putJson('/api/admin/settings', [
            'site_name' => '',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('site_name');
    }

    public function test_admin_can_change_a_users_role(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/users/'.$user->id, [
            'role' => 'admin',
        ])->assertOk()
            ->assertJsonPath('data.role', 'admin');

        $this->assertTrue($user->fresh()->isAdmin());
    }

    public function test_another_admin_can_be_demoted_while_one_admin_remains(): void
    {
        $admin = User::factory()->admin()->create();
        $second = User::factory()->admin()->create();

        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/users/'.$second->id, [
            'role' => 'user',
        ])->assertOk()
            ->assertJsonPath('data.role', 'user');

        $this->assertFalse($second->fresh()->isAdmin());
        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_last_admin_role_cannot_be_removed(): void
    {
        $admin = User::factory()->admin()->create();

        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/users/'.$admin->id, [
            'role' => 'user',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_user_cannot_change_roles(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->patchJson('/api/admin/users/'.$user->id, [
            'role' => 'admin',
        ])->assertForbidden();
    }
}
