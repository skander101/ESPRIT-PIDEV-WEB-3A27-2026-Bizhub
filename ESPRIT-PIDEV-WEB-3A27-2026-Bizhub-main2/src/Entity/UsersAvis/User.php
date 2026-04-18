<?php

namespace App\Entity\UsersAvis;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\ORM\Mapping\UniqueConstraint;

use App\Repository\UsersAvis\UserRepository;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'user')]
#[UniqueConstraint(name: 'email_unique', columns: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'This email is already used.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const USER_TYPES = [
        'startup' => 'startup',
        'fournisseur' => 'fournisseur',
        'formateur' => 'formateur',
        'investisseur' => 'investisseur',
    ];

    public function __construct()
    {
        $this->avis = new ArrayCollection();
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $user_id = null;

    #[ORM\Column(type: 'string', nullable: false)]
    #[Assert\NotBlank(message: 'Email is required')]
    #[Assert\Email(message: 'Please enter a valid email')]
    private ?string $email = null;

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $password_hash = null;

    #[ORM\Column(type: 'string', nullable: false)]
    #[Assert\NotBlank(message: 'Please select a user type')]
    #[Assert\Choice(choices: ['startup', 'fournisseur', 'formateur', 'investisseur'], message: 'Invalid user type')]
    private ?string $user_type = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $is_active = null;

    #[ORM\Column(type: 'string', nullable: false)]
    #[Assert\NotBlank(message: 'Full name is required')]
    #[Assert\Length(min: 2, max: 255, minMessage: 'Name must be at least 2 characters', maxMessage: 'Name must not exceed 255 characters')]
    private ?string $full_name = null;

    #[ORM\Column(type: 'string', nullable: true)]
    #[Assert\Regex(pattern: '/^\d{8}$/', message: 'Phone number must contain exactly 8 digits (Tunisian format).')]
    private ?string $phone = null;

    #[ORM\Column(type: 'string', nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'Address must not exceed 255 characters')]
    private ?string $address = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 1000, maxMessage: 'Bio must not exceed 1000 characters')]
    private ?string $bio = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $avatar_url = null;

    #[ORM\Column(type: 'string', nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'Company name must not exceed 255 characters')]
    private ?string $company_name = null;

    #[ORM\Column(type: 'string', nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'Sector must not exceed 255 characters')]
    private ?string $sector = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 5000, maxMessage: 'Company description must not exceed 5000 characters')]
    private ?string $company_description = null;

    #[ORM\Column(type: 'string', nullable: true)]
    #[Assert\Url(message: 'Please enter a valid website URL (e.g. https://example.com).')]
    #[Assert\Length(max: 255, maxMessage: 'Website must not exceed 255 characters')]
    private ?string $website = null;

    #[ORM\Column(type: 'date', nullable: true)]
    #[Assert\LessThanOrEqual(value: 'today', message: 'Founding date cannot be in the future.')]
    private ?\DateTimeInterface $founding_date = null;

    #[ORM\Column(type: 'string', nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'Business type must not exceed 255 characters')]
    private ?string $business_type = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 2000, maxMessage: 'Delivery zones must not exceed 2000 characters')]
    private ?string $delivery_zones = null;

    #[ORM\Column(type: 'string', nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'Payment methods must not exceed 255 characters')]
    private ?string $payment_methods = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 2000, maxMessage: 'Return policy must not exceed 2000 characters')]
    private ?string $return_policy = null;

    #[ORM\Column(type: 'string', nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'Investment sector must not exceed 255 characters')]
    private ?string $investment_sector = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Assert\PositiveOrZero(message: 'Maximum budget must be a positive number.')]
    #[Assert\LessThanOrEqual(value: 1000000000, message: 'Maximum budget is too high.')]
    private ?float $max_budget = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Range(min: 0, max: 80, notInRangeMessage: 'Years of experience must be between {{ min }} and {{ max }}.')]
    private ?int $years_experience = null;

    #[ORM\Column(type: 'string', nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'Represented company must not exceed 255 characters')]
    private ?string $represented_company = null;

    #[ORM\Column(type: 'string', nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'Specialty must not exceed 255 characters')]
    private ?string $specialty = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Assert\PositiveOrZero(message: 'Hourly rate must be a positive number.')]
    #[Assert\LessThanOrEqual(value: 10000, message: 'Hourly rate is too high.')]
    private ?float $hourly_rate = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 1000, maxMessage: 'Availability must not exceed 1000 characters')]
    private ?string $availability = null;

    #[ORM\Column(type: 'string', nullable: true)]
    #[Assert\Url(message: 'Please enter a valid CV URL (e.g. https://example.com/cv.pdf).')]
    #[Assert\Length(max: 255, maxMessage: 'CV URL must not exceed 255 characters')]
    private ?string $cv_url = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $totp_secret = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $face_token = null;

    #[ORM\OneToMany(targetEntity: Avis::class, mappedBy: 'user')]
    private Collection $avis;

    public function getUser_id(): ?int { return $this->user_id; }
    public function getUserId(): ?int { return $this->user_id; }
    public function setUser_id(int $user_id): self { $this->user_id = $user_id; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }

    public function getPasswordHash(): ?string { return $this->password_hash; }
    public function getPassword_hash(): ?string { return $this->password_hash; }
    public function setPasswordHash(string $password_hash): self { $this->password_hash = $password_hash; return $this; }
    public function setPassword_hash(string $password_hash): self { $this->password_hash = $password_hash; return $this; }

    public function getUserType(): ?string { return $this->user_type; }
    public function getUser_type(): ?string { return $this->user_type; }
    public function setUserType(string $user_type): self { $this->user_type = $user_type; return $this; }
    public function setUser_type(string $user_type): self { $this->user_type = $user_type; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->created_at; }
    public function getCreated_at(): ?\DateTimeInterface { return $this->created_at; }
    public function setCreatedAt(?\DateTimeInterface $created_at): self { $this->created_at = $created_at; return $this; }
    public function setCreated_at(?\DateTimeInterface $created_at): self { $this->created_at = $created_at; return $this; }

    public function getIsActive(): ?bool { return $this->is_active; }
    public function is_active(): ?bool { return $this->is_active; }
    public function setIsActive(?bool $is_active): self { $this->is_active = $is_active; return $this; }
    public function setIs_active(?bool $is_active): self { $this->is_active = $is_active; return $this; }

    public function getFullName(): ?string { return $this->full_name; }
    public function getFull_name(): ?string { return $this->full_name; }
    public function setFullName(string $full_name): self { $this->full_name = $full_name; return $this; }
    public function setFull_name(string $full_name): self { $this->full_name = $full_name; return $this; }

    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $phone): self { $this->phone = $phone; return $this; }

    public function getAddress(): ?string { return $this->address; }
    public function setAddress(?string $address): self { $this->address = $address; return $this; }

    public function getBio(): ?string { return $this->bio; }
    public function setBio(?string $bio): self { $this->bio = $bio; return $this; }

    public function getAvatarUrl(): ?string { return $this->avatar_url; }
    public function getAvatar_url(): ?string { return $this->avatar_url; }
    public function setAvatarUrl(?string $avatar_url): self { $this->avatar_url = $avatar_url; return $this; }
    public function setAvatar_url(?string $avatar_url): self { $this->avatar_url = $avatar_url; return $this; }

    public function getCompanyName(): ?string { return $this->company_name; }
    public function getCompany_name(): ?string { return $this->company_name; }
    public function setCompanyName(?string $company_name): self { $this->company_name = $company_name; return $this; }
    public function setCompany_name(?string $company_name): self { $this->company_name = $company_name; return $this; }

    public function getSector(): ?string { return $this->sector; }
    public function setSector(?string $sector): self { $this->sector = $sector; return $this; }

    public function getCompany_description(): ?string { return $this->company_description; }
    public function setCompany_description(?string $company_description): self { $this->company_description = $company_description; return $this; }
    public function getCompanyDescription(): ?string { return $this->company_description; }
    public function setCompanyDescription(?string $company_description): self { $this->company_description = $company_description; return $this; }

    public function getWebsite(): ?string { return $this->website; }
    public function setWebsite(?string $website): self { $this->website = $website; return $this; }

    public function getFounding_date(): ?\DateTimeInterface { return $this->founding_date; }
    public function setFounding_date(?\DateTimeInterface $founding_date): self { $this->founding_date = $founding_date; return $this; }
    public function getFoundingDate(): ?\DateTimeInterface { return $this->founding_date; }
    public function setFoundingDate(?\DateTimeInterface $founding_date): self { $this->founding_date = $founding_date; return $this; }

    public function getBusiness_type(): ?string { return $this->business_type; }
    public function setBusiness_type(?string $business_type): self { $this->business_type = $business_type; return $this; }
    public function getBusinessType(): ?string { return $this->business_type; }
    public function setBusinessType(?string $business_type): self { $this->business_type = $business_type; return $this; }

    public function getDelivery_zones(): ?string { return $this->delivery_zones; }
    public function setDelivery_zones(?string $delivery_zones): self { $this->delivery_zones = $delivery_zones; return $this; }
    public function getDeliveryZones(): ?string { return $this->delivery_zones; }
    public function setDeliveryZones(?string $delivery_zones): self { $this->delivery_zones = $delivery_zones; return $this; }

    public function getPayment_methods(): ?string { return $this->payment_methods; }
    public function setPayment_methods(?string $payment_methods): self { $this->payment_methods = $payment_methods; return $this; }
    public function getPaymentMethods(): ?string { return $this->payment_methods; }
    public function setPaymentMethods(?string $payment_methods): self { $this->payment_methods = $payment_methods; return $this; }

    public function getReturn_policy(): ?string { return $this->return_policy; }
    public function setReturn_policy(?string $return_policy): self { $this->return_policy = $return_policy; return $this; }
    public function getReturnPolicy(): ?string { return $this->return_policy; }
    public function setReturnPolicy(?string $return_policy): self { $this->return_policy = $return_policy; return $this; }

    public function getInvestment_sector(): ?string { return $this->investment_sector; }
    public function setInvestment_sector(?string $investment_sector): self { $this->investment_sector = $investment_sector; return $this; }
    public function getInvestmentSector(): ?string { return $this->investment_sector; }
    public function setInvestmentSector(?string $investment_sector): self { $this->investment_sector = $investment_sector; return $this; }

    public function getMax_budget(): ?float { return $this->max_budget; }
    public function setMax_budget(?float $max_budget): self { $this->max_budget = $max_budget; return $this; }
    public function getMaxBudget(): ?float { return $this->max_budget; }
    public function setMaxBudget(?float $max_budget): self { $this->max_budget = $max_budget; return $this; }

    public function getYears_experience(): ?int { return $this->years_experience; }
    public function setYears_experience(?int $years_experience): self { $this->years_experience = $years_experience; return $this; }
    public function getYearsExperience(): ?int { return $this->years_experience; }
    public function setYearsExperience(?int $years_experience): self { $this->years_experience = $years_experience; return $this; }

    public function getRepresented_company(): ?string { return $this->represented_company; }
    public function setRepresented_company(?string $represented_company): self { $this->represented_company = $represented_company; return $this; }
    public function getRepresentedCompany(): ?string { return $this->represented_company; }
    public function setRepresentedCompany(?string $represented_company): self { $this->represented_company = $represented_company; return $this; }

    public function getSpecialty(): ?string { return $this->specialty; }
    public function setSpecialty(?string $specialty): self { $this->specialty = $specialty; return $this; }

    public function getHourly_rate(): ?float { return $this->hourly_rate; }
    public function setHourly_rate(?float $hourly_rate): self { $this->hourly_rate = $hourly_rate; return $this; }
    public function getHourlyRate(): ?float { return $this->hourly_rate; }
    public function setHourlyRate(?float $hourly_rate): self { $this->hourly_rate = $hourly_rate; return $this; }

    public function getAvailability(): ?string { return $this->availability; }
    public function setAvailability(?string $availability): self { $this->availability = $availability; return $this; }

    public function getCv_url(): ?string { return $this->cv_url; }
    public function setCv_url(?string $cv_url): self { $this->cv_url = $cv_url; return $this; }
    public function getCvUrl(): ?string { return $this->cv_url; }
    public function setCvUrl(?string $cv_url): self { $this->cv_url = $cv_url; return $this; }

    public function getTotp_secret(): ?string { return $this->totp_secret; }
    public function setTotp_secret(?string $totp_secret): self { $this->totp_secret = $totp_secret; return $this; }

    public function getFace_token(): ?string { return $this->face_token; }
    public function setFace_token(?string $face_token): self { $this->face_token = $face_token; return $this; }

    /** @return Collection<int, Avis> */
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

    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];
        if ($this->user_type === 'admin') {
            $roles[] = 'ROLE_ADMIN';
        }
        return $roles;
    }

    public function getUserIdentifier(): string
    {
        return $this->email ?? '';
    }

    public function getPassword(): ?string
    {
        return $this->password_hash;
    }

    public function eraseCredentials(): void
    {
    }
}
