<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ComplaintAttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplaintAttachment extends Model
{
    /** @use HasFactory<ComplaintAttachmentFactory> */
    use HasFactory;

    protected $fillable = [
        'complaint_id',
        'media_id',
        'label',
    ];

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
