<?php

namespace App\Tests\Service\Community;

use App\Entity\Community\Post;
use App\Service\Community\PostTestManager;
use PHPUnit\Framework\TestCase;

final class PostTestManagerTest extends TestCase
{
    private PostTestManager $manager;

    protected function setUp(): void
    {
        $this->manager = new PostTestManager();
    }

    public function testValidateRejectsBlankTitle(): void
    {
        $post = new Post();
        $post->setTitle('');
        $post->setContent('Some content');
        $post->setCategory('General');

        $this->expectException(\InvalidArgumentException::class);
        $this->manager->validate($post);
    }

    public function testValidateDefaultsCategoryToGeneralWhenBlank(): void
    {
        $post = new Post();
        $post->setTitle('Hello');
        $post->setContent('World');
        $post->setCategory('   ');

        $result = $this->manager->validate($post);

        self::assertTrue($result);
        self::assertSame('General', $post->getCategory());
    }

    public function testValidateRejectsOutOfRangeLatitude(): void
    {
        $post = new Post();
        $post->setTitle('Hello');
        $post->setContent('World');
        $post->setCategory('General');
        $post->setLocationLat('200');

        $this->expectException(\InvalidArgumentException::class);
        $this->manager->validate($post);
    }
}
