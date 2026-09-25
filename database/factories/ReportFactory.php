<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Report>
 */
class ReportFactory extends Factory
{
    protected $model = Report::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reporter_id' => User::factory(),
            'reportable_type' => (new Post())->getMorphClass(),
            'reportable_id' => Post::factory(),
            'reason' => fake()->randomElement(ReportReason::values()),
            'detail' => fake()->sentence(),
            'status' => ReportStatus::PENDING,
            'handled_by' => null,
            'handled_at' => null,
            'resolution_note' => null,
        ];
    }

    /**
     * A report that a moderator already closed.
     */
    public function handled(ReportStatus $status = ReportStatus::RESOLVED): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => $status,
            'handled_by' => User::factory(),
            'handled_at' => now(),
            'resolution_note' => '已处理',
        ]);
    }
}
