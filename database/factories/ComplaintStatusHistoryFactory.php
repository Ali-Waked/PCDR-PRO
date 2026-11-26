<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ComplaintStatus;
use App\Models\Complaint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComplaintStatusHistory>
 */
class ComplaintStatusHistoryFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'complaint_id' => Complaint::factory(),
            'from_status' => ComplaintStatus::New->value,
            'to_status' => ComplaintStatus::UnderReview->value,
            'changed_by' => null,
            'note' => null,
        ];
    }
}
