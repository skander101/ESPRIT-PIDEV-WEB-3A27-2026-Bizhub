<?php

namespace App\Service\Investissement;

use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class DompdfService
{
    public function __construct(private Environment $twig)
    {
    }

    public function generatePdf(string $template, array $data): string
    {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);

        $html = $this->twig->render($template, $data);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
