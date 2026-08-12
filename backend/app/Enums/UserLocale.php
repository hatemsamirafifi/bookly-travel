<?php

namespace App\Enums;

/**
 * Supported UI locale for the user (Spec 002, data-model.md).
 */
enum UserLocale: string
{
    case English = 'en';
    case Spanish = 'es';
    case Italian = 'it';
}
