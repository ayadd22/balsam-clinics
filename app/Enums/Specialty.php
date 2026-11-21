<?php

namespace App\Enums;

enum Specialty: string
{
    case GENERAL_MEDICINE = 'general_medicine';
    case INTERNAL_MEDICINE = 'internal_medicine';
    case PEDIATRICS = 'pediatrics';
    case CARDIOLOGY = 'cardiology';
    case DERMATOLOGY = 'dermatology';
    case DENTISTRY = 'dentistry';
    case OPHTHALMOLOGY = 'ophthalmology';
    case ORTHOPEDICS = 'orthopedics';
    case GYNECOLOGY = 'gynecology';
    case ENT = 'ent';
    case NEUROLOGY = 'neurology';
}