<?php

namespace App\Entity\Investissement;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;

use App\Repository\InvestmentRepository;
use App\Entity\UsersAvis\User;

#[ORM\Entity(repositoryClass: InvestmentRepository::class)]
#[ORM\Table(name: 'investment')]
class Investment
{
    const TYPE_PRISE_PARTICIPATION = 'prise_participation';
    const TYPE_PRET_CONVERTIBLE   = 'pret_convertible';
    const TYPE_PRET_SIMPLE        = 'pret_simple';
    const TYPE_DON                 = 'don';

    const TYPES_INVESTISSEMENT = [
        'Prise de participation' => self::TYPE_PRISE_PARTICIPATION,
        'Prêt convertible'       => self::TYPE_PRET_CONVERTIBLE,
        'Prêt simple'            => self::TYPE_PRET_SIMPLE,
        'Don / Grant'            => self::TYPE_DON,
    ];

    const DUREES = [
        '3 mois'  => '3m',
        '6 mois'  => '6m',
        '1 an'    => '12m',
        '2 ans'   => '24m',
        '3 ans'   => '36m',
        '5 ans'   => '60m',
    ];

    const PAYMENT_MODES = [
        'Virement bancaire' => 'virement',
        'Chèque'            => 'cheque',
        'Espèces'           => 'especes',
        'Carte bancaire'    => 'carte',
        'Crypto'            => 'crypto',
    ];

    const STATUTS = [
        'En attente'      => 'en_attente',
        'En négociation'  => 'en_negociation',
        'Accepté'         => 'accepte',
        'Refusé'          => 'refuse',
        'Contrat généré'  => 'contrat_genere',
        'Signé'           => 'signe',
        'Terminé'         => 'termine',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $investment_id = null;

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'investments')]
    #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'project_id')]
    #[Assert\NotNull(message: 'Le projet est obligatoire.')]
    private ?Project $project = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'investments')]
    #[ORM\JoinColumn(name: 'investor_id', referencedColumnName: 'user_id')]
    #[Assert\NotNull(message: 'L\'investisseur est obligatoire.')]
    private ?User $user = null;

    #[ORM\Column(type: 'decimal', nullable: false)]
    #[Assert\NotNull(message: 'Le montant est obligatoire.')]
    #[Assert\Positive(message: 'Le montant doit être positif.')]
    private ?float $amount = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Assert\LessThanOrEqual(value: 'now', message: "La date d'investissement ne peut pas être dans le futur.")]
    private ?\DateTimeInterface $investment_date = null;

    #[ORM\Column(type: 'string', nullable: true)]
    #[Assert\Url(message: "L'URL du contrat n'est pas valide.")]
    #[Assert\Length(max: 500, maxMessage: "L'URL du contrat ne peut pas dépasser {{ limit }} caractères.")]
    private ?string $contract_url = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    #[Assert\Choice(
        choices: ['virement', 'cheque', 'especes', 'carte', 'crypto'],
        message: 'Mode de paiement invalide.'
    )]
    private ?string $payment_mode = 'virement';

    #[ORM\Column(type: 'string', length: 30, nullable: true)]
    #[Assert\Choice(
        choices: ['en_attente', 'en_negociation', 'accepte', 'refuse', 'contrat_genere', 'signe', 'termine'],
        message: 'Statut invalide.'
    )]
    private ?string $statut = 'en_attente';

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 1000, maxMessage: 'Le commentaire ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $commentaire = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    #[Assert\Choice(
        choices: ['prise_participation', 'pret_convertible', 'pret_simple', 'don'],
        message: "Type d'investissement invalide."
    )]
    private ?string $type_investissement = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    #[Assert\Choice(
        choices: ['3m', '6m', '12m', '24m', '36m', '60m'],
        message: 'Durée souhaitée invalide.'
    )]
    private ?string $duree_souhaitee = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 800, maxMessage: 'Les conditions ne peuvent pas dépasser {{ limit }} caractères.')]
    private ?string $conditions_particulieres = null;

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $created_at = null;

    public function __construct()
    {
        $this->created_at = new \DateTime();
    }

    public function getInvestment_id(): ?int { return $this->investment_id; }
    public function setInvestment_id(int $investment_id): self { $this->investment_id = $investment_id; return $this; }

    public function getProject(): ?Project { return $this->project; }
    public function setProject(?Project $project): self { $this->project = $project; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getAmount(): ?float { return $this->amount; }
    public function setAmount(float $amount): self { $this->amount = $amount; return $this; }

    public function getInvestment_date(): ?\DateTimeInterface { return $this->investment_date; }
    public function getInvestmentDate(): ?\DateTimeInterface { return $this->investment_date; }
    public function setInvestment_date(?\DateTimeInterface $investment_date): self { $this->investment_date = $investment_date; return $this; }
    public function setInvestmentDate(?\DateTimeInterface $investment_date): self { $this->investment_date = $investment_date; return $this; }

    public function getContract_url(): ?string { return $this->contract_url; }
    public function getContractUrl(): ?string { return $this->contract_url; }
    public function setContract_url(?string $contract_url): self { $this->contract_url = $contract_url; return $this; }
    public function setContractUrl(?string $contract_url): self { $this->contract_url = $contract_url; return $this; }

    public function getPaymentMode(): ?string { return $this->payment_mode; }
    public function setPaymentMode(?string $payment_mode): self { $this->payment_mode = $payment_mode; return $this; }

    public function getStatut(): ?string { return $this->statut; }
    public function setStatut(?string $statut): self { $this->statut = $statut; return $this; }

    public function getCommentaire(): ?string { return $this->commentaire; }
    public function setCommentaire(?string $commentaire): self { $this->commentaire = $commentaire; return $this; }

    public function getTypeInvestissement(): ?string { return $this->type_investissement; }
    public function setTypeInvestissement(?string $type_investissement): self { $this->type_investissement = $type_investissement; return $this; }

    public function getDureeSouhaitee(): ?string { return $this->duree_souhaitee; }
    public function setDureeSouhaitee(?string $duree_souhaitee): self { $this->duree_souhaitee = $duree_souhaitee; return $this; }

    public function getConditionsParticulieres(): ?string { return $this->conditions_particulieres; }
    public function setConditionsParticulieres(?string $conditions_particulieres): self { $this->conditions_particulieres = $conditions_particulieres; return $this; }

    public function getCreated_at(): ?\DateTimeInterface { return $this->created_at; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->created_at; }
    public function setCreated_at(\DateTimeInterface $created_at): self { $this->created_at = $created_at; return $this; }
    public function setCreatedAt(\DateTimeInterface $created_at): self { $this->created_at = $created_at; return $this; }
}
