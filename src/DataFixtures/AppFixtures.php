<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use App\Entity\User;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $user = new User;
        $user->setUsername("Testing");
        $user->setPassword(password_hash("12345", PASSWORD_BCRYPT));
        $user->setEmail("test@test.com");
        $user->setPubKey("xFfymVxpEAaUmTFl238AnkQTnNiyRgsB8MefesqLkVc=");
        $user->setPlan("trollollolloll");
        $user->setRoles(['ROLE_USER_PENDING']);
        $user->setContactMethod("Email");

        $manager->persist($user);

        $user = new User;
        $user->setUsername("Testing2");
        $user->setPassword(password_hash("123456", PASSWORD_BCRYPT));
        $user->setEmail("test2@test.com");
        $user->setPubKey("xFfymvxpEAaUmTFl238AnkQTnNiyRgsB8MefesqLkVc=");
        $user->setPlan("trollollolloll");
        $user->setRoles(['ROLE_USER_PENDING']);
        $user->setContactMethod("Email");

        $manager->persist($user);

        $user = new User;
        $user->setUsername("loganius");
        $user->setPassword(password_hash("very-secure", PASSWORD_BCRYPT));
        $user->setEmail("loganisamazing@outlook.com");
        $user->setRoles(['ROLE_ADMIN_APPROVED']);
        $user->setContactMethod("Email");

        $manager->persist($user);
        $manager->flush();
    }
}
