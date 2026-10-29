<?php

namespace App\Enum;

enum ActorChangeStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}