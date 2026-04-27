<?php

namespace App\Tests\Service\Community;

use App\Entity\Community\Post;
use App\Service\Community\PostManager;
use PHPUnit\Framework\TestCase;

class PostManagerTest extends TestCase
{
    private PostManager $manager;

    protected function setUp(): void
    {
        $this->manager = new PostManager();
    }

    public function testPostValide(): void
    {
        $post = new Post();
        $post->setTitle('Mon titre');
        $post->setContent('Mon contenu');

        $result = $this->manager->validate($post);

        $this->assertTrue($result === true);
    }

    public function testTitreVide(): void
    {
        $post = new Post();
        $post->setTitle('');
        $post->setContent('Mon contenu');

        $this->expectException(\InvalidArgumentException::class);
        $this->manager->validate($post);
    }

    public function testContenuVide(): void
    {
        $post = new Post();
        $post->setTitle('Mon titre');
        $post->setContent('');

        $this->expectException(\InvalidArgumentException::class);
        $this->manager->validate($post);
    }

    public function testTitreEtContenuVides(): void
    {
        $post = new Post();
        $post->setTitle('');
        $post->setContent('');

        $this->expectException(\InvalidArgumentException::class);
        $this->manager->validate($post);
    }
}