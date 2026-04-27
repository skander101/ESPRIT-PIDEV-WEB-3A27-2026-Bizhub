<?php

namespace App\Controller;

use App\Entity\UsersAvis\User;
use App\Service\Elearning\FormationRecommendationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FrontController extends AbstractController
{
    public function __construct(
        private readonly FormationRecommendationService $formationRecommendationService,
    ) {
    }

    #[Route('/front', name: 'app_front_index', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();

        $reco = null;
        if ($user instanceof User) {
            $reco = $this->formationRecommendationService->getHomeBlocksForUser($user);
        }

        return $this->render('front/index.html.twig', [
            'user' => $user,
            'reco' => $reco,
        ]);
    }
}
