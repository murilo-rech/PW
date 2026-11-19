<?php

namespace Source\Controller;

use Source\Models\Notification;

class Notifications extends Api
{
    private const ALLOWED_TYPES = [1, 2, 3, 4];

    public function listAll(array $data): void
    {
        if (!$this->authToken(self::ALLOWED_TYPES)) {
            $this->call(401, "unauthorized", "Usuário não está autenticado (sem token ou token inválido).", "error")->back();
            return;
        }
        $notifications = new Notification();
        $rows = $notifications->listForUser((int)$this->userAuthId);
        if ($rows === false) {
            $this->call(500, "internal_server_error", "Não foi possível consultar as notificações.", "error")->back();
            return;
        }
        $this->call(200, "success", "Lista de notificações", "success")->back($rows);
    }

    public function markRead(array $data): void
    {
        if (!$this->authToken(self::ALLOWED_TYPES)) {
            $this->call(401, "unauthorized", "Usuário não está autenticado (sem token ou token inválido).", "error")->back();
            return;
        }
        if (!$this->validateId($data, "notification_id")) {
            $this->call(400, "bad_request", "ID da notificação inválido.", "error")->back();
            return;
        }
        $notifications = new Notification();
        if (!$notifications->markRead((int)$data["notification_id"], (int)$this->userAuthId)) {
            $this->call(404, "not_found", "Notificação não encontrada para este usuário.", "error")->back();
            return;
        }
        $this->call(200, "success", "Notificação marcada como lida.", "success")->back();
    }

    public function markAllRead(array $data): void
    {
        if (!$this->authToken(self::ALLOWED_TYPES)) {
            $this->call(401, "unauthorized", "Usuário não está autenticado (sem token ou token inválido).", "error")->back();
            return;
        }
        $notifications = new Notification();
        if (!$notifications->markAllRead((int)$this->userAuthId)) {
            $this->call(500, "internal_server_error", "Não foi possível atualizar as notificações.", "error")->back();
            return;
        }
        $this->call(200, "success", "Notificações marcadas como lidas.", "success")->back();
    }

    private function validateId(array $data, string $field): bool
    {
        return isset($data[$field])
            && filter_var($data[$field], FILTER_VALIDATE_INT) !== false
            && (int)$data[$field] > 0;
    }
}
