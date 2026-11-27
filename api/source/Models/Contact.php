<?php

namespace Source\Models;

use Source\Core\Model;

class Contact extends Model
{
    private ?int $id;
    private ?string $name;
    private ?string $email;
    private ?string $subject;
    private ?string $message;
    private ?int $active;
    private ?string $createdAt = null;

    public function __construct(
        ?int $id = null,
        ?string $name = null,
        ?string $email = null,
        ?string $subject = null,
        ?string $message = null,
        ?int $active = 1
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
        $this->subject = $subject;
        $this->message = $message;
        $this->active = $active;

        $this->table = 'contacts'; // nome da tabela do banco
        $this->primaryKey = 'id'; // nome da chave primária da tabela
        $this->fillable = ['name', 'email', 'subject', 'message', 'active']; // camelCase
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(?string $subject): void
    {
        $this->subject = $subject;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): void
    {
        $this->message = $message;
    }

    public function getActive(): ?int
    {
        return $this->active;
    }

    public function setActive(?int $active): void
    {
        $this->active = $active;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?string $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
}
