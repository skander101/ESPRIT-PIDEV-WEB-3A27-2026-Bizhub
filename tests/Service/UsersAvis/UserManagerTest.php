<?php

namespace App\Tests\Service\UsersAvis;

use App\Entity\UsersAvis\User;
use App\Service\UsersAvis\UserManager;
use PHPUnit\Framework\TestCase;

class UserManagerTest extends TestCase
{
    private UserManager $manager;

    protected function setUp(): void
    {
        $this->manager = new UserManager();
    }

    public function testUserValide(): void
    {
        $user = new User();
        $user->setEmail('user@gmail.com');
        $user->setUserType('startup');

        $result = $this->manager->validate($user);

        $this->assertTrue($result === true);
    }

    public function testEmailInvalide(): void
    {
        $user = new User();
        $user->setEmail('email_invalide');
        $user->setUserType('startup');

        $this->expectException(\InvalidArgumentException::class);
        $this->manager->validate($user);
    }

    public function testEmailVide(): void
    {
        $user = new User();
        $user->setEmail('');
        $user->setUserType('startup');

        $this->expectException(\InvalidArgumentException::class);
        $this->manager->validate($user);
    }

    public function testTypeInvalide(): void
    {
        $user = new User();
        $user->setEmail('user@gmail.com');
        $user->setUserType('admin');

        $this->expectException(\InvalidArgumentException::class);
        $this->manager->validate($user);
    }
}