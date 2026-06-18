<?php

namespace Database\Factories;

use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldScope;
use App\Enums\CustomFieldType;
use App\Models\CustomField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomField>
 */
class CustomFieldFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity' => CustomFieldEntity::Participant->value,
            'key' => fake()->unique()->lexify('field_??????'),
            'label' => ucfirst(fake()->words(2, true)),
            'type' => CustomFieldType::Text->value,
            'options' => null,
            'required' => false,
            'scope' => CustomFieldScope::Admin->value,
            'help_text' => null,
            'cert_var' => null,
            'sort' => 0,
            'active' => true,
        ];
    }

    public function forEntity(CustomFieldEntity|string $entity): static
    {
        return $this->state(fn (): array => [
            'entity' => $entity instanceof CustomFieldEntity ? $entity->value : $entity,
        ]);
    }

    public function publicForm(): static
    {
        return $this->state(fn (): array => ['scope' => CustomFieldScope::PublicForm->value]);
    }

    public function required(): static
    {
        return $this->state(fn (): array => ['required' => true]);
    }

    /**
     * @param  array<string, string>  $options
     */
    public function select(array $options): static
    {
        return $this->state(fn (): array => [
            'type' => CustomFieldType::Select->value,
            'options' => $options,
        ]);
    }
}
