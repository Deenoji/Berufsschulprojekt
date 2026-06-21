<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'user')]
#[ORM\UniqueConstraint(name: 'UNIQ_USER_EMAIL', columns: ['email'])]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 60, unique: true)]
    private string $email = '';

    // Anzeigename aus der Registrierung (optional, Fallback ist die E-Mail).
    #[ORM\Column(length: 60, nullable: true)]
    private ?string $username = null;

    // In der Migration heisst die Spalte "role" (Einzahl).
    #[ORM\Column(length: 16)]
    private string $role = 'ROLE_USER';

    #[ORM\Column(length: 255)]
    private string $password = '';

    #[ORM\Column(name: 'vote_count')]
    private int $voteCount = 0;

    // NEU: offene Aufstufungsanfrage (Nutzer -> Kunde).
    #[ORM\Column(name: 'upgrade_requested')]
    private bool $upgradeRequested = false;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Eindeutiger Identifier fuer das Security-System (hier: die E-Mail).
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): self
    {
        $this->username = $username;

        return $this;
    }

    /**
     * Anzeigename: Benutzername, falls gesetzt, sonst die E-Mail.
     */
    public function getDisplayName(): string
    {
        return $this->username ?: $this->email;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): self
    {
        $this->role = $role;

        return $this;
    }

    /**
     * @see UserInterface
     *
     * @return string[]
     */
    public function getRoles(): array
    {
        // Jeder eingeloggte Nutzer hat garantiert ROLE_USER.
        return array_values(array_unique([$this->role, 'ROLE_USER']));
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getVoteCount(): int
    {
        return $this->voteCount;
    }

    public function setVoteCount(int $voteCount): self
    {
        $this->voteCount = $voteCount;

        return $this;
    }

    public function isUpgradeRequested(): bool
    {
        return $this->upgradeRequested;
    }

    public function setUpgradeRequested(bool $upgradeRequested): self
    {
        $this->upgradeRequested = $upgradeRequested;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // Falls temporaere, sensible Daten am User haengen, hier loeschen.
    }
}