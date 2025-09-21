<?php

namespace App\Entity;

use App\Entity\Car;
use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Reservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type:'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Car::class, inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Car $car = null;

    #[ORM\Column(type:'datetime')]
    private \DateTimeInterface $startDate;

    #[ORM\Column(type:'datetime')]
    private \DateTimeInterface $endDate;

    #[ORM\Column(type:'string', length:20, options: ['default' => 'pending'])]
    private string $status = 'pending'; // pending | accepted | rejected | completed

    #[ORM\Column(type:'text', nullable:true)]
    private ?string $comment = null;

    #[ORM\Column(type:'datetime')]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getCar(): ?Car { return $this->car; }
    public function setCar(?Car $car): self { $this->car = $car; return $this; }

    public function getStartDate(): \DateTimeInterface { return $this->startDate; }
    public function setStartDate(\DateTimeInterface $startDate): self { $this->startDate = $startDate; return $this; }

    public function getEndDate(): \DateTimeInterface { return $this->endDate; }
    public function setEndDate(\DateTimeInterface $endDate): self { $this->endDate = $endDate; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    public function getComment(): ?string { return $this->comment; }
    public function setComment(?string $comment): self { $this->comment = $comment; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): self { $this->createdAt = $createdAt; return $this; }
}
