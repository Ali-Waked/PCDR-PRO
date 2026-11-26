<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ComplaintReferenceSequence;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Allocates human-readable references from a database-backed yearly sequence.
 * The sequence row is locked inside a transaction so concurrent submissions
 * cannot receive the same number.
 */
final class ComplaintReferenceService
{
    public function next(?string $year = null): string
    {
        $year ??= now()->format('Y');
        $prefix = (string) config('complaints.reference.prefix', 'PCDR-CMP');
        $padding = max(1, min(12, (int) config('complaints.reference.padding', 6)));

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $value = DB::transaction(function () use ($year): int {
                    /** @var ComplaintReferenceSequence|null $sequence */
                    $sequence = ComplaintReferenceSequence::query()
                        ->where('year', $year)
                        ->lockForUpdate()
                        ->first();

                    if ($sequence === null) {
                        $sequence = ComplaintReferenceSequence::create([
                            'year' => $year,
                            'next_value' => 1,
                        ]);
                    }

                    $value = (int) $sequence->next_value;
                    $sequence->forceFill(['next_value' => $value + 1])->save();

                    return $value;
                });

                return sprintf('%s-%s-%s', $prefix, $year, str_pad((string) $value, $padding, '0', STR_PAD_LEFT));
            } catch (UniqueConstraintViolationException) {
                // Two requests can race to create the year row. Retry against
                // the row committed by the winning request.
                continue;
            }
        }

        throw new RuntimeException('Unable to allocate a complaint reference.');
    }
}
