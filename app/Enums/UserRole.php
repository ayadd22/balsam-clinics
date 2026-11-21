<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case RECEPTIONIST = 'receptionist';
    case DOCTOR = 'doctor';
    case PATIENT = 'patient';
}