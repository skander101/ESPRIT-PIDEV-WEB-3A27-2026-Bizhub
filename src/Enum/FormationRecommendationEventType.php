<?php

namespace App\Enum;

enum FormationRecommendationEventType: string
{
    case IMPRESSION = 'impression';
    case CLICK = 'click';
    case ENROLL = 'enroll';
}
