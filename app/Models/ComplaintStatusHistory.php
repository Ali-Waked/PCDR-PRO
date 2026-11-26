<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ComplaintStatusHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplaintStatusHistory extends Model
{
    /** @use HasFactory<ComplaintStatusHistoryFactory> */
    use HasFactory;

    protected $fillable = [
        'complaint_id',
        'from_status',
        'to_status',
        'changed_by',
        'note',
    ];

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
