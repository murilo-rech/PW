<?php

namespace Source\Controller;

use Source\Controller\Api;
use Source\Models\Address;

class Addresses extends Api
{
    // Cada usuário autenticado gerencia somente os próprios endereços.
    private const ALLOWED_TYPES = [1, 2, 3, 4];

    public function listById(array $data): void
    {
        if (!$this->authToken(self::ALLOWED_TYPES)) {
            $this->call(401, "unauthorized", "Usuário não está autenticado (sem token ou token inválido).", "error")->back();
            return;
        }

        if (!$this->validateId($data, "address_id")) {
            $this->call(400, "bad_request", "ID do endereço é obrigatório e deve ser um número inteiro", "error")->back();
            return;
        }

        $address = new Address();
        if (!$address->selectById((int)$data["address_id"])) {
            $this->call(404, "not_found", "Endereço não encontrado", "error")->back();
            return;
        }

        if ($address->getUserId() !== $this->userAuthId) {
            $this->call(403, "forbidden", "Usuário sem permissão para acessar este endereço.", "error")->back();
            return;
        }

        $this->call(200, "success", "Endereço encontrado", "success")->back($this->responseData($address));
    }

    public function listAll(array $data): void
    {
        if (!$this->authToken(self::ALLOWED_TYPES)) {
            $this->call(401, "unauthorized", "Usuário não está autenticado (sem token ou token inválido).", "error")->back();
            return;
        }

        $address = new Address();
        $this->call(200, "success", "Lista de Endereços", "success")->back(
            $address->selectAll(["user_id = " . (int)$this->userAuthId, "active = 1"])
        );
    }

    public function listPaginator(array $data): void
    {
        if (!$this->authToken(self::ALLOWED_TYPES)) {
            $this->call(401, "unauthorized", "Usuário não está autenticado (sem token ou token inválido).", "error")->back();
            return;
        }

        if (!$this->validateId($data, "page") || !$this->validateId($data, "per_page")) {
            $this->call(400, "bad_request", "Os campos page e per_page são obrigatórios e devem ser números inteiros", "error")->back();
            return;
        }

        $address = new Address();
        $response = $address->selectPaginator(
            (int)$data["page"],
            (int)$data["per_page"],
            ["user_id = " . (int)$this->userAuthId, "active = 1"],
            "id",
            "ASC"
        );

        $this->call(200, "success", "Lista de Endereços com Paginação", "success")->back($response);
    }

    public function insert(array $data): void
    {
        if (!$this->authToken(self::ALLOWED_TYPES)) {
            $this->call(401, "unauthorized", "Usuário não está autenticado (sem token ou token inválido).", "error")->back();
            return;
        }


        if (!$this->validate($data)) {
            $this->call(400, "bad_request", "Os campos street e number são obrigatórios", "error")->back();
            return;
        }

        $address = new Address(
            null,
            (int)$this->userAuthId,
            trim($data["street"]),
            trim($data["number"]),
            isset($data["active"]) ? (int)$data["active"] : 1
        );

        if (!$address->insert()) {
            $this->call(500, "internal_server_error", $address->getErrorMessage(), "error")->back();
            return;
        }

        $address->selectById((int)$address->getId());
        $this->call(201, "success", "Endereço inserido com sucesso", "created")->back($this->responseData($address));
    }

    public function update(array $data): void
    {
        if (!$this->authToken(self::ALLOWED_TYPES)) {
            $this->call(401, "unauthorized", "Usuário não está autenticado (sem token ou token inválido).", "error")->back();
            return;
        }


        if (!$this->validateId($data, "address_id") || !$this->validate($data)) {
            $this->call(400, "bad_request", "ID inválido ou campos obrigatórios ausentes", "error")->back();
            return;
        }

        $existing = new Address();
        if (!$existing->selectById((int)$data["address_id"])) {
            $this->call(404, "not_found", "Endereço não encontrado", "error")->back();
            return;
        }

        if ($existing->getUserId() !== $this->userAuthId) {
            $this->call(403, "forbidden", "Usuário sem permissão para atualizar este endereço.", "error")->back();
            return;
        }

        $address = new Address(
            null,
            (int)$this->userAuthId,
            trim($data["street"]),
            trim($data["number"]),
            isset($data["active"]) ? (int)$data["active"] : null
        );

        if (!$address->updateById((int)$data["address_id"])) {
            $this->call(500, "internal_server_error", $address->getErrorMessage(), "error")->back();
            return;
        }

        $address->selectById((int)$data["address_id"]);
        $this->call(200, "success", "Endereço atualizado com sucesso", "success")->back($this->responseData($address));
    }

    public function delete(array $data): void
    {
        if (!$this->authToken(self::ALLOWED_TYPES)) {
            $this->call(401, "unauthorized", "Usuário não está autenticado (sem token ou token inválido).", "error")->back();
            return;
        }

        if (!$this->validateId($data, "address_id")) {
            $this->call(400, "bad_request", "ID do endereço é obrigatório e deve ser um número inteiro", "error")->back();
            return;
        }

        $address = new Address();
        if (!$address->selectById((int)$data["address_id"])) {
            $this->call(404, "not_found", "Endereço não encontrado", "error")->back();
            return;
        }

        if ($address->getUserId() !== $this->userAuthId) {
            $this->call(403, "forbidden", "Usuário sem permissão para excluir este endereço.", "error")->back();
            return;
        }

        if (!$address->softDeleteById((int)$data["address_id"])) {
            $this->call(404, "not_found", "Endereço não encontrado", "error")->back();
            return;
        }

        $this->call(200, "success", "Endereço excluído com sucesso", "success")->back();
    }

    private function validate(array $data): bool
    {
        if (
            !isset($data["street"], $data["number"])
            || empty(trim($data["street"]))
            || empty(trim($data["number"]))
            || strlen(trim($data["street"])) > 150
            || strlen(trim($data["number"])) > 20
        ) {
            return false;
        }

        return !isset($data["active"]) || $this->validateActive($data["active"]);
    }

    private function validateId(array $data, string $field): bool
    {
        return isset($data[$field])
            && filter_var($data[$field], FILTER_VALIDATE_INT) !== false
            && (int)$data[$field] > 0;
    }

    private function validateActive(mixed $active): bool
    {
        return filter_var($active, FILTER_VALIDATE_INT) !== false
            && in_array((int)$active, [0, 1], true);
    }

    private function responseData(Address $address): array
    {
        return [
            "id" => $address->getId(),
            "user_id" => $address->getUserId(),
            "street" => $address->getStreet(),
            "number" => $address->getNumber(),
            "active" => $address->getActive()
        ];
    }
}
