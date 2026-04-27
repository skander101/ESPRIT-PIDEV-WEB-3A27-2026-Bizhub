<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\Elearning\PromoCodeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/back/elearning/promo-codes')]
#[IsGranted('ROLE_ADMIN')]
final class ElearningPromoCodesController extends AbstractController
{
    #[Route('', name: 'app_admin_elearning_promo_codes_index', methods: ['GET'])]
    public function index(PromoCodeRepository $promoCodeRepository): Response
    {
        return $this->render('back/elearning/promo_codes/index.html.twig', [
            'promo_codes' => $promoCodeRepository->findAllForAdmin(),
        ]);
    }
}
