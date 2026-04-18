<?php

namespace App\Service\Investissement;

use App\Entity\Investissement\Deal;
use App\Entity\Investissement\Negotiation;
use App\Entity\UsersAvis\User;
use App\Repository\ProjectRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

/**
 * Generates the investment contract as a PDF using Dompdf + Twig.
 * Returns the public-relative path to the saved file.
 */
class ContractPdfService
{
    private string $contractDir;

    public function __construct(
        private ProjectRepository $projectRepository,
        private Environment       $twig,
        string                    $projectDir,
    ) {
        $this->contractDir = $projectDir . '/public/contracts';
    }

    /**
     * Generate the signature certificate PDF for a signed deal.
     * Returns the relative public path (usable in a URL).
     */
    public function generateCertificate(Deal $deal, User $buyer, User $seller): string
    {
        $certDir = $this->contractDir . '/certificats';
        if (!is_dir($certDir)) {
            mkdir($certDir, 0755, true);
        }

        $filename   = sprintf('certificat-signature-deal-%d.pdf', $deal->getDeal_id());
        $filePath   = $certDir . '/' . $filename;
        $publicPath = '/contracts/certificats/' . $filename;

        $signedAt = $deal->getCompleted_at() ?? new \DateTime();

        $statusLabels = [
            'signed'    => 'Signé',
            'completed' => 'Complété',
        ];
        $statusLabel = $statusLabels[$deal->getStatus()] ?? 'Signé';

        $html = $this->twig->render('pdf/certificat_signature.html.twig', [
            'deal'         => $deal,
            'buyer'        => $buyer,
            'seller'       => $seller,
            'signed_at'    => $signedAt,
            'status_label' => $statusLabel,
            'date'         => new \DateTime(),
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        file_put_contents($filePath, $dompdf->output());

        return $publicPath;
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

        $filename   = sprintf('contract_deal_%d_%s.pdf', $deal->getDeal_id(), date('Ymd_His'));
        $filePath   = $this->contractDir . '/' . $filename;
        $publicPath = '/contracts/' . $filename;

        $html = $this->twig->render('pdf/contrat.html.twig', [
            'deal'        => $deal,
            'negotiation' => $negotiation,
            'project'     => $project,
            'buyer'       => $buyer,
            'seller'      => $seller,
            'date'        => new \DateTime(),
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        file_put_contents($filePath, $dompdf->output());

        return $publicPath;
    }
}
