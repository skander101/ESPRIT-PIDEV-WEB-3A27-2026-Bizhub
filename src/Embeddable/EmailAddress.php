<?php

namespace App\Embeddable;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class EmailAddress
{
    #[ORM\Column(type: 'string', unique: true)]
    private string $value;

    public function __construct(string $value = '')
    {
        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): self
    {
        $this->value = $value;
        return $this;
    }
}
