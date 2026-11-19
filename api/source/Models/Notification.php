<?php

namespace Source\Models;

use PDO;
use PDOException;
use Source\Core\Connect;
use Source\Core\Model;

class Notification extends Model
{
    private ?int $id;
    private ?int $userId;
    private ?string $title;
    private ?string $message;
    private ?int $isRead;
    private ?string $createdAt = null;

    public function __construct(
        ?int $id = null,
        ?int $userId = null,
        ?string $title = null,
        ?string $message = null,
        ?int $isRead = 0
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->title = $title;
        $this->message = $message;
        $this->isRead = $isRead;

        $this->table = 'notifications'; // nome da tabela do banco
        $this->primaryKey = 'id'; // nome da chave primária da tabela
        $this->fillable = ['userId', 'title', 'message', 'isRead']; // camelCase
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(?int $userId): void
    {
        $this->userId = $userId;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): void
    {
        $this->message = $message;
    }

    public function getIsRead(): ?int
    {
        return $this->isRead;
    }

    public function setIsRead(?int $isRead): void
    {
        $this->isRead = $isRead;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?string $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    // Cria uma notificação para um usuário. Uma falha aqui não deve desfazer a operação principal.
    public static function notify(int $userId, string $title, string $message): bool
    {
        $notification = new self(null, $userId, $title, $message, 0);
        if (!$notification->insert()) {
            error_log("Não foi possível registrar a notificação: " . ($notification->getErrorMessage() ?? "erro desconhecido"));
            return false;
        }
        return true;
    }

    public function listForUser(int $userId): array|false
    {
        try {
            $stmt = Connect::getInstance()->prepare(
                "SELECT id, title, message, is_read, created_at FROM {$this->table} WHERE user_id = :user_id ORDER BY created_at DESC, id DESC"
            );
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $exception) {
            $this->errorMessage = $exception->getMessage();
            return false;
        }
    }

    public function markRead(int $id, int $userId): bool
    {
        try {
            $stmt = Connect::getInstance()->prepare(
                "UPDATE {$this->table} SET is_read = 1 WHERE id = :id AND user_id = :user_id"
            );
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                return true;
            }

            // A notificação já lida não altera linhas; confere se ela pertence ao usuário.
            $check = Connect::getInstance()->prepare(
                "SELECT id FROM {$this->table} WHERE id = :id AND user_id = :user_id LIMIT 1"
            );
            $check->bindValue(':id', $id, PDO::PARAM_INT);
            $check->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $check->execute();
            if (!$check->fetch()) {
                $this->errorMessage = "Notificação não encontrada para este usuário.";
                return false;
            }
            return true;
        } catch (PDOException $exception) {
            $this->errorMessage = $exception->getMessage();
            return false;
        }
    }

    public function markAllRead(int $userId): bool
    {
        try {
            $stmt = Connect::getInstance()->prepare(
                "UPDATE {$this->table} SET is_read = 1 WHERE user_id = :user_id AND is_read = 0"
            );
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $exception) {
            $this->errorMessage = $exception->getMessage();
            return false;
        }
    }
}
