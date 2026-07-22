<?php

namespace App\Enums;

enum PackageRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Changed = 'changed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}
