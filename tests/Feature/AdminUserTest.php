<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Role guarantees are enforced on the requests themselves, not only by hiding buttons. */
class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    private function form(User $user, array $changes = []): array
    {
        return $changes + ['name' => $user->name, 'email' => $user->email, 'role' => $user->role];
    }

    public function test_an_admin_cannot_change_their_own_role_with_a_direct_request(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put("/admin/users/{$admin->id}", $this->form($admin, ['role' => 'user']))
            ->assertSessionHas('error');

        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_another_admin_cannot_be_deleted_before_being_demoted(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();

        $this->actingAs($admin)->delete("/admin/users/{$other->id}")->assertSessionHas('error');
        $this->assertFalse($other->fresh()->isAnonymized());

        $this->actingAs($admin)->put("/admin/users/{$other->id}", $this->form($other, ['role' => 'user']))->assertSessionHas('success');
        $this->actingAs($admin)->delete("/admin/users/{$other->id}")->assertSessionHas('success');
        $this->assertTrue($other->fresh()->isAnonymized());
    }

    public function test_a_password_change_is_validated_before_anything_is_saved(): void
    {
        $admin = User::factory()->admin()->create();
        $player = User::factory()->create();

        $this->actingAs($admin)->put("/admin/users/{$player->id}", $this->form($player, ['role' => 'verified', 'password' => 'short', 'password_confirmation' => 'short']))
            ->assertSessionHasErrors('password');

        $this->assertSame('user', $player->fresh()->role);
    }

    public function test_a_new_avatar_replaces_the_old_file(): void
    {
        Storage::fake('public');
        $player = User::factory()->create();
        $profile = ['name' => $player->name, 'gender' => 'male', 'date_of_birth' => '1990-05-15'];

        $this->actingAs($player)->put('/profile', $profile + ['avatar' => UploadedFile::fake()->image('one.jpg')]);
        $first = $player->fresh()->avatar;
        Storage::disk('public')->assertExists($first);

        $this->actingAs($player)->put('/profile', $profile + ['avatar' => UploadedFile::fake()->image('two.jpg')]);
        $second = $player->fresh()->avatar;

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertExists($second);
        Storage::disk('public')->assertMissing($first);
    }
}
