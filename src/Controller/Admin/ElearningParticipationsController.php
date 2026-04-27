<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\Elearning\ParticipationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/back/elearning/participations')]
#[IsGranted('ROLE_ADMIN')]
final class ElearningParticipationsController extends AbstractController
{
    #[Route('', name: 'app_admin_elearning_participations_index', methods: ['GET'])]
    public function index(ParticipationRepository $participationRepository): Response
    {
        return $this->render('back/elearning/participations/index.html.twig', [
            'participations' => $participationRepository->findAllForAdminDashboard(),
        ]);
    }
}
