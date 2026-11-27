<?php

namespace Source\Controller;

use Source\Controller\Api;
use Source\Models\Contact;

class Contacts extends Api
{
    private const SUBJECTS = ["demo", "planos", "suporte", "parceria", "outro"];

    public function listAll(array $data): void
    {
        if (!$this->authToken(1)) {
            $this->call(401, "unauthorized", "Usuário não está autenticado (sem token ou token inválido).", "error")->back();
            return;
        }

        $contact = new Contact();
        $this->call(200, "success", "Lista de mensagens de contato", "success")
            ->back($contact->selectAll(["active = 1"]));
    }

    public function insert(array $data): void
    {
        if (!$this->validate($data)) {
            $this->call(
                400,
                "bad_request",
                "Os campos name, email, subject e message são obrigatórios. O e-mail deve ser válido.",
                "error"
            )->back();
            return;
        }

        $contact = new Contact(
            null,
            trim($data["name"]),
            trim($data["email"]),
            $data["subject"],
            trim($data["message"]),
            1
        );

        if (!$contact->insert()) {
            $this->call(500, "internal_server_error", "Não foi possível enviar a mensagem. Tente novamente.", "error")->back();
            return;
        }

        $this->call(201, "success", "Mensagem enviada! Retornaremos em breve.", "created")->back([
            "id" => $contact->getId()
        ]);
    }

    public function delete(array $data): void
    {
        if (!$this->authToken(1)) {
            $this->call(401, "unauthorized", "Usuário não está autenticado (sem token ou token inválido).", "error")->back();
            return;
        }

        if (!isset($data["contact_id"]) || !filter_var($data["contact_id"], FILTER_VALIDATE_INT)) {
            $this->call(400, "bad_request", "ID da mensagem é obrigatório e deve ser um número inteiro", "error")->back();
            return;
        }

        $contact = new Contact();
        if (!$contact->softDeleteById((int)$data["contact_id"])) {
            $this->call(404, "not_found", "Mensagem não encontrada ou já arquivada", "error")->back();
            return;
        }

        $this->call(200, "success", "Mensagem arquivada com sucesso", "success")->back();
    }

    private function validate(array $data): bool
    {
        return isset($data["name"], $data["email"], $data["subject"], $data["message"])
            && is_string($data["name"]) && trim($data["name"]) !== "" && strlen(trim($data["name"])) <= 150
            && is_string($data["email"]) && strlen(trim($data["email"])) <= 150
            && filter_var(trim($data["email"]), FILTER_VALIDATE_EMAIL)
            && in_array($data["subject"], self::SUBJECTS, true)
            && is_string($data["message"]) && trim($data["message"]) !== "" && strlen($data["message"]) <= 5000;
    }
}
