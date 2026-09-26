<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PublicPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_send_a_contact_message(): void
    {
        $this->postJson('/api/contact', [
            'name' => 'Norbert',
            'email' => 'norbert@example.com',
            'message' => 'Szeretnék érdeklődni.',
        ])->assertCreated()
            ->assertJsonPath('message', 'Köszönjük, az üzenetet megkaptuk.');

        $this->assertDatabaseHas('contact_messages', [
            'email' => 'norbert@example.com',
        ]);
    }

    public function test_short_contact_message_is_rejected(): void
    {
        $this->postJson('/api/contact', [
            'name' => 'Norbert',
            'email' => 'norbert@example.com',
            'message' => 'Rövid',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('message');
    }

    public function test_user_cannot_list_contact_messages(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/admin/messages')->assertForbidden();
    }

    public function test_admin_can_read_and_delete_a_contact_message(): void
    {
        $message = ContactMessage::query()->create([
            'name' => 'Norbert',
            'email' => 'norbert@example.com',
            'message' => 'Szeretnék érdeklődni.',
        ]);

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/admin/messages')
            ->assertOk()
            ->assertJsonPath('data.0.email', 'norbert@example.com')
            ->assertJsonPath('data.0.read_at', null);

        $read = $this->patchJson('/api/admin/messages/'.$message->id)->assertOk();
        $this->assertIsString($read->json('data.read_at'));

        $this->assertNotNull($message->fresh()->read_at);

        $this->deleteJson('/api/admin/messages/'.$message->id)->assertNoContent();
        $this->assertDatabaseMissing('contact_messages', ['id' => $message->id]);
    }
}
