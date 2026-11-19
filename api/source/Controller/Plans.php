<?php

namespace Source\Controller;

use Source\Controller\Api;
use Source\Models\Plan;

class Plans extends Api
{
    public function listActive(array $data): void
    {
        $plan = new Plan();
        $this->call(200, "success", "Lista de Planos ativos", "success")->back($plan->selectAll(["active = 1"]));
    }

    public function listAll(array $data): void
    {
        if (!$this->authToken(1)) {
            $this->call(401, "unauthorized", "Usuário não está autenticado (sem token ou token inválido).", "error")->back();
            return;
        }

        $plan = new Plan();
        $this->call(200, "success", "Lista de Planos", "success")->back($plan->selectAll());
    }

    public function listById(array $data): void
    {
        if (!$this->authToken(1)) {
            $this->call(401, "unauthorized", "Usuário não está autenticado (sem token ou token inválido).", "error")->back();
            return;
        }

        if (!$this->validateId($data, "plan_id")) {
            $this->call(400, "bad_request", "ID do plano é obrigatório e deve ser um número inteiro", "error")->back();
            return;
        }

        $plan = new Plan();
        if (!$plan->selectById((int)$data["plan_id"])) {
            $this->call(404, "not_found", "Plano não encontrado", "error")->back();
            return;
        }

        $this->call(200, "success", "Plano encontrado", "success")->back($this->responseData($plan));
    }

    public function listPaginator(array $data): void
    {
        if (!$this->authToken(1)) {
            $this->call(401, "unauthorized", "Usuário não está autenticado (sem token ou token inválido).", "error")->back();
            return;
        }

        if (!$this->validateId($data, "page") || !$this->validateId($data, "per_page")) {
            $this->call(400, "bad_request", "Os campos page e per_page são obrigatórios e devem ser números inteiros", "error")->back();
            return;
        }

        $plan = new Plan();
        $response = $plan->selectPaginator((int)$data["page"], (int)$data["per_page"], [], "id", "ASC");
        $this->call(200, "success", "Lista de Planos com Paginação", "success")->back($response);
    }

    public function insert(array $data): void
    {
        if (!$this->authToken(1)) {
            $this->call(401, "unauthorized", "Usuário não está autenticado (sem token ou token inválido).", "error")->back();
            return;
        }

        $data = $this->normalize($data);

        if (!$this->validate($data)) {
            $this->call(400, "bad_request", "Os campos name, price, max_students e max_teachers são obrigatórios", "error")->back();
            return;
        }

        $plan = new Plan(
            null,
            trim($data["name"]),
            $data["description"] ?? null,
            (float)$data["price"],
            (int)$data["max_students"],
            (int)$data["max_teachers"],
            isset($data["active"]) ? (int)$data["active"] : 1
        );

        if (!$plan->insert()) {
            $this->call(500, "internal_server_error", $plan->getErrorMessage(), "error")->back();
            return;
        }

        $plan->selectById((int)$plan->getId());
        $this->call(201, "success", "Plano inserido com sucesso", "created")->back($this->responseData($plan));
    }

    public function update(array $data): void
    {
        if (!$this->authToken(1)) {
            $this->call(401, "unauthorized", "Usuário não está autenticado (sem token ou token inválido).", "error")->back();
            return;
        }

        $data = $this->normalize($data);

        if (!$this->validateId($data, "plan_id") || !$this->validate($data)) {
            $this->call(400, "bad_request", "ID inválido ou campos obrigatórios ausentes", "error")->back();
            return;
        }

        $existing = new Plan();
        if (!$existing->selectById((int)$data["plan_id"])) {
            $this->call(404, "not_found", "Plano não encontrado", "error")->back();
            return;
        }

        $plan = new Plan(
            null,
            trim($data["name"]),
            $data["description"] ?? null,
            (float)$data["price"],
            (int)$data["max_students"],
            (int)$data["max_teachers"],
            isset($data["active"]) ? (int)$data["active"] : null
        );

        if (!$plan->updateById((int)$data["plan_id"])) {
            $this->call(500, "internal_server_error", $plan->getErrorMessage(), "error")->back();
            return;
        }

        $plan->selectById((int)$data["plan_id"]);
        $this->call(200, "success", "Plano atualizado com sucesso", "success")->back($this->responseData($plan));
    }

    public function delete(array $data): void
    {
        if (!$this->authToken(1)) {
            $this->call(401, "unauthorized", "Usuário não está autenticado (sem token ou token inválido).", "error")->back();
            return;
        }

        if (!$this->validateId($data, "plan_id")) {
            $this->call(400, "bad_request", "ID do plano é obrigatório e deve ser um número inteiro", "error")->back();
            return;
        }

        $plan = new Plan();
        if (!$plan->softDeleteById((int)$data["plan_id"])) {
            $this->call(404, "not_found", "Plano não encontrado", "error")->back();
            return;
        }

        $this->call(200, "success", "Plano excluído com sucesso", "success")->back();
    }

    private function validate(array $data): bool
    {
        return isset($data["name"], $data["price"], $data["max_students"], $data["max_teachers"])
            && !empty(trim($data["name"]))
            && filter_var($data["price"], FILTER_VALIDATE_FLOAT) !== false
            && filter_var($data["max_students"], FILTER_VALIDATE_INT) !== false
            && filter_var($data["max_teachers"], FILTER_VALIDATE_INT) !== false;
    }

    private function validateId(array $data, string $field): bool
    {
        return isset($data[$field]) && filter_var($data[$field], FILTER_VALIDATE_INT) && (int)$data[$field] > 0;
    }

    private function normalize(array $data): array
    {
        if (isset($data["limit_students"]) && !isset($data["max_students"])) {
            $data["max_students"] = $data["limit_students"];
        }
        if (isset($data["limit_teachers"]) && !isset($data["max_teachers"])) {
            $data["max_teachers"] = $data["limit_teachers"];
        }
        return $data;
    }

    private function responseData(Plan $plan): array
    {
        return [
            "id" => $plan->getId(),
            "name" => $plan->getName(),
            "description" => $plan->getDescription(),
            "price" => $plan->getPrice(),
            "max_students" => $plan->getMaxStudents(),
            "max_teachers" => $plan->getMaxTeachers(),
            "active" => $plan->getActive()
        ];
    }
}
