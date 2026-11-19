<?php

namespace Source\Models;

use PDO;
use PDOException;
use Source\Core\Connect;
use Source\Core\Model;

class AppointmentImage extends Model
{
    private ?int $id;
    private ?int $appointmentId;
    private ?string $imagePath;
    private ?string $altText;
    private ?int $position;
    private ?string $createdAt = null;

    public function __construct(
        ?int $id = null,
        ?int $appointmentId = null,
        ?string $imagePath = null,
        ?string $altText = null,
        ?int $position = 0
    ) {
        $this->id = $id;
        $this->appointmentId = $appointmentId;
        $this->imagePath = $imagePath;
        $this->altText = $altText;
        $this->position = $position;

        $this->table = 'appointment_images'; // nome da tabela do banco
        $this->primaryKey = 'id'; // nome da chave primária da tabela
        $this->fillable = ['appointmentId', 'imagePath', 'altText', 'position']; // camelCase
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getAppointmentId(): ?int
    {
        return $this->appointmentId;
    }

    public function setAppointmentId(?int $appointmentId): void
    {
        $this->appointmentId = $appointmentId;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(?string $imagePath): void
    {
        $this->imagePath = $imagePath;
    }

    public function getAltText(): ?string
    {
        return $this->altText;
    }

    public function setAltText(?string $altText): void
    {
        $this->altText = $altText;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): void
    {
        $this->position = $position;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?string $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function listByAppointmentId(int $appointmentId): array|false
    {
        try {
            $stmt = Connect::getInstance()->prepare(
                "SELECT id, appointment_id, image_path, alt_text, position, created_at
                 FROM {$this->table}
                 WHERE appointment_id = :appointment_id
                 ORDER BY position ASC, id ASC"
            );
            $stmt->bindValue(':appointment_id', $appointmentId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $exception) {
            $this->errorMessage = $exception->getMessage();
            return false;
        }
    }

    public function nextPosition(int $appointmentId): int
    {
        try {
            $stmt = Connect::getInstance()->prepare(
                "SELECT COALESCE(MAX(position), -1) + 1 FROM {$this->table} WHERE appointment_id = :appointment_id"
            );
            $stmt->bindValue(':appointment_id', $appointmentId, PDO::PARAM_INT);
            $stmt->execute();
            return (int)$stmt->fetchColumn();
        } catch (PDOException $exception) {
            $this->errorMessage = $exception->getMessage();
            return 0;
        }
    }
}
