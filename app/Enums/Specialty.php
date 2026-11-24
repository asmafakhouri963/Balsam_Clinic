<?php

namespace App\Enums;

enum Specialty: string
{
    case InternalMedicine = 'internal_medicine';
    case Pediatrics = 'pediatrics';
    case Dermatology = 'dermatology';
    case Cardiology = 'cardiology';
    case Orthopedics = 'orthopedics';
}