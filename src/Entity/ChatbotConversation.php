<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\ChatbotConversationRepository;

#[ORM\Entity(repositoryClass: ChatbotConversationRepository::class)]
#[ORM\Table(name: 'chatbot_conversation')]
class ChatbotConversation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }
}
