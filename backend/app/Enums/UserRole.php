<?php

namespace App\Enums;

/**
 * User account role (Spec 002, data-model.md).
 */
enum UserRole: string
{
    case Traveler = 'traveler';
    case Partner = 'partner';
    case Admin = 'admin';
}