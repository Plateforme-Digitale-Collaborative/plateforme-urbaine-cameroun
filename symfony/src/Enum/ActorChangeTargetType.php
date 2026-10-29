<?php

namespace App\Enum;

enum ActorChangeTargetType: string
{
    case ACTOR = 'actor';
    case PROJECT = 'project';
    case RESOURCE = 'resource';
}