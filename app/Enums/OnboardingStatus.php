<?php

namespace App\Enums;

enum OnboardingStatus: string
{
    case Registered = 'registered';
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Completed = 'completed';
    case Abandoned = 'abandoned';
}
