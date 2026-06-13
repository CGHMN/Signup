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


namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\SecurityBundle\Security;
use App\Repository\UserRepository;
use App\Entity\Requests;
use App\Entity\User;

final class StatsController extends AbstractController {
    #[Route('/stats', name: 'app.stats')]
    public function index(): Response {
        return $this->render('stats/index.html.twig');
    }

    #[Route('/stats/allocations', name: 'app.stats.allocations')]
    public function allocations(UserRepository $userRepository, Security $security): Response {
        $users = new Requests($userRepository, 'ROLE_USER_APPROVED', $security);
        $allocs = [];
        // Count the users we're actually showing so we can add that as a statistic.
        $userCount = 0;
        // Iterate through all the users and get their Wireguard peers
        // so we can sort them later and display them in order.
        foreach ($users->getRequests() as $user) {
            if ($user->isDisplay()) {
                $userCount++;
                foreach ($user->getWireguardPeers() as $peer) {
                    // Regex to remove the CIDR notation from the tunnel IP.
                    $matches;
                    preg_match('/([\d\.]*)(?:\/\d+)?/', $peer->getTunnelIP(), $matches);
                    if (count($matches) != 0) {
                        array_push(
                            $allocs, [
                                'username' => $user->getUsername(),
                                'tunnelIP' => $matches[1],
                                'subnets' => $peer->getAllowedIPs()
                            ],
                        );
                    }
                }
            }
        }

        // Convert their tunnel IPs to numbers and compare them so we can sort
        // by tunnel IP in ascending order.
        usort($allocs, function($a, $b) {
            return ip2long($a['tunnelIP']) - ip2long($b['tunnelIP']);
        });

        return $this->render('stats/allocations.html.twig', [
            'allocs' => $allocs,
            'totalUsers' => $users->getRequests()->count(),
            'users' => $userCount,
        ]);
    }
}
