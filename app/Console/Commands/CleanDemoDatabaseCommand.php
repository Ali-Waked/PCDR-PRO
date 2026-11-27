<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\RbacService;
use App\Support\DemoSeedManifest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

#[Signature('pcdr:demo:cleanup {--dry-run : Show the complete deletion summary without deleting anything} {--force : Confirm deletion of only the preview-owned records and files shown in the summary}')]
#[Description('Delete only internally tracked preview data after showing a summary and requiring confirmation')]
final class CleanDemoDatabaseCommand extends Command
{
    public function handle(): int
    {
        try {
            DemoSeedManifest::assertCleanable();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $manifest = app(DemoSeedManifest::class);
        $rows = $manifest->rows();
        $pivots = $manifest->pivots();
        $files = $manifest->files();
        $settings = (int) DB::table('settings')
            ->where('group', DemoSeedManifest::GROUP)
            ->count();

        $this->renderSummary($rows, $pivots, $files, $settings);

        if ($rows === [] && $pivots === [] && $files === []) {
            $this->info('No preview-owned records or files are registered; nothing was changed.');

            return self::SUCCESS;
        }

        if ((bool) $this->option('dry-run')) {
            $this->info('Dry run complete. No records or files were deleted.');

            return self::SUCCESS;
        }

        if (! (bool) $this->option('force')
            && ! $this->confirm('Delete only the preview-owned records and files shown above?', false)) {
            $this->warn('Cleanup cancelled; nothing was changed.');

            return self::SUCCESS;
        }

        $deletedRows = 0;
        $deletedPivots = 0;
        $deletedFiles = 0;
        $skippedFiles = 0;

        try {
            DB::transaction(function () use ($manifest, $rows, $pivots, &$deletedRows, &$deletedPivots): void {
                foreach (array_reverse($pivots) as $pivot) {
                    $query = DB::table($pivot['table']);

                    foreach ($pivot['match'] as $column => $value) {
                        $query->where($column, $value);
                    }

                    $deletedPivots += $query->delete();
                }

                foreach (array_reverse($rows) as $row) {
                    $deletedRows += DB::table($row['table'])
                        ->where($row['id_column'], $row['id'])
                        ->delete();
                }

                $manifest->clearManifest();
            });
        } catch (Throwable $exception) {
            $this->error('Database cleanup failed; the transaction was rolled back: '.$exception->getMessage());

            return self::FAILURE;
        }

        foreach ($files as $file) {
            if ($this->deleteTrackedFile($file)) {
                $deletedFiles++;
            } else {
                $skippedFiles++;
            }
        }

        $this->invalidateRbacCache();

        $this->info("Cleanup complete: {$deletedRows} rows, {$deletedPivots} pivot rows and {$deletedFiles} files deleted.");
        $this->line("{$settings} internal ownership markers removed.");

        if ($skippedFiles > 0) {
            $this->warn("{$skippedFiles} files were preserved because their checksum or existence could not be verified.");
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<array{table: string, id_column: string, id: string, natural_key: string}>  $rows
     * @param  list<array{table: string, match: array<string, mixed>}>  $pivots
     * @param  list<array{disk: string, path: string, checksum: string, natural_key: string}>  $files
     */
    private function renderSummary(array $rows, array $pivots, array $files, int $settings): void
    {
        $this->info('Preview cleanup summary (deletes only internally tracked data)');

        $lines = [];

        foreach ($this->countBy($rows, static fn (array $row): string => $row['table']) as $table => $count) {
            $lines[] = ['Row', (string) $table, (string) $count];
        }

        foreach ($this->countBy($pivots, static fn (array $pivot): string => $pivot['table']) as $table => $count) {
            $lines[] = ['Pivot', (string) $table, (string) $count];
        }

        foreach ($this->countBy($files, static fn (array $file): string => $file['disk']) as $disk => $count) {
            $lines[] = ['File', (string) $disk, (string) $count];
        }

        $lines[] = ['Internal marker', DemoSeedManifest::GROUP, (string) $settings];

        $this->table(['Scope', 'Table or disk', 'Count'], $lines);
        $this->line('Non-preview records, administrator edits and untracked files are never deleted.');
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, int>
     */
    private function countBy(array $items, callable $keyFor): array
    {
        $counts = [];

        foreach ($items as $item) {
            $key = (string) $keyFor($item);
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }

    /** @param  array{disk: string, path: string, checksum: string, natural_key: string}  $file */
    private function deleteTrackedFile(array $file): bool
    {
        try {
            $disk = Storage::disk($file['disk']);

            if (! $disk->exists($file['path'])) {
                return false;
            }

            $contents = $disk->get($file['path']);

            if (! is_string($contents) || hash('sha256', $contents) !== $file['checksum']) {
                return false;
            }

            return $disk->delete($file['path']);
        } catch (Throwable) {
            return false;
        }
    }

    private function invalidateRbacCache(): void
    {
        $rbac = app(RbacService::class);

        if (method_exists($rbac, 'invalidate')) {
            $rbac->invalidate();
        }
    }
}
