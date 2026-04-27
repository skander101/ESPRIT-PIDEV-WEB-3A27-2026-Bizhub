<?php

namespace App\Service\Community;

use App\Entity\Community\Post;

class PostManager
{
    public function validate(Post $post): bool
    {
        $titre = $post->getTitle();
        if ($titre === null || $titre === '') {
            throw new \InvalidArgumentException('Le titre est obligatoire');
        }

        $contenu = $post->getContent();
        if ($contenu === null || $contenu === '') {
            throw new \InvalidArgumentException('Le contenu est obligatoire');
        }

        return true;
    }
}