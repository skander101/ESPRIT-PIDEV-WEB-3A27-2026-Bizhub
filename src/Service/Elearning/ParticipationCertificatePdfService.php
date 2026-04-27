<?php

declare(strict_types=1);

namespace App\Service\Elearning;

use App\Entity\Elearning\Formation;
use App\Entity\Elearning\Participation;
use App\Entity\UsersAvis\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Generates "Attestation_[slug].pdf" under public/uploads/certificates.
 */
final class ParticipationCertificatePdfService
{
    public function __construct(
        private readonly string $certificatesDir,
        private readonly string $certificatesPublicPath,
    ) {
    }

    /**
     * @return string Web path starting with /uploads/certificates/…
     */
    public function generateAndSave(Participation $participation, string $verificationAbsoluteUrl): string
    {
        if (!is_dir($this->certificatesDir)) {
            mkdir($this->certificatesDir, 0755, true);
        }

        $user = $participation->getUser();
        $formation = $participation->getFormation();
        if (!$user instanceof User || !$formation instanceof Formation) {
            throw new \InvalidArgumentException('Participation incomplète pour le certificat.');
        }

        $slug = $this->slugify((string) ($user->getEmail() ?? 'user')) . '-' . $participation->getId_candidature();
        $filename = 'Attestation_' . $slug . '.pdf';
        $absolute = $this->certificatesDir . DIRECTORY_SEPARATOR . $filename;

        $certificateNumber = sprintf('BH-CERT-%s-%05d', date('Y'), (int) $participation->getId_candidature());
        $qrSvg = $this->buildQrSvg($verificationAbsoluteUrl);

        $html = $this->buildHtml($participation, $formation, $user, $certificateNumber, $qrSvg);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        file_put_contents($absolute, $dompdf->output());

        return rtrim($this->certificatesPublicPath, '/') . '/' . $filename;
    }

    private function slugify(string $email): string
    {
        $base = preg_replace('/[^a-zA-Z0-9]+/', '_', strstr($email, '@', true) ?: $email) ?? 'participant';

        return strtolower(trim($base, '_')) ?: 'participant';
    }

    private function buildQrSvg(string $url): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(140, 1),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);

        return $writer->writeString($url);
    }

    private function buildHtml(
        Participation $participation,
        Formation $formation,
        User $user,
        string $certificateNumber,
        string $qrSvg,
    ): string {
        $fullName = htmlspecialchars($user->getFullName() ?? $user->getEmail() ?? 'Participant', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $title = htmlspecialchars($formation->getTitle() ?? 'Formation', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $instructor = htmlspecialchars($formation->getUser()?->getFullName() ?? '—', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $start = $formation->getStartDate() ? $formation->getStartDate()->format('d/m/Y') : '—';
        $end = $formation->getEndDate() ? $formation->getEndDate()->format('d/m/Y') : '—';
        $mode = $formation->isEnLigne() ? 'En ligne' : 'Présentielle';
        $lieu = $formation->isEnLigne() ? '—' : htmlspecialchars($formation->getLieu() ?? '—', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $num = htmlspecialchars($certificateNumber, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <style>
    @page { margin: 28pt; }
    body { font-family: "DejaVu Sans", sans-serif; color: #0f172a; margin: 0; padding: 0; font-size: 11pt; }
    .frame { border: 3px solid #0ea5e9; border-radius: 12px; padding: 28px 32px; min-height: 720px; box-sizing: border-box;
      background: linear-gradient(180deg, #f8fafc 0%, #ffffff 45%); }
    .brand { text-align: center; margin-bottom: 8px; }
    .brand span { font-size: 18pt; font-weight: bold; color: #0369a1; letter-spacing: 2px; }
    .brand small { display:block; color:#64748b; font-size:9pt; margin-top:4px; }
    h1 { text-align: center; font-size: 16pt; color: #0c4a6e; margin: 18px 0 6px; text-transform: uppercase; letter-spacing: .08em; }
    .subtitle { text-align:center; color:#0369a1; font-size:10pt; margin-bottom: 22px; }
    .recipient { font-size: 13pt; font-weight: bold; text-align: center; margin: 16px 0 22px; color: #0f172a; }
    .box { background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 8px; padding: 14px 16px; margin-bottom: 14px; }
    .row { margin: 4px 0; }
    .label { color: #475569; font-size: 9.5pt; text-transform: uppercase; letter-spacing: .06em; }
    .value { color: #0f172a; font-weight: 600; }
    .footer { margin-top: 28px; display: table; width: 100%; }
    .sig { display: table-cell; width: 55%; vertical-align: bottom; font-size: 9pt; color: #475569; }
    .qr { display: table-cell; width: 45%; text-align: right; vertical-align: bottom; }
    .stamp { margin-top: 10px; font-size: 9pt; color: #0ea5e9; font-weight: bold; border: 1px dashed #38bdf8; display: inline-block; padding: 6px 12px; border-radius: 6px; }
  </style>
</head>
<body>
  <div class="frame">
    <div class="brand">
      <span>BizHub</span>
      <small>Plateforme professionnelle</small>
    </div>
    <h1>Attestation de participation</h1>
    <div class="subtitle">Document officiel — ne pas reproduire sans autorisation</div>
    <div class="recipient">{$fullName}</div>
    <p style="text-align:center; font-size:10.5pt; line-height:1.5; color:#334155;">
      certifie avoir suivi la formation intitulée <strong>{$title}</strong>, selon les modalités indiquées ci-dessous.
    </p>
    <div class="box">
      <div class="row"><span class="label">Formation</span><br/><span class="value">{$title}</span></div>
      <div class="row"><span class="label">Formateur</span><br/><span class="value">{$instructor}</span></div>
      <div class="row"><span class="label">Période</span><br/><span class="value">{$start} &rarr; {$end}</span></div>
      <div class="row"><span class="label">Modalité / lieu</span><br/><span class="value">{$mode} — {$lieu}</span></div>
      <div class="row"><span class="label">Numéro de certificat</span><br/><span class="value">{$num}</span></div>
    </div>
    <div class="footer">
      <div class="sig">
        <div style="height:48px;border-bottom:1px solid #94a3b8;width:220px;margin-bottom:6px;"></div>
        <div>Signature et cachet BizHub</div>
        <div class="stamp">Vérification : scannez le QR code &rarr;</div>
      </div>
      <div class="qr">{$qrSvg}</div>
    </div>
  </div>
</body>
</html>
HTML;
    }
}
