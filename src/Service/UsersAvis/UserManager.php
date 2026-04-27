<?php

namespace App\Service\UsersAvis;

use App\Entity\UsersAvis\User;

class UserManager
{
    private const TYPES_AUTORISES = [
        'startup',
        'fournisseur',
        'formateur',
        'investisseur',
    ];

    public function validate(User $user): bool
    {
        $email = $user->getEmail();
        if ($email === null || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Email invalide');
        }

        $type = $user->getUserType();
        if ($type === null || !in_array($type, self::TYPES_AUTORISES, true)) {
            throw new \InvalidArgumentException('Type utilisateur invalide');
        }

        return true;
    }
}