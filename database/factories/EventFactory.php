<?php

namespace Database\Factories;

use App\Enums\CertificateType;
use App\Enums\EventStatus;
use App\Models\CertificateTemplate;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = Carbon::instance(fake()->dateTimeBetween('+1 day', '+3 months'));
        $endsAt = (clone $startsAt)->addHours(fake()->numberBetween(2, 8));
        $certificateType = fake()->randomElement(CertificateType::cases());

        return [
            'legacy_id' => null,
            'title' => fake()->sentence(6),
            'description' => fake()->optional()->paragraph(),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'start_time_text' => $startsAt->format('g:i A'),
            'end_time_text' => $endsAt->format('g:i A'),
            'venue' => fake()->address(),
            'organizer_name' => 'PUSPANITA Kebangsaan',
            'registration_open' => false,
            'status' => fake()->randomElement(EventStatus::cases()),
            'certificate_type' => $certificateType,
            'template_key' => $certificateType->templateKey(),
            'certificate_template_id' => CertificateTemplate::factory()->state([
                'type' => $certificateType,
            ]),
            'created_by' => User::factory(),
        ];
    }
}
