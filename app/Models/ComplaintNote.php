<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ComplaintNoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplaintNote extends Model
{
    /** @use HasFactory<ComplaintNoteFactory> */
    use HasFactory;

    protected $fillable = [
        'complaint_id',
        'user_id',
        'body',
        'is_internal',
    ];

    protected function casts(): array
    {
        return ['is_internal' => 'boolean'];
    }

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
