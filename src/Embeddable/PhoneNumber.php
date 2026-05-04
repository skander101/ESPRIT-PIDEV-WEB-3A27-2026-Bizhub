<?php

namespace App\Embeddable;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class PhoneNumber
{
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $value = null;

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): self
    {
        $this->value = $value;
        return $this;
    }
}
