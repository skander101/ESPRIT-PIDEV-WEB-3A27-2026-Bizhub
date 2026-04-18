<?php

namespace App\Service\Investissement;

use App\Entity\Investissement\Deal;
use App\Entity\Investissement\Negotiation;
use App\Entity\UsersAvis\User;
use App\Repository\ProjectRepository;
use Dompdf\Dompdf;
use Dompdf\Options;

class ContractPdfService
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private string $projectDir,
    ) {}

    /**
     * Génère le PDF du contrat pour un deal et retourne le chemin relatif (public/).
     * Signature complète : deal + negotiation + buyer + seller (comme DealController l'appelle).
     */
    public function generate(Deal $deal, ?Negotiation $negotiation = null, ?User $buyer = null, ?User $seller = null): string
    {
        $html = $this->buildHtml($deal, $negotiation, $buyer, $seller);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $dir = $this->projectDir . '/public/contracts';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = sprintf('contract_deal_%d_%s.pdf', $deal->getDeal_id(), date('Ymd_His'));
        $filepath = $dir . '/' . $filename;

        file_put_contents($filepath, $dompdf->output());

        return '/contracts/' . $filename;
    }

    private function buildHtml(Deal $deal, ?Negotiation $negotiation, ?User $buyer, ?User $seller): string
    {
        $project = $this->projectRepository->find($deal->getProject_id());
        $title   = $project?->getTitle() ?? 'Projet #' . $deal->getProject_id();

        $amount    = number_format((float) $deal->getAmount(), 2, ',', ' ');
        $date      = $deal->getCreated_at()?->format('d/m/Y') ?? date('d/m/Y');
        $dealId    = $deal->getDeal_id();

        $buyerName  = $buyer  ? ($buyer->getFirstName()  . ' ' . $buyer->getLastName())  : 'Investisseur #' . $deal->getBuyer_id();
        $sellerName = $seller ? ($seller->getFirstName() . ' ' . $seller->getLastName()) : 'Startup #' . $deal->getSeller_id();
        $buyerEmail  = $buyer?->getEmail()  ?? '';
        $sellerEmail = $seller?->getEmail() ?? '';

        $negAmount = $negotiation?->getFinal_amount()
            ? number_format((float) $negotiation->getFinal_amount(), 2, ',', ' ') . ' TND'
            : $amount . ' TND';

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  body  { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a1a; margin: 40px; }
  h1    { font-size: 20px; color: #1a1a1a; border-bottom: 2px solid #ffbe33; padding-bottom: 8px; }
  h2    { font-size: 14px; color: #444; margin-top: 24px; }
  table { width: 100%; border-collapse: collapse; margin-top: 12px; }
  td, th{ padding: 8px 10px; border: 1px solid #ddd; }
  th    { background: #f5f5f5; text-align: left; }
  .footer   { margin-top: 60px; font-size: 10px; color: #888; text-align: center; }
  .sign-row { display: table; width: 100%; margin-top: 80px; }
  .sign-cell{ display: table-cell; width: 50%; padding: 0 20px; vertical-align: top; }
  .sign-line{ border-top: 1px solid #333; margin-top: 50px; }
</style>
</head>
<body>
<h1>Contrat d'Investissement — BizHub</h1>
<p><strong>Référence deal :</strong> #$dealId &nbsp;&nbsp;|&nbsp;&nbsp; <strong>Date :</strong> $date</p>

<h2>Parties</h2>
<table>
  <tr><th>Rôle</th><th>Nom</th><th>Email</th></tr>
  <tr><td>Investisseur (acheteur)</td><td>$buyerName</td><td>$buyerEmail</td></tr>
  <tr><td>Startup (vendeur)</td><td>$sellerName</td><td>$sellerEmail</td></tr>
</table>

<h2>Objet du contrat</h2>
<p>Investissement dans le projet <strong>$title</strong> pour un montant convenu de <strong>$negAmount</strong>.</p>

<h2>Conditions générales</h2>
<p>Les parties acceptent les termes convenus lors de la négociation sur la plateforme BizHub.
   Ce contrat est généré automatiquement et doit être signé électroniquement par les deux parties.
   Il est soumis au droit tunisien.</p>

<div class="sign-row">
  <div class="sign-cell"><div class="sign-line"></div><p>$buyerName<br/>(Investisseur)</p></div>
  <div class="sign-cell"><div class="sign-line"></div><p>$sellerName<br/>(Startup)</p></div>
</div>

<div class="footer">Document généré par BizHub — $date</div>
</body>
</html>
HTML;
    }
}
