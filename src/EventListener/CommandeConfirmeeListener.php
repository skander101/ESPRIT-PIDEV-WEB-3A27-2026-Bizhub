<?php

namespace App\EventListener;

use App\Entity\Marketplace\CommandeStatusHistory;
use App\Event\CommandeConfirmeeEvent;
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
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(CommandeConfirmeeEvent $event): void
    {
        $commande = $event->getCommande();

        // 1. Enregistrer l'entrée dans l'historique des statuts
        $history = (new CommandeStatusHistory())
            ->setCommande($commande)
            ->setStatutPrecedent(null) // était 'en_attente' avant confirmation
            ->setStatutNouveau($commande->getStatut())
            ->setChangedByUserId($event->getInvestisseurId())
            ->setNote('Confirmée par l\'investisseur');

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

        if ($investisseur) {
            $this->twilioService->sendConfirmationSms($commande, $startup, $investisseur);
        }
    }
}
