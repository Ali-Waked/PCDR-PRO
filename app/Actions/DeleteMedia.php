<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\MediaInUseException;
use App\Models\Media;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

final class DeleteMedia
{
    public function handle(Media $media): void
    {
        if ($this->isReferenced($media)) {
            throw new MediaInUseException($media->id);
        }

        DB::transaction(function () use ($media): void {
            Storage::disk($media->disk)->delete($media->path);
            $media->translations()->delete();
            $media->forceDelete();
        });
    }

    private function isReferenced(Media $media): bool
    {
        foreach (['page_media', 'pillar_media', 'project_media', 'article_media', 'publication_media'] as $pivot) {
            if (Schema::hasTable($pivot) && DB::table($pivot)->where('media_id', $media->id)->exists()) {
                return true;
            }
        }

        $columns = [
            'strategic_pillars' => ['featured_image_id'],
            'programs' => ['featured_image_id'],
            'projects' => ['featured_image_id'],
            'articles' => ['cover_media_id'],
            'publications' => ['document_media_id', 'cover_media_id'],
            'domain_transitions' => ['evidence_media_id'],
            'document_links' => ['media_id'],
            'distribution_records' => ['evidence_media_id'],
            'volunteer_trainings' => ['evidence_media_id'],
            'committee_meetings' => ['evidence_media_id'],
            'partner_agreements' => ['document_media_id'],
            'grant_proposals' => ['submission_evidence_media_id'],
            'grant_awards' => ['agreement_media_id', 'evidence_media_id'],
            'indicator_measurements' => ['evidence_media_id'],
            'evaluations' => ['evidence_media_id'],
            'assessments' => ['evidence_media_id'],
            'document_reviews' => ['media_id'],
            'directory_partners' => ['logo_media_id'],
            'homepage_section_items' => ['media_id'],
        ];

        foreach ($columns as $table => $fields) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($fields as $field) {
                if (Schema::hasColumn($table, $field) && DB::table($table)->where($field, $media->id)->exists()) {
                    return true;
                }
            }
        }

        return false;
    }
}
