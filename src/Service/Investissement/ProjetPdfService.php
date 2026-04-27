<?php

namespace App\Service\Investissement;

use App\Entity\Investissement\Project;
use App\Repository\InvestmentRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class ProjetPdfService
{
    public function __construct(
        private Environment $twig,
        private InvestmentRepository $investmentRepository,
    ) {}

    public function generateProjetPdf(Project $projet): string
    {
        $totalInvesti    = $this->investmentRepository->getTotalInvestedByProject($projet);
        $pourcentage     = $projet->getRequiredBudget() > 0
            ? min(100, round(($totalInvesti / $projet->getRequiredBudget()) * 100, 1))
            : 0;
        $investissements = $this->investmentRepository->findByProject($projet);

        $html = $this->twig->render('front/projet/pdf.html.twig', [
            'projet'          => $projet,
            'total_investi'   => $totalInvesti,
            'pourcentage'     => $pourcentage,
            'investissements' => $investissements,
            'date_export'     => new \DateTime(),
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
