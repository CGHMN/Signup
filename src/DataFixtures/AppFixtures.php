<?php

/*
CGHMN Signup Page - A PHP project to ease the process of joining CGHMN.
Copyright (C) 2026 Logan C. et al. loganius@cghmn.org

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of  MERCHANTABILITY or FITNESS FOR
A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <http://www.gnu.org/licenses/>.

Many thanks to Jonas Luehrig (Snep) for all his contributions to this project,
both through writing code and providing advice.
*/

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
        $user->setEmail("loganius@cghmn.org");
        $user->setPubKey("xFfymVxpEAaUmTFl238AnkQTnNiyRgsB8MefesqLkVc=");
        $user->setPlan("trollollolloll");
        $user->setRoles(['ROLE_USER_PENDING']);
        $user->setNeedsHosting(false);
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
        $user->setRoles(['ROLE_SUPER_ADMIN']);
        $user->setContactMethod("Email");

        $manager->persist($user);
        $manager->flush();
    }
}
