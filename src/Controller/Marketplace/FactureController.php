<?php

namespace App\Controller\Marketplace;

use App\Entity\Marketplace\Commande;
use App\Repository\Marketplace\FactureRepository;
use App\Service\Marketplace\FactureService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Consultation et téléchargement des factures.
 * Accessible par la startup (propriétaire de la commande).
 */
#[Route('/marketplace/facture', name: 'facture_')]
class FactureController extends AbstractController
{
    private function requireLogin(): ?Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        return null;
    }

    private function checkAccess(Commande $commande): void
    {
        $user   = $this->getUser();
        $userId = (int) $user->getUserId();

        if ($user->getUserType() === 'startup') {
            if ($commande->getIdClient() !== $userId) {
                throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette facture.');
            }
        }
        // Les investisseurs peuvent aussi voir la facture (vente de leurs produits)
        // Pas de restriction supplémentaire pour eux ici.
    }

    // ════════════════════════════════════════════════════════════════════
    //  VUE HTML DE LA FACTURE
    // ════════════════════════════════════════════════════════════════════

    #[Route('/commande/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(
        Commande           $commande,
        FactureRepository  $factureRepo,
        FactureService     $factureService,
    ): Response {
        if ($r = $this->requireLogin()) return $r;
        $this->checkAccess($commande);

        if (!$commande->isEstPayee()) {
            $this->addFlash('warning', 'La facture n\'est disponible qu\'après paiement de la commande.');
            return $this->redirectToRoute('commande_show', ['id' => $commande->getIdCommande()]);
        }

        // Créer la facture si elle n'existe pas encore (cas edge: webhook trop lent)
        $facture      = $factureService->createFromCommande($commande);
        $lignesDetail = $factureService->buildLignesDetail($commande);

        return $this->render('front/Marketplace/facture/show.html.twig', [
            'facture'       => $facture,
            'commande'      => $commande,
            'lignes_detail' => $lignesDetail,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════
    //  TÉLÉCHARGEMENT PDF
    // ════════════════════════════════════════════════════════════════════

    #[Route('/commande/{id}/pdf', name: 'download_pdf', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function downloadPdf(
        Commande           $commande,
        FactureRepository  $factureRepo,
        FactureService     $factureService,
    ): Response {
        if ($r = $this->requireLogin()) return $r;
        $this->checkAccess($commande);

        if (!$commande->isEstPayee()) {
            $this->addFlash('warning', 'La facture PDF n\'est disponible qu\'après paiement.');
            return $this->redirectToRoute('commande_show', ['id' => $commande->getIdCommande()]);
        }

        $facture = $factureService->createFromCommande($commande);
        $pdf     = $factureService->generatePdf($facture);

        $filename = 'facture-' . $facture->getNumeroFacture() . '.pdf';

        $response = new Response($pdf);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Content-Length', (string) strlen($pdf));

        return $response;
    }
}
