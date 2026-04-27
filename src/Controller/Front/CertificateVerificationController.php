<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Elearning\Participation;
use App\Repository\Elearning\ParticipationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CertificateVerificationController extends AbstractController
{
    #[Route('/front/certificat/verifier', name: 'app_front_certificate_verify', methods: ['GET'])]
    public function verify(Request $request, ParticipationRepository $participationRepository): Response
    {
        $id = (int) $request->query->get('id', 0);
        $sig = (string) $request->query->get('sig', '');
        if ($id < 1 || $sig === '') {
            return $this->render('front/certificate/verify.html.twig', ['valid' => false]);
        }

        $participation = $participationRepository->find($id);
        if (!$participation instanceof Participation || !$participation->isPaidEnrollment()) {
            return $this->render('front/certificate/verify.html.twig', ['valid' => false]);
        }

        $expected = $this->buildSignature($participation);
        if (!hash_equals($expected, $sig)) {
            return $this->render('front/certificate/verify.html.twig', ['valid' => false]);
        }

        return $this->render('front/certificate/verify.html.twig', [
            'valid' => true,
            'participation' => $participation,
            'formation' => $participation->getFormation(),
            'user' => $participation->getUser(),
        ]);
    }

    private function buildSignature(Participation $participation): string
    {
        $secret = (string) $this->getParameter('kernel.secret');

        return substr(hash_hmac('sha256', (string) $participation->getId_candidature(), $secret), 0, 48);
    }
}
