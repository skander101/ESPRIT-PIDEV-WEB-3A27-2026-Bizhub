<?php

namespace App\Service\Marketplace;

use App\Entity\Marketplace\Commande;
use App\Entity\Marketplace\Facture;
use App\Repository\Marketplace\FactureRepository;
use App\Repository\Marketplace\ProduitServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Psr\Log\LoggerInterface;
use Twig\Environment;

/**
 * Service de gestion des factures.
 *
 * Deux responsabilités :
 *   1. createFromCommande() — crée l'entité Facture en base après paiement
 *   2. generatePdf()        — génère le PDF via DomPDF (dompdf/dompdf déjà installé)
 */
class FactureService
{
    public function __construct(
        private readonly EntityManagerInterface   $em,
        private readonly FactureRepository        $factureRepo,
        private readonly ProduitServiceRepository $produitRepo,
        private readonly Environment              $twig,
        private readonly LoggerInterface          $logger,
    ) {}

    // ════════════════════════════════════════════════════════════════════
    //  CRÉATION DE LA FACTURE EN BASE
    // ════════════════════════════════════════════════════════════════════

    /**
     * Crée la facture pour une commande payée.
     * Idempotent : si la facture existe déjà, la retourne sans la recréer.
     */
    public function createFromCommande(Commande $commande): Facture
    {
        // Idempotence : une commande = une facture maximum
        $existing = $this->factureRepo->findOneByCommande($commande);
        if ($existing !== null) {
            return $existing;
        }

        $facture = new Facture();
        $facture
            ->setCommande($commande)
            ->setNumeroFacture($this->generateNumero($commande))
            ->setDateFacture(new \DateTime())
            ->setTotalHt($commande->getTotalHt() ?? '0.000')
            ->setTotalTva($commande->getTotalTva() ?? '0.000')
            ->setTotalTtc($commande->getTotalTtc() ?? '0.000')
            ->setStripeRef($commande->getPaymentRef() ?? $commande->getStripeSessionId());

        $this->em->persist($facture);
        $this->em->flush();

        $this->logger->info('Facture créée', [
            'numero'      => $facture->getNumeroFacture(),
            'commande_id' => $commande->getIdCommande(),
        ]);

        return $facture;
    }

    // ════════════════════════════════════════════════════════════════════
    //  GÉNÉRATION PDF
    // ════════════════════════════════════════════════════════════════════

    /**
     * Génère le contenu PDF de la facture en bytes.
     * Utilise DomPDF (déjà installé via dompdf/dompdf v3.1).
     *
     * @return string contenu binaire du PDF
     */
    public function generatePdf(Facture $facture): string
    {
        $lignesDetail = $this->buildLignesDetail($facture->getCommande());

        $html = $this->twig->render('front/Marketplace/facture/pdf.html.twig', [
            'facture'       => $facture,
            'commande'      => $facture->getCommande(),
            'lignes_detail' => $lignesDetail,
        ]);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'sans-serif');
        $options->set('chroot', realpath(''));

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    // ════════════════════════════════════════════════════════════════════
    //  HELPERS
    // ════════════════════════════════════════════════════════════════════

    /**
     * Génère un numéro de facture unique : FAC-YYYY-NNNNN
     * Exemple : FAC-2026-00042
     */
    private function generateNumero(Commande $commande): string
    {
        return sprintf(
            'FAC-%s-%05d',
            date('Y'),
            $commande->getIdCommande()
        );
    }

    /**
     * Construit les détails des lignes avec le nom du produit.
     */
    public function buildLignesDetail(Commande $commande): array
    {
        $lignes = [];
        foreach ($commande->getLignes() as $ligne) {
            $produit = $this->produitRepo->find($ligne->getIdProduit());
            $lignes[] = [
                'ligne'   => $ligne,
                'produit' => $produit,
                'nom'     => $produit?->getNom() ?? 'Produit #' . $ligne->getIdProduit(),
            ];
        }

        // Fallback commande directe sans lignes
        if (empty($lignes) && $commande->getIdProduit()) {
            $produit  = $this->produitRepo->find($commande->getIdProduit());
            $lignes[] = [
                'ligne'   => null,
                'produit' => $produit,
                'nom'     => $produit?->getNom() ?? 'Commande BizHub #' . $commande->getIdCommande(),
            ];
        }

        return $lignes;
    }
}
