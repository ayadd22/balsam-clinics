<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case BOOKED = 'booked';
    case ARRIVED = 'arrived';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case NO_SHOW = 'no_show';
}