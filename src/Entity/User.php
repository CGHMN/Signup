<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validatior\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_USERNAME', fields: ['username'])]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ["email"])]
#[ORM\Index(name: 'INDEX_USERNAME', fields: ["username"])]
#[UniqueEntity(fields: ['username'], message: 'There is already an account with this username')]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email address')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[a-z]\w{0, 63}$/i', message: 'You must enter a valid username (no spaces or special chacters).')]
    private ?string $username = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    #[Assert\NotBlank]
    private ?string $password = null;

    #[ORM\Column(length: 256)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(min: 5, max: 256, maxMessage: 'Your email address may not be longer than {{ limit }} characters.')]
    private ?string $email = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Regex(pattern: '/^[a-z0-9\+\/]{43}=$/i', message: 'You must enter a valid WireGuard public key.')]
    private ?string $pubKey = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $plan = null;

    #[ORM\Column(nullable: true)]
    private ?bool $needsHosting = false;

    #[ORM\Column(nullable: true)]
    private ?bool $hasExperience = false;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private ?string $contactMethod = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $contactDetails = null;

    /**
     * @var Collection<int, WireguardPeer>
     */
    #[ORM\OneToMany(targetEntity: WireguardPeer::class, mappedBy: 'userID', orphanRemoval: false)]
    private Collection $wireguardPeers;

    public function __construct()
    {
        $this->wireguardPeers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

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

    public function getPlan(): ?string
    {
        return $this->plan;
    }

    public function setPlan(string $plan): static
    {
        $this->plan = $plan;

        return $this;
    }

    public function isNeedsHosting(): ?bool
    {
        return $this->needsHosting;
    }

    public function setNeedsHosting(?bool $needsHosting): static
    {
        $this->needsHosting = $needsHosting;

        return $this;
    }

    public function hasExperience(): ?bool
    {
        return $this->hasExperience;
    }

    public function setHasExperience(?bool $hasExperience): static
    {
        $this->hasExperience = $hasExperience;

        return $this;
    }

    public function getContactMethod(): ?string
    {
        return $this->contactMethod;
    }

    public function setContactMethod(string $contactMethod): static
    {
        $this->contactMethod = $contactMethod;

        return $this;
    }

    public function getContactDetails(): ?string
    {
        return $this->contactDetails;
    }

    public function setContactDetails(?string $contactDetails): static
    {
        $this->contactDetails = $contactDetails;

        return $this;
    }

    /**
     * @return Collection<int, WireguardPeer>
     */
    public function getWireguardPeers(): Collection
    {
        return $this->wireguardPeers;
    }

    public function addWireguardPeer(WireguardPeer $wireguardPeer): static
    {
        if (!$this->wireguardPeers->contains($wireguardPeer)) {
            $this->wireguardPeers->add($wireguardPeer);
            $wireguardPeer->setUserID($this);
        }

        return $this;
    }

    public function removeWireguardPeer(WireguardPeer $wireguardPeer): static
    {
        if ($this->wireguardPeers->removeElement($wireguardPeer)) {
            // set the owning side to null (unless already changed)
            if ($wireguardPeer->getUserID() === $this) {
                $wireguardPeer->setUserID(null);
            }
        }

        return $this;
    }
}
