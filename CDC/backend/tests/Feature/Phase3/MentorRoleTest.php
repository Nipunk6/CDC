<?php

namespace Tests\Feature\Phase3;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * P0.2 (N12 groundwork): `mentor` is a fourth user role. A mentor signs in by email like an admin, reaches the
 * role-neutral account routes, and is refused by every admin, company and student route. Mentor pages come in P8.
 */
#[Group('phase3')]
class MentorRoleTest extends TestCase
{
    use RefreshDatabase;

    private function mentor(array $attributes = []): User
    {
        return User::factory()->create(['role' => 'mentor', 'email' => 'mentor@cdc.qa.test', 'password' => Hash::make('Mentor-Pass-9')] + $attributes);
    }

    public function test_a_mentor_account_can_be_stored_and_signs_in_by_email(): void
    {
        $mentor = $this->mentor();
        $this->assertSame('mentor', $mentor->fresh()->role);

        $response = $this->postJson('/api/auth/login', ['email' => 'mentor@cdc.qa.test', 'password' => 'Mentor-Pass-9'])->assertOk();
        $this->assertSame('mentor', $response->json('user.role'));
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_a_mentor_reaches_only_role_neutral_routes(): void
    {
        $token = $this->mentor()->createToken('auth-token')->plainTextToken;
        $as = fn () => $this->withHeader('Authorization', 'Bearer '.$token);
        $this->withoutMiddleware(ThrottleRequests::class); // more than 60 calls as one user follow

        $as()->getJson('/api/auth/user')->assertOk()->assertJsonPath('user.role', 'mentor');
        $as()->getJson('/api/auth/notifications')->assertOk();

        // Every route behind role:admin, role:company or role:student answers 403. Routes with an id in the path are
        // covered by the permission matrices, which have real records to point at (a made-up id would be a 404).
        $checked = 0;
        foreach (Route::getRoutes()->getRoutes() as $route) {
            /** @var RoutingRoute $route */
            $roles = collect($route->gatherMiddleware())->filter(fn ($m) => is_string($m) && str_starts_with($m, 'role:'))->values();
            if ($roles->isEmpty() || str_contains($route->uri(), '{') || $roles->contains(fn (string $m) => in_array('mentor', explode(',', substr($m, 5)), true))) {
                continue;
            }
            $uri = '/'.$route->uri();
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $this->app['auth']->forgetGuards();
                $status = $as()->json($method, $uri)->status();
                $this->assertSame(403, $status, "{$method} {$uri} must refuse a mentor, got {$status}");
                $checked++;
            }
        }
        $this->assertGreaterThan(80, $checked, 'the role-gated routes were really exercised');
    }

    public function test_a_suspended_mentor_is_refused(): void
    {
        $mentor = $this->mentor(['is_active' => false]);
        $this->postJson('/api/auth/login', ['email' => 'mentor@cdc.qa.test', 'password' => 'Mentor-Pass-9'])->assertStatus(403);

        $token = $mentor->createToken('auth-token')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/auth/user')->assertStatus(403);
    }
}
