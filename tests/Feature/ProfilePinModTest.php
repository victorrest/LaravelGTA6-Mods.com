<?php

namespace Tests\Feature;

use App\Models\Mod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfilePinModTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_pin_their_own_mod(): void
    {
        $user = User::factory()->create();
        $mod = Mod::factory()->for($user)->create();

        $response = $this->actingAs($user)->postJson(route('profile.mod.pin', $mod->id));

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'isPinned' => true,
            ]);

        $this->assertSame($mod->id, $user->fresh()->pinned_mod_id);
    }

    public function test_user_cannot_pin_mod_they_do_not_own(): void
    {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        $this->actingAs($user)
            ->postJson(route('profile.mod.pin', $mod->id))
            ->assertStatus(403);

        $this->assertNull($user->fresh()->pinned_mod_id);
    }

    public function test_pin_request_requires_authentication(): void
    {
        $mod = Mod::factory()->create();

        $this->post(route('profile.mod.pin', $mod->id))
            ->assertRedirect(route('login'));
    }

    public function test_pinning_a_new_mod_replaces_previous_pin(): void
    {
        $user = User::factory()->create();
        $firstMod = Mod::factory()->for($user)->create();
        $secondMod = Mod::factory()->for($user)->create();

        $user->forceFill(['pinned_mod_id' => $firstMod->id])->save();

        $this->actingAs($user)
            ->postJson(route('profile.mod.pin', $secondMod->id))
            ->assertOk();

        $this->assertSame($secondMod->id, $user->fresh()->pinned_mod_id);
    }

    public function test_user_can_unpin_mod(): void
    {
        $user = User::factory()->create();
        $mod = Mod::factory()->for($user)->create();

        $user->forceFill(['pinned_mod_id' => $mod->id])->save();

        $response = $this->actingAs($user)->deleteJson(route('profile.mod.unpin'));

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'isPinned' => false,
            ]);

        $this->assertNull($user->fresh()->pinned_mod_id);
    }
}
