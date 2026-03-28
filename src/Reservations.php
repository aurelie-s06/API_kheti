<?php

namespace App;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[Entity]
#[Table(name: 'kheti_reservations')]
class Reservations
{
    #[Id]
    #[Column(type: 'integer')]
    #[GeneratedValue]
    private int $id_reservation;

    #[Column(type: 'string', length: 100)]
    private string $email = '';

    #[Column(type: 'string')]
    private string $day = '';

    #[Column(type: 'string')]
    private string $hour;

    #[Column(type: 'string')]
    private string $price;

    #[Column(type: 'integer')]
    private int $adult_count = 0;

    #[Column(type: 'integer')]
    private int $child_count = 0;

    #[Column(type: 'integer')]
    private int $student_count = 0;

    public function getId(): int
    {
        return $this->id_reservation;
    }

    public function getDay(): string
    {
        return $this->day;
    }

    public function setDay(string $day): self
    {
        $this->day = $day;
        return $this;
    }

    public function getHour(): string
    {
        return $this->hour;
    }

    public function setHour(string $hour): self
    {
        $this->hour = $hour;
        return $this;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function setPrice(string $price): self
    {
        $this->price = $price;
        return $this;
    }

    public function getAdultCount(): int
    {
        return $this->adult_count;
    }

    public function setAdultCount(int $adult_count): self
    {
        $this->adult_count = $adult_count;
        return $this;
    }

    public function getChildCount(): int
    {
        return $this->child_count;
    }

    public function setChildCount(int $child_count): self
    {
        $this->child_count = $child_count;
        return $this;
    }

    public function getStudentCount(): int
    {
        return $this->student_count;
    }

    public function setStudentCount(int $student_count): self
    {
        $this->student_count = $student_count;
        return $this;
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
}
