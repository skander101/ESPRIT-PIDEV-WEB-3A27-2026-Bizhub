<?php

namespace App\Enum;

enum PaymentMethod: string
{
    case VIREMENT = 'virement';
    case CHEQUE = 'cheque';
    case ESPECES = 'especes';
    case CARTE = 'carte';
    case CRYPTO = 'crypto';
    // Add more as needed based on usages
}
