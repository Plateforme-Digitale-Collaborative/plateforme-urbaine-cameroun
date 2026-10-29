<?php

namespace App\Enum;

enum ActorChangeOperation: string
{
    case CREATE = 'create';
    case UPDATE = 'update';
}