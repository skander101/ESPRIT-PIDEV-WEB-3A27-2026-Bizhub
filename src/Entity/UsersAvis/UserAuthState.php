<?php

namespace App\Entity\UsersAvis;

use App\Repository\UsersAvis\UserAuthStateRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserAuthStateRepository::class)]
#[ORM\Table(name: 'user_auth_state')]
#[ORM\UniqueConstraint(name: 'uniq_user_auth_state_user', columns: ['user_id'])]
#[ORM\UniqueConstraint(name: 'uniq_user_auth_state_oauth_identity', columns: ['oauth_provider', 'oauth_provider_id'])]
#[ORM\Index(name: 'idx_user_auth_state_verification_token', columns: ['verification_token'])]
#[ORM\Index(name: 'idx_user_auth_state_password_reset_token', columns: ['password_reset_token'])]
class UserAuthState
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $is_verified = true;

    #[ORM\Column(type: 'string', length: 128, nullable: true)]
    private ?string $verification_token = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeInterface $verification_token_expires_at = null;

    #[ORM\Column(type: 'string', length: 128, nullable: true)]
    private ?string $password_reset_token = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeInterface $password_reset_token_expires_at = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $mfa_enabled = false;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $mfa_enrollment_id = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $oauth_provider = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $oauth_provider_id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->is_verified;
    }

    public function setIsVerified(bool $isVerified): self
    {
        $this->is_verified = $isVerified;

        return $this;
    }

    public function getVerificationToken(): ?string
    {
        return $this->verification_token;
    }

    public function setVerificationToken(?string $verificationToken): self
    {
        $this->verification_token = $verificationToken;

        return $this;
    }

    public function getVerificationTokenExpiresAt(): ?\DateTimeInterface
    {
        return $this->verification_token_expires_at;
    }

    public function setVerificationTokenExpiresAt(?\DateTimeInterface $verificationTokenExpiresAt): self
    {
        $this->verification_token_expires_at = $verificationTokenExpiresAt;

        return $this;
    }

    public function getPasswordResetToken(): ?string
    {
        return $this->password_reset_token;
    }

    public function setPasswordResetToken(?string $passwordResetToken): self
    {
        $this->password_reset_token = $passwordResetToken;

        return $this;
    }

    public function getPasswordResetTokenExpiresAt(): ?\DateTimeInterface
    {
        return $this->password_reset_token_expires_at;
    }

    public function setPasswordResetTokenExpiresAt(?\DateTimeInterface $passwordResetTokenExpiresAt): self
    {
        $this->password_reset_token_expires_at = $passwordResetTokenExpiresAt;

        return $this;
    }

    public function isMfaEnabled(): bool
    {
        return $this->mfa_enabled;
    }

    public function setMfaEnabled(bool $mfaEnabled): self
    {
        $this->mfa_enabled = $mfaEnabled;

        return $this;
    }

    public function getMfaEnrollmentId(): ?string
    {
        return $this->mfa_enrollment_id;
    }

    public function setMfaEnrollmentId(?string $mfaEnrollmentId): self
    {
        $this->mfa_enrollment_id = $mfaEnrollmentId;

        return $this;
    }

    public function getOauthProvider(): ?string
    {
        return $this->oauth_provider;
    }

    public function setOauthProvider(?string $oauthProvider): self
    {
        $this->oauth_provider = $oauthProvider;

        return $this;
    }

    public function getOauthProviderId(): ?string
    {
        return $this->oauth_provider_id;
    }

    public function setOauthProviderId(?string $oauthProviderId): self
    {
        $this->oauth_provider_id = $oauthProviderId;

        return $this;
    }
}
