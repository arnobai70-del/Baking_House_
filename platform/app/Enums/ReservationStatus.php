<?php

namespace App\Enums;

/**
 * Stock reservation lifecycle. Only Active reservations hold stock; every transition out of
 * Active is a single guarded UPDATE so a reservation can never be released or committed twice.
 */
enum ReservationStatus: string
{
    case Active = 'active';
    case Committed = 'committed';
    case Released = 'released';
    case Expired = 'expired';
}
