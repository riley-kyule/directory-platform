<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class VerificationCheck extends Model
{
    public const TYPES = [
        'adult_age' => 'Adult age assurance',
        'identity' => 'Identity match',
        'publishing_rights' => 'Publishing rights and depicted-person consent',
        'agency_authorization' => 'Agency authorization',
    ];

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(fn (VerificationCheck $check) => $check->public_id ??= (string) Str::uuid());
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function label(): string
    {
        return self::TYPES[$this->check_type] ?? str($this->check_type)->headline()->toString();
    }

    public function isCurrentVerified(): bool
    {
        return $this->status === 'verified'
            && (! $this->expires_at || $this->expires_at->isFuture());
    }

    protected function casts(): array
    {
        return [
            'evidence_reference' => 'encrypted',
            'notes' => 'encrypted',
            'checked_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
