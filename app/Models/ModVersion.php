<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ModVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'mod_id',
        'submitted_by',
        'version_number',
        'changelog',
        'file_path',
        'download_url',
        'file_size',
        'status',
        'is_current',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'approved_by',
    ];

    protected $casts = [
        'is_current' => 'boolean',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'file_size' => 'decimal:2',
    ];

    public function mod(): BelongsTo
    {
        return $this->belongsTo(Mod::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getFileUrlAttribute(): ?string
    {
        if ($this->download_url) {
            return $this->download_url;
        }

        if (!$this->file_path) {
            return null;
        }

        if (Str::startsWith($this->file_path, ['http://', 'https://'])) {
            return $this->file_path;
        }

        return Storage::disk('public')->url($this->file_path);
    }

    public function getFileSizeLabelAttribute(): ?string
    {
        return $this->file_size ? number_format((float) $this->file_size, 2) . ' MB' : null;
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }
}
