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

namespace App\Entity;

use Symfony\Bundle\SecurityBundle\Security;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use App\Repository\UserRepository;
use App\Entity\User;

// Holds a collection of requests
class Requests {
    protected Collection $requests;

    public function __construct(UserRepository $userRepository, string $type,
        Security $security) {
        $this->requests = new ArrayCollection();
        $users = $userRepository->findAll();
        foreach ($users as $user) {
            if ($security->isGrantedForUser($user, $type)) {
                $this->requests->add($user);
            }
        }
    }

    public function getRequests(): Collection {
        return $this->requests;
    }
}