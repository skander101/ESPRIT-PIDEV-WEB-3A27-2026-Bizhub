<?php

namespace App\Service\Elearning;

use App\Entity\Elearning\Formation;

class FormationManager
{
    public function validate(Formation $formation): bool
    {
        if (!$formation->isEnLigne()) {
            if ($formation->getLieu() === null || $formation->getLieu() === '') {
                throw new \InvalidArgumentException('Le lieu est obligatoire pour une formation présentielle');
            }
            if ($formation->getLatitude() === null) {
                throw new \InvalidArgumentException('La latitude est obligatoire pour une formation présentielle');
            }
            if ($formation->getLongitude() === null) {
                throw new \InvalidArgumentException('La longitude est obligatoire pour une formation présentielle');
            }
        }

        $dateDebut = $formation->getStartDate();
        $dateFin = $formation->getEndDate();

        if ($dateFin <= $dateDebut) {
            throw new \InvalidArgumentException('La date de fin doit être postérieure à la date de début');
        }

        return true;
    }
}