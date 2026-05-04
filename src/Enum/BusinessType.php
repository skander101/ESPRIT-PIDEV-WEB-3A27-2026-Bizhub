<?php

namespace App\Enum;

enum BusinessType: string
{
    case STARTUP = 'startup';
    case FOURNISSEUR = 'fournisseur';
    case FORMATEUR = 'formateur';
    case INVESTISSEUR = 'investisseur';
    // Add more as needed based on usages
}
