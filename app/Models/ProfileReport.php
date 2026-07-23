<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ProfileReport extends Model
{
    public const CATEGORIES = [
        'suspected_minor' => 'Suspected minor',
        'non_consensual_content' => 'Non-consensual content',
        'impersonation' => 'Impersonation',
        'threat_or_coercion' => 'Threat or coercion',
        'fraud' => 'Fraud or scam',
        'copyright' => 'Copyright concern',
        'inaccurate_listing' => 'Inaccurate listing',
        'other' => 'Other concern',
    ];

    public const URGENT_CATEGORIES = [
        'suspected_minor',
        'non_consensual_content',
        'threat_or_coercion',
    ];

    protected $table = 'reports';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(fn (ProfileReport $report) => $report->public_id ??= (string) Str::uuid());
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ModerationAction::class, 'report_id');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? str($this->category)->headline()->toString();
    }

    protected function casts(): array
    {
        return [
            'reporter_email' => 'encrypted',
            'resolved_at' => 'datetime',
        ];
    }
}
