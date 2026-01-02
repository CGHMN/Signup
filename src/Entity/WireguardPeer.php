<?php

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
    #[Assert\Regex(pattern: '/^[a-z0-9\+\/]{43}=$/i', message: 'You must enter a valid WireGuard public key.')]
    private ?string $pubKey = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[a-z0-9\+\/]{43}=$/i', message: 'An invalid preshared key should not be possible...')]
    private ?string $presharedKey = null;

    #[ORM\Column(type: Types::BIGINT)]
    #[Assert\NotBlank]
    private ?string $routerID = null;

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

    public function getRouterID(): ?string
    {
        return $this->routerID;
    }

    public function setRouterID(string $routerID): static
    {
        $this->routerID = $routerID;

        return $this;
    }
}
