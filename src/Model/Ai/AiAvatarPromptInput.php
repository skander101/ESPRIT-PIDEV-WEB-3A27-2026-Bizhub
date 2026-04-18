<?php

namespace App\Model\Ai;

use Symfony\Component\Validator\Constraints as Assert;

class AiAvatarPromptInput
{
    #[Assert\NotBlank(message: 'Please provide a prompt for your AI picture.')]
    #[Assert\Length(
        min: 10,
        max: 350,
        minMessage: 'Prompt must be at least {{ limit }} characters long.',
        maxMessage: 'Prompt must not exceed {{ limit }} characters.'
    )]
    private ?string $prompt = null;

    public function getPrompt(): ?string
    {
        return $this->prompt;
    }

    public function setPrompt(?string $prompt): self
    {
        $this->prompt = $prompt !== null ? trim($prompt) : null;

        return $this;
    }
}
