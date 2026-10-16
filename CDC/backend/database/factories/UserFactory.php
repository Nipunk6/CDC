<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => 'company',
            'is_active' => true,
            'company_id' => null,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Staff roles: a non-super admin gets the built-in Full Access role and a completed profile unless the test
     * says otherwise (pass `staff_role_id` / `profile_completed_at`, or use withoutStaffRole()).
     */
    public function configure(): static
    {
        return $this->afterMaking(function (User $user) {
            if ($user->role !== 'admin' || $user->is_super_admin) {
                return;
            }
            if (! array_key_exists('staff_role_id', $user->getAttributes())) {
                $user->staff_role_id = \App\Models\StaffRole::fullAccess()->id;
            }
            if (! array_key_exists('profile_completed_at', $user->getAttributes())) {
                $user->profile_completed_at = now();
            }
        });
    }

    /** An admin with no staff role (can sign in, cannot open any admin page). */
    public function withoutStaffRole(): static
    {
        return $this->state(fn () => ['staff_role_id' => null]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
