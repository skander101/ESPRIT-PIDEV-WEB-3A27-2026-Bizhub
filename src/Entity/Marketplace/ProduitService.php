<?php

namespace App\Entity\Marketplace;

use App\Repository\Marketplace\ProduitServiceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

// #[Vich\Uploadable] indique à VichUploaderBundle que cette entité utilise l'upload
#[Vich\Uploadable]
#[ORM\Entity(repositoryClass: ProduitServiceRepository::class)]
#[ORM\Table(name: 'produit_service')]
#[ORM\HasLifecycleCallbacks]
class ProduitService
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_produit', type: 'integer')]
    private ?int $idProduit = null;

    #[ORM\Column(name: 'id_profile', type: 'integer', nullable: false)]
    private ?int $idProfile = null;

    #[ORM\Column(name: 'nom', type: 'string', length: 255, nullable: false)]
    private ?string $nom = null;

    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'prix', type: 'decimal', precision: 10, scale: 3, nullable: false)]
    private ?string $prix = null;

    #[ORM\Column(name: 'quantite', type: 'integer', nullable: false)]
    private ?int $quantite = null;

    #[ORM\Column(name: 'categorie', type: 'string', length: 100, nullable: true)]
    private ?string $categorie = null;

    #[ORM\Column(name: 'disponible', type: 'boolean', nullable: false, options: ['default' => true])]
    private bool $disponible = true;

    #[ORM\Column(name: 'owner_user_id', type: 'integer', nullable: true)]
    private ?int $ownerUserId = null;

    /**
     * imageFile : l'objet File PHP lors de l'upload.
     * Non persisté en base — Vich s'en occupe via ses listeners.
     * #[Vich\UploadableField] lie ce champ au mapping 'product_images'
     * et stocke le nom final dans la propriété 'imageName'.
     */
    #[Vich\UploadableField(mapping: 'product_images', fileNameProperty: 'imageName')]
    private ?File $imageFile = null;

    /**
     * imageName : le nom du fichier enregistré sur le disque (ex: photo-abc123.jpg).
     * C'est la seule valeur stockée en base (colonne image_path).
     * Aucune migration nécessaire — on garde le nom de colonne existant.
     */
    #[ORM\Column(name: 'image_path', type: 'string', length: 255, nullable: true)]
    private ?string $imageName = null;

    public function getIdProduit(): ?int
    {
        return $this->idProduit;
    }

    public function getIdProfile(): ?int
    {
        return $this->idProfile;
    }

    public function setIdProfile(?int $v): self
    {
        $this->idProfile = $v;

        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $v): self
    {
        $this->nom = $v;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $v): self
    {
        $this->description = $v;

        return $this;
    }

    public function getPrix(): ?string
    {
        return $this->prix;
    }

    public function setPrix(?string $v): self
    {
        $this->prix = $v;

        return $this;
    }

    public function getQuantite(): ?int
    {
        return $this->quantite;
    }

    public function setQuantite(?int $v): self
    {
        $this->quantite = $v;

        return $this;
    }

    public function getCategorie(): ?string
    {
        return $this->categorie;
    }

    public function setCategorie(?string $v): self
    {
        $this->categorie = $v;

        return $this;
    }

    public function isDisponible(): bool
    {
        return $this->disponible;
    }

    public function setDisponible(bool $v): self
    {
        $this->disponible = $v;

        return $this;
    }

    public function getOwnerUserId(): ?int
    {
        return $this->ownerUserId;
    }

    public function setOwnerUserId(?int $v): self
    {
        $this->ownerUserId = $v;

        return $this;
    }

    /**
     * setImageFile : appelé par le formulaire quand l'utilisateur choisit un fichier.
     * Vich détecte que imageFile n'est pas null → déclenche l'upload automatiquement.
     */
    public function setImageFile(?File $imageFile = null): self
    {
        $this->imageFile = $imageFile;
        return $this;
    }

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    /** Nom du fichier stocké en base (colonne image_path). */
    public function setImageName(?string $imageName): self
    {
        $this->imageName = $imageName;
        return $this;
    }

    public function getImageName(): ?string
    {
        return $this->imageName;
    }

    /**
     * Alias de compatibilité — certains templates utilisent encore imagePath.
     * Redirige vers getImageName() sans changer la base de données.
     */
    public function getImagePath(): ?string
    {
        return $this->imageName;
    }

    public function __toString(): string
    {
        return $this->nom ?? '';
    }
}