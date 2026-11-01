<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

// فرح: صفحة "المستخدمين" باللوحة: بحث وحذف يحرر البريد، وما بتحذف حسابات الإدارة أو حساب عليه حجز قائم.
class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role = 'system.admin'): User
    {
        Role::findOrCreate($role, 'web');
        $admin = User::factory()->create(['user_type' => 'admin', 'status' => 'active']);
        $admin->assignRole($role);

        return $admin;
    }

    public function test_lists_and_searches_customers_and_providers(): void
    {
        $admin = $this->admin();
        User::factory()->create(['name' => 'Sara Customer', 'email' => 'sara@test.com', 'user_type' => 'customer']);
        User::factory()->create(['name' => 'Lavender', 'email' => 'lav@test.com', 'user_type' => 'provider']);

        $this->actingAs($admin)->get(route('admin.users'))->assertOk()
            ->assertSee('sara@test.com')->assertSee('lav@test.com')->assertDontSee($admin->email);
        $this->actingAs($admin)->get(route('admin.users', ['search' => 'sara']))->assertOk()
            ->assertSee('sara@test.com')->assertDontSee('lav@test.com');
        $this->actingAs($admin)->get(route('admin.users', ['type' => 'provider']))->assertOk()
            ->assertSee('lav@test.com')->assertDontSee('sara@test.com');
    }

    public function test_deleting_frees_the_email_and_is_audited(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['email' => 'again@test.com', 'user_type' => 'customer']);

        $this->actingAs($admin)->from(route('admin.users'))
            ->delete(route('admin.users.delete', $user))
            ->assertRedirect(route('admin.users'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'user_deleted', 'resource_id' => $user->id]);

        auth()->logout();
        $this->postJson('/api/register', [
            'name' => 'Again',
            'email' => 'again@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();
    }

    public function test_active_bookings_and_admin_accounts_are_protected(): void
    {
        $admin = $this->admin();
        $busy = User::factory()->create(['user_type' => 'customer']);
        Booking::factory()->create(['user_id' => $busy->id, 'status' => 'pending']);
        $otherAdmin = $this->admin();

        $this->actingAs($admin)->from(route('admin.users'))
            ->delete(route('admin.users.delete', $busy))
            ->assertSessionHasErrors('user');
        $this->assertNotSoftDeleted('users', ['id' => $busy->id]);

        $this->actingAs($admin)->delete(route('admin.users.delete', $otherAdmin))->assertForbidden();
    }

    public function test_only_system_admins_can_open_it(): void
    {
        $moderator = $this->admin('ops.moderator');

        $this->actingAs($moderator)->get(route('admin.users'))->assertForbidden();
    }
}
