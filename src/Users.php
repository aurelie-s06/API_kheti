<?php

namespace App;

/* use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection; */
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
/* use Doctrine\ORM\Mapping\OneToMany; */
use Doctrine\ORM\Mapping\Table;

#[Entity]
#[Table(name: 'kheti_users')]
class Users
{
    #[Id]
    #[Column(type: 'string', length: 100)]
    private string $email;

    #[Column(type: 'string')]
    private string $name;

    #[Column(type: 'string')]
    private string $first_name;

    #[Column(type: 'string')]
    private string $password;

    #[Column(type: 'integer')]
    private int $admin_state = 0;

    public function getId(): string
    {
        return $this->email;
    }

    public function getNom(): string
    {
        return $this->name;
    }

    public function setNom(string $nom): self
    {
        $this->name = $nom;
        return $this;
    }

    public function getPrenom(): string
    {
        return $this->first_name;
    }

    public function setPrenom(string $prenom): self
    {
        $this->first_name = $prenom;
        return $this;
    }

    public function getMail(): string
    {
        return $this->email;
    }

    public function setMail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getMotDePasse(): string
    {
        return $this->password;
    }

    public function setMotDePasse(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function getAdminState(): int
    {
        return $this->admin_state;
    }

    public function setAdminState(int $admin_state): self
    {
        $this->admin_state = $admin_state;
        return $this;
    }
}
