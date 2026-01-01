<?php

namespace App\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use App\Repository\UserRepository;
use App\Entity\User;

// Holds a collection of requests 
class Requests {
    protected Collection $requests;

    public function __construct(UserRepository $userRepository, string $type) {
        $this->requests = new ArrayCollection();
        $users = $userRepository->findAll();
        foreach ($users as $user) {
            if (in_array($type, $user->getRoles(), true)) {
                $this->requests->add($user);
            }
        }
    }

    public function getRequests(): Collection {
        return $this->requests;
    }
}