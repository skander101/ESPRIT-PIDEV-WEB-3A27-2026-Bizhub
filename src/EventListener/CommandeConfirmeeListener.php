<?php

namespace App\EventListener;

use App\Entity\Marketplace\AutoConfirmNotification;
use App\Entity\Marketplace\CommandeStatusHistory;
use App\Event\CommandeConfirmeeEvent;
use App\Repository\Marketplace\ProduitServiceRepository;
use App\Repository\UsersAvis\UserRepository;
use App\Service\Marketplace\TwilioService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: CommandeConfirmeeEvent::NAME)]
class CommandeConfirmeeListener
{
    public function __construct(
        private readonly TwilioService $twilioService,
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
        private readonly ProduitServiceRepository $produitRepo,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(CommandeConfirmeeEvent $event): void
    {
        $commande = $event->getCommande();

        // 1. Enregistrer l'entrée dans l'historique des statuts
        $note = $event->getInvestisseurId()
            ? 'Confirmée par l\'investisseur'
            : 'Confirmée automatiquement par le moteur de scoring';

        $history = (new CommandeStatusHistory())
            ->setCommande($commande)
            ->setStatutPrecedent(null)
            ->setStatutNouveau($commande->getStatut())
            ->setChangedByUserId($event->getInvestisseurId())
            ->setNote($note);

        $this->em->persist($history);
        $this->em->flush();

        // 2. Envoyer un SMS Twilio à la startup
        $startup = $this->userRepository->find($commande->getIdClient());
        if (!$startup) {
            $this->logger->warning('CommandeConfirmeeListener: startup introuvable', [
                'id_client' => $commande->getIdClient(),
            ]);
            return;
        }

        $investisseur = $event->getInvestisseurId()
            ? $this->userRepository->find($event->getInvestisseurId())
            : null;

        // 3. Notifier la startup de la confirmation
        $this->twilioService->sendConfirmationSms($commande, $startup, $investisseur);

        // 4. En cas de confirmation automatique, créer une notification toast + WhatsApp investisseur
        if ($event->getInvestisseurId() === null) {
            $investisseursNotifies = [];
            foreach ($commande->getLignes() as $ligne) {
                $produit = $this->produitRepo->find($ligne->getIdProduit());
                if (!$produit) continue;

                $ownerId = $produit->getOwnerUserId();
                if ($ownerId === null || isset($investisseursNotifies[$ownerId])) continue;

                $owner = $this->userRepository->find($ownerId);
                if (!$owner) continue;

                // Notification toast en base (affichée côté investisseur à la prochaine visite)
                $notif = (new AutoConfirmNotification())
                    ->setInvestisseurId($ownerId)
                    ->setCommandeId($commande->getIdCommande())
                    ->setStartupName($startup->getFullName() ?? $startup->getEmail())
                    ->setMontantTtc($commande->getTotalTtc())
                    ->setScoreAuto($commande->getScoreAuto() ?? 0);
                $this->em->persist($notif);

                // WhatsApp investisseur
                $this->twilioService->sendAutoConfirmInvestisseurNotification($commande, $owner, $startup);
                $investisseursNotifies[$ownerId] = true;
            }
            $this->em->flush();
        }
    }
}
