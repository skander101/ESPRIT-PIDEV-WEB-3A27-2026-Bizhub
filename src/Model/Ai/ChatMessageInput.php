<?php

namespace App\Model\Ai;

use Symfony\Component\Validator\Constraints as Assert;

class ChatMessageInput
{
    #[Assert\NotBlank(message: 'Please enter a message.')]
    #[Assert\Length(
        min: 1,
        max: 1200,
        maxMessage: 'Message must not exceed {{ limit }} characters.'
    )]
    private ?string $message = null;

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message !== null ? trim($message) : null;

        return $this;
    }
}
