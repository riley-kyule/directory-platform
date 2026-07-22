<?php

namespace App\Enums;

enum ProfileStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Active = 'active';
    case Expired = 'expired';
    case Deactivated = 'deactivated';
    case Banned = 'banned';
    case Rejected = 'rejected';

    public function isPublic(): bool
    {
        return $this === self::Active;
    }
}
