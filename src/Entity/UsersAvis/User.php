<?php

namespace App\Entity\UsersAvis;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\ORM\Mapping\UniqueConstraint;

use App\Repository\UsersAvis\UserRepository;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'user')]
#[UniqueConstraint(name: 'email_unique', columns: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public function __construct()
    {
        $this->avis = new ArrayCollection();
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $user_id = null;

    public function getUser_id(): ?int
    {
        return $this->user_id;
    }

    public function getUserId(): ?int
    {
        return $this->user_id;
    }

    public function setUser_id(int $user_id): self
    {
        $this->user_id = $user_id;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $email = null;

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $password_hash = null;

    public function getPasswordHash(): ?string
    {
        return $this->password_hash;
    }

    public function getPassword_hash(): ?string
    {
        return $this->password_hash;
    }

    public function setPasswordHash(string $password_hash): self
    {
        $this->password_hash = $password_hash;
        return $this;
    }

    public function setPassword_hash(string $password_hash): self
    {
        $this->password_hash = $password_hash;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $user_type = null;

    public function getUserType(): ?string
    {
        return $this->user_type;
    }

    public function getUser_type(): ?string
    {
        return $this->user_type;
    }

    public function setUserType(string $user_type): self
    {
        $this->user_type = $user_type;
        return $this;
    }

    public function setUser_type(string $user_type): self
    {
        $this->user_type = $user_type;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $created_at = null;

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function getCreated_at(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(?\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;
        return $this;
    }

    public function setCreated_at(?\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;
        return $this;
    }

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $is_active = null;

    public function getIsActive(): ?bool
    {
        return $this->is_active;
    }

    public function is_active(): ?bool
    {
        return $this->is_active;
    }

    public function setIsActive(?bool $is_active): self
    {
        $this->is_active = $is_active;
        return $this;
    }

    public function setIs_active(?bool $is_active): self
    {
        $this->is_active = $is_active;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $full_name = null;

    public function getFullName(): ?string
    {
        return $this->full_name;
    }

    public function getFull_name(): ?string
    {
        return $this->full_name;
    }

    public function setFullName(string $full_name): self
    {
        $this->full_name = $full_name;
        return $this;
    }

    public function setFull_name(string $full_name): self
    {
        $this->full_name = $full_name;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $phone = null;

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $address = null;

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): self
    {
        $this->address = $address;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $bio = null;

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): self
    {
        $this->bio = $bio;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $avatar_url = null;

    public function getAvatarUrl(): ?string
    {
        return $this->avatar_url;
    }

    public function getAvatar_url(): ?string
    {
        return $this->avatar_url;
    }

    public function setAvatarUrl(?string $avatar_url): self
    {
        $this->avatar_url = $avatar_url;
        return $this;
    }

    public function setAvatar_url(?string $avatar_url): self
    {
        $this->avatar_url = $avatar_url;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $company_name = null;

    public function getCompanyName(): ?string
    {
        return $this->company_name;
    }

    public function getCompany_name(): ?string
    {
        return $this->company_name;
    }

    public function setCompanyName(?string $company_name): self
    {
        $this->company_name = $company_name;
        return $this;
    }

    public function setCompany_name(?string $company_name): self
    {
        $this->company_name = $company_name;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $sector = null;

    public function getSector(): ?string
    {
        return $this->sector;
    }

    public function setSector(?string $sector): self
    {
        $this->sector = $sector;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $company_description = null;

    public function getCompany_description(): ?string
    {
        return $this->company_description;
    }

    public function setCompany_description(?string $company_description): self
    {
        $this->company_description = $company_description;
        return $this;
    }

    public function getCompanyDescription(): ?string
    {
        return $this->company_description;
    }

    public function setCompanyDescription(?string $company_description): self
    {
        $this->company_description = $company_description;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $website = null;

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): self
    {
        $this->website = $website;
        return $this;
    }

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $founding_date = null;

    public function getFounding_date(): ?\DateTimeInterface
    {
        return $this->founding_date;
    }

    public function setFounding_date(?\DateTimeInterface $founding_date): self
    {
        $this->founding_date = $founding_date;
        return $this;
    }

    public function getFoundingDate(): ?\DateTimeInterface
    {
        return $this->founding_date;
    }

    public function setFoundingDate(?\DateTimeInterface $founding_date): self
    {
        $this->founding_date = $founding_date;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $business_type = null;

    public function getBusiness_type(): ?string
    {
        return $this->business_type;
    }

    public function setBusiness_type(?string $business_type): self
    {
        $this->business_type = $business_type;
        return $this;
    }

    public function getBusinessType(): ?string
    {
        return $this->business_type;
    }

    public function setBusinessType(?string $business_type): self
    {
        $this->business_type = $business_type;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $delivery_zones = null;

    public function getDelivery_zones(): ?string
    {
        return $this->delivery_zones;
    }

    public function setDelivery_zones(?string $delivery_zones): self
    {
        $this->delivery_zones = $delivery_zones;
        return $this;
    }

    public function getDeliveryZones(): ?string
    {
        return $this->delivery_zones;
    }

    public function setDeliveryZones(?string $delivery_zones): self
    {
        $this->delivery_zones = $delivery_zones;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $payment_methods = null;

    public function getPayment_methods(): ?string
    {
        return $this->payment_methods;
    }

    public function setPayment_methods(?string $payment_methods): self
    {
        $this->payment_methods = $payment_methods;
        return $this;
    }

    public function getPaymentMethods(): ?string
    {
        return $this->payment_methods;
    }

    public function setPaymentMethods(?string $payment_methods): self
    {
        $this->payment_methods = $payment_methods;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $return_policy = null;

    public function getReturn_policy(): ?string
    {
        return $this->return_policy;
    }

    public function setReturn_policy(?string $return_policy): self
    {
        $this->return_policy = $return_policy;
        return $this;
    }

    public function getReturnPolicy(): ?string
    {
        return $this->return_policy;
    }

    public function setReturnPolicy(?string $return_policy): self
    {
        $this->return_policy = $return_policy;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $investment_sector = null;

    public function getInvestment_sector(): ?string
    {
        return $this->investment_sector;
    }

    public function setInvestment_sector(?string $investment_sector): self
    {
        $this->investment_sector = $investment_sector;
        return $this;
    }

    public function getInvestmentSector(): ?string
    {
        return $this->investment_sector;
    }

    public function setInvestmentSector(?string $investment_sector): self
    {
        $this->investment_sector = $investment_sector;
        return $this;
    }

    #[ORM\Column(type: 'decimal', nullable: true)]
    private ?float $max_budget = null;

    public function getMax_budget(): ?float
    {
        return $this->max_budget;
    }

    public function setMax_budget(?float $max_budget): self
    {
        $this->max_budget = $max_budget;
        return $this;
    }

    public function getMaxBudget(): ?float
    {
        return $this->max_budget;
    }

    public function setMaxBudget(?float $max_budget): self
    {
        $this->max_budget = $max_budget;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $years_experience = null;

    public function getYears_experience(): ?int
    {
        return $this->years_experience;
    }

    public function setYears_experience(?int $years_experience): self
    {
        $this->years_experience = $years_experience;
        return $this;
    }

    public function getYearsExperience(): ?int
    {
        return $this->years_experience;
    }

    public function setYearsExperience(?int $years_experience): self
    {
        $this->years_experience = $years_experience;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $represented_company = null;

    public function getRepresented_company(): ?string
    {
        return $this->represented_company;
    }

    public function setRepresented_company(?string $represented_company): self
    {
        $this->represented_company = $represented_company;
        return $this;
    }

    public function getRepresentedCompany(): ?string
    {
        return $this->represented_company;
    }

    public function setRepresentedCompany(?string $represented_company): self
    {
        $this->represented_company = $represented_company;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $specialty = null;

    public function getSpecialty(): ?string
    {
        return $this->specialty;
    }

    public function setSpecialty(?string $specialty): self
    {
        $this->specialty = $specialty;
        return $this;
    }

    #[ORM\Column(type: 'decimal', nullable: true)]
    private ?float $hourly_rate = null;

    public function getHourly_rate(): ?float
    {
        return $this->hourly_rate;
    }

    public function setHourly_rate(?float $hourly_rate): self
    {
        $this->hourly_rate = $hourly_rate;
        return $this;
    }

    public function getHourlyRate(): ?float
    {
        return $this->hourly_rate;
    }

    public function setHourlyRate(?float $hourly_rate): self
    {
        $this->hourly_rate = $hourly_rate;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $availability = null;

    public function getAvailability(): ?string
    {
        return $this->availability;
    }

    public function setAvailability(?string $availability): self
    {
        $this->availability = $availability;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $cv_url = null;

    public function getCv_url(): ?string
    {
        return $this->cv_url;
    }

    public function setCv_url(?string $cv_url): self
    {
        $this->cv_url = $cv_url;
        return $this;
    }

    public function getCvUrl(): ?string
    {
        return $this->cv_url;
    }

    public function setCvUrl(?string $cv_url): self
    {
        $this->cv_url = $cv_url;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $totp_secret = null;

    public function getTotp_secret(): ?string
    {
        return $this->totp_secret;
    }

    public function setTotp_secret(?string $totp_secret): self
    {
        $this->totp_secret = $totp_secret;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $face_token = null;

    public function getFace_token(): ?string
    {
        return $this->face_token;
    }

    public function setFace_token(?string $face_token): self
    {
        $this->face_token = $face_token;
        return $this;
    }

    #[ORM\OneToMany(targetEntity: Avis::class, mappedBy: 'user')]
    private Collection $avis;

    /**
     * @return Collection<int, Avis>
     */
    public function getAvis(): Collection
    {
        if (!$this->avis instanceof Collection) {
            $this->avis = new ArrayCollection();
        }
        return $this->avis;
    }

    public function addAvi(Avis $avi): self
    {
        if (!$this->getAvis()->contains($avi)) {
            $this->getAvis()->add($avi);
            $avi->setUser($this);
        }
        return $this;
    }

    public function removeAvi(Avis $avi): self
    {
        if ($this->getAvis()->removeElement($avi)) {
            if ($avi->getUser() === $this) {
                $avi->setUser(null);
            }
        }
        return $this;
    }

    // ===== UserInterface Implementation =====

    /**
     * Get the roles for this user as a Symfony security role
     *
     * @return string[]
     */
    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];

        // Admin users get ROLE_ADMIN
        if ($this->user_type === 'admin') {
            $roles[] = 'ROLE_ADMIN';
        }

        return $roles;
    }

    /**
     * Get the user identifier (used for authentication)
     */
    public function getUserIdentifier(): string
    {
        return $this->email ?? '';
    }

    /**
     * Get the password (required for PasswordAuthenticatedUserInterface)
     */
    public function getPassword(): ?string
    {
        return $this->password_hash;
    }

    /**
     * Erase sensitive data (optional for this implementation)
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
    }

}
