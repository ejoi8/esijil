<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

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
     * Make factory users administrators by default so panel tests retain full
     * access. Use ->staff() or ->roleless() to override.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            if ($user->roles()->count() === 0) {
                $user->assignRole(Role::findOrCreate(UserRole::Admin->value, 'web'));
            }

            // Join the ambient organization so the user can enter the tenant panel.
            if (Schema::hasTable('organizations') && ($organization = Organization::query()->first()) !== null) {
                $user->organizations()->syncWithoutDetaching([$organization->id]);
            }
        });
    }

    public function staff(): static
    {
        return $this->afterCreating(
            fn (User $user) => $user->syncRoles([Role::findOrCreate(UserRole::Staff->value, 'web')]),
        );
    }

    public function roleless(): static
    {
        return $this->afterCreating(fn (User $user) => $user->syncRoles([]));
    }

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
            'remember_token' => Str::random(10),
        ];
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
