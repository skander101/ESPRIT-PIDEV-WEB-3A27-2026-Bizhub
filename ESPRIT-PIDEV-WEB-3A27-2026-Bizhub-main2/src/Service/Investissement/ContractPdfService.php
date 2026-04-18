<?php

namespace App\Service\Investissement;

use App\Entity\Investissement\Deal;
use App\Entity\Investissement\Negotiation;
use App\Entity\UsersAvis\User;
use App\Repository\ProjectRepository;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Generates the investment contract as a PDF using dompdf.
 * Returns the absolute path to the saved file.
 */
class ContractPdfService
{
    private string $contractDir;

    public function __construct(
        private ProjectRepository $projectRepository,
        string $projectDir,
    ) {
        $this->contractDir = $projectDir . '/public/contracts';
    }

    /**
     * Generate the PDF contract for a deal and save it to disk.
     * Returns the relative public path (usable in a URL).
     */
    public function generate(Deal $deal, Negotiation $negotiation, User $buyer, User $seller): string
    {
        if (!is_dir($this->contractDir)) {
            mkdir($this->contractDir, 0755, true);
        }

        $project = $this->projectRepository->find($deal->getProject_id());

        $filename  = sprintf('contract_deal_%d_%s.pdf', $deal->getDeal_id(), date('Ymd_His'));
        $filePath  = $this->contractDir . '/' . $filename;
        $publicPath = '/contracts/' . $filename;

        $html = $this->buildHtml($deal, $negotiation, $project, $buyer, $seller);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        file_put_contents($filePath, $dompdf->output());

        return $publicPath;
    }

    private function buildHtml(Deal $deal, Negotiation $negotiation, $project, User $buyer, User $seller): string
    {
        $date      = (new \DateTime())->format('d/m/Y');
        $amount    = number_format((float) $deal->getAmount(), 2, ',', ' ');
        $projectTitle = $project ? htmlspecialchars($project->getTitle()) : 'Projet #' . $deal->getProject_id();
        $buyerName    = htmlspecialchars($buyer->getFullName() ?? 'Investisseur');
        $sellerName   = htmlspecialchars($seller->getFullName() ?? 'Startup');
        $buyerEmail   = htmlspecialchars($buyer->getEmail() ?? '');
        $sellerEmail  = htmlspecialchars($seller->getEmail() ?? '');

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: "DejaVu Sans", sans-serif; font-size: 12px; color: #1a1a2e; margin: 0; padding: 40px; }
  .header { text-align: center; border-bottom: 3px solid #ffbe33; padding-bottom: 20px; margin-bottom: 30px; }
  .header h1 { font-size: 22px; margin: 0 0 6px; color: #1a1a2e; }
  .header p  { font-size: 11px; color: #666; margin: 0; }
  .badge { display: inline-block; background: #ffbe33; color: #1a1a2e; font-weight: bold;
           font-size: 10px; padding: 3px 10px; border-radius: 12px; }
  .section { margin-bottom: 24px; }
  .section-title { font-size: 11px; font-weight: bold; text-transform: uppercase;
                   letter-spacing: 1px; color: #ffbe33; border-bottom: 1px solid #eee;
                   padding-bottom: 5px; margin-bottom: 12px; }
  .row { display: flex; margin-bottom: 6px; }
  .label { width: 180px; font-weight: bold; color: #555; flex-shrink: 0; }
  .value { color: #1a1a2e; }
  .parties { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }
  .party-card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; }
  .party-card h4 { margin: 0 0 10px; font-size: 12px; color: #ffbe33; }
  .amount-box { background: #f9fafb; border: 2px solid #ffbe33; border-radius: 10px;
                padding: 18px; text-align: center; margin: 20px 0; }
  .amount-box .big { font-size: 26px; font-weight: bold; color: #1a1a2e; }
  .amount-box .sub { font-size: 11px; color: #666; margin-top: 4px; }
  .clauses { background: #fafafa; border: 1px solid #eee; border-radius: 8px; padding: 16px; font-size: 11px; line-height: 1.7; }
  .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 50px; }
  .sig-block { border-top: 1px solid #ccc; padding-top: 12px; text-align: center; font-size: 11px; color: #555; }
  .footer { text-align: center; font-size: 9px; color: #aaa; margin-top: 40px;
            border-top: 1px solid #eee; padding-top: 12px; }
</style>
</head>
<body>

<div class="header">
  <h1>CONTRAT D'INVESTISSEMENT</h1>
  <p>Référence : DEAL-{$deal->getDeal_id()} &nbsp;|&nbsp; Date : {$date}</p>
  <span class="badge">BizHub — Plateforme d'investissement</span>
</div>

<div class="section">
  <div class="section-title">Projet concerné</div>
  <div class="row"><span class="label">Titre :</span><span class="value">{$projectTitle}</span></div>
  <div class="row"><span class="label">Référence négociation :</span><span class="value">NEG-{$negotiation->getNegotiation_id()}</span></div>
</div>

<div class="section">
  <div class="section-title">Parties contractantes</div>
  <div class="parties">
    <div class="party-card">
      <h4>Investisseur (Acheteur)</h4>
      <div><strong>{$buyerName}</strong></div>
      <div style="color:#666;margin-top:4px;">{$buyerEmail}</div>
    </div>
    <div class="party-card">
      <h4>Startup (Vendeur)</h4>
      <div><strong>{$sellerName}</strong></div>
      <div style="color:#666;margin-top:4px;">{$sellerEmail}</div>
    </div>
  </div>
</div>

<div class="section">
  <div class="section-title">Montant de l'investissement</div>
  <div class="amount-box">
    <div class="big">{$amount} TND</div>
    <div class="sub">Montant convenu après négociation et validé par les deux parties</div>
  </div>
</div>

<div class="section">
  <div class="section-title">Clauses et conditions</div>
  <div class="clauses">
    <p>1. <strong>Objet :</strong> Le présent contrat formalise l'accord d'investissement conclu entre les parties à l'issue de la négociation référencée ci-dessus sur la plateforme BizHub.</p>
    <p>2. <strong>Montant :</strong> L'investisseur s'engage à verser la somme de <strong>{$amount} TND</strong> en faveur du projet <strong>{$projectTitle}</strong>.</p>
    <p>3. <strong>Paiement :</strong> Le paiement a été effectué et validé via la plateforme de paiement sécurisée Stripe.</p>
    <p>4. <strong>Confidentialité :</strong> Les parties s'engagent à garder confidentielles les informations échangées dans le cadre de cette négociation.</p>
    <p>5. <strong>Droit applicable :</strong> Le présent contrat est soumis au droit tunisien. Tout litige sera soumis aux tribunaux compétents de Tunis.</p>
    <p>6. <strong>Validité :</strong> Ce contrat prend effet dès sa signature électronique par les deux parties.</p>
  </div>
</div>

<div class="signatures">
  <div class="sig-block">
    <div style="margin-bottom:30px;">Signature de l'investisseur</div>
    <div><strong>{$buyerName}</strong></div>
    <div style="color:#aaa;">Date : {$date}</div>
  </div>
  <div class="sig-block">
    <div style="margin-bottom:30px;">Signature de la startup</div>
    <div><strong>{$sellerName}</strong></div>
    <div style="color:#aaa;">Date : {$date}</div>
  </div>
</div>

<div class="footer">
  Document généré automatiquement par BizHub le {$date}. Ce contrat a valeur juridique entre les parties signataires.
</div>

</body>
</html>
HTML;
    }
}
