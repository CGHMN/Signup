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

use App\Repository\WireguardPeerRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validatior\Constraints as Assert;

#[ORM\Entity(repositoryClass: WireguardPeerRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_PUBKEY', fields: ['pubKey'])]
#[UniqueEntity(fields: ['pubKey'], message: 'There is already a Wireguard peer with this public key.')]
class WireguardPeer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'wireguardPeers')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotBlank]
    private ?User $user = null;

    #[ORM\Column(length: 32)]
    #[Assert\NotBlank]
    private ?string $tunnelIP = null;

    #[ORM\Column]
    #[Assert\NotBlank]
    private array $allowedIPs = [];

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[A-Za-z0-9+\/]{42}[AEIMQUYcgkosw480]=$/', message: 'You must enter a valid WireGuard public key.')]
    private ?string $pubKey = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[A-Za-z0-9+\/]{42}[AEIMQUYcgkosw480]=$/', message: 'An invalid preshared key should not be possible...')]
    private ?string $presharedKey = null;

    #[ORM\Column(type: Types::BIGINT)]
    #[Assert\NotBlank]
    private ?string $peerID = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getTunnelIP(): ?string
    {
        return $this->tunnelIP;
    }

    public function setTunnelIP(string $tunnelIP): static
    {
        $this->tunnelIP = $tunnelIP;

        return $this;
    }

    public function getAllowedIPs(): array
    {
        return $this->allowedIPs;
    }

    public function setAllowedIPs(array $allowedIPs): static
    {
        $this->allowedIPs = $allowedIPs;

        return $this;
    }

    public function getPubKey(): ?string
    {
        return $this->pubKey;
    }

    public function setPubKey(string $pubKey): static
    {
        $this->pubKey = $pubKey;

        return $this;
    }

    public function getPresharedKey(): ?string
    {
        return $this->presharedKey;
    }

    public function setPresharedKey(string $presharedKey): static
    {
        $this->presharedKey = $presharedKey;

        return $this;
    }

    public function getpeerID(): ?string
    {
        return $this->peerID;
    }

    public function setpeerID(string $peerID): static
    {
        $this->peerID = $peerID;

        return $this;
    }
}
