<?php

namespace Source\Controller;

use Source\Controller\Api;
use Source\Models\Faqs\Faq;
use Source\Models\Faqs\FaqCategory;

class Faqs extends Api
{
    public function listAll(array $data): void
    {
        $faq = new Faq();
        $this->call(200, "success", "Lista de Perguntas Frequentes", "success")
            ->back($faq->selectAll(["active = 1"]));
    }

    public function listAllAdmin(array $data): void
    {
        if (!$this->authToken(1)) {
            $this->call(401, "unauthorized", "Usuário não está autenticado (sem token ou token inválido).", "error")->back();
            return;
        }

        $faq = new Faq();
        $this->call(200, "success", "Lista administrativa de Perguntas Frequentes", "success")
            ->back($faq->selectAll());
    }

    public function listById(array $data): void
    {
        if (!isset($data["faqId"]) || empty($data["faqId"]) || !filter_var($data["faqId"], FILTER_VALIDATE_INT)) {
            $this->call(400, "bad_request", "ID da FAQ é obrigatório e deve ser um número inteiro", "error")->back();
            return;
        }

        $faq = new Faq();
        if (!$faq->selectById((int)$data["faqId"])) {
            $this->call(404, "not_found", "FAQ não encontrada", "error")->back();
            return;
        }

        // FAQ despublicada só aparece para o administrador global.
        if ($faq->getActive() !== 1 && !$this->authToken(1)) {
            $this->call(404, "not_found", "FAQ não encontrada", "error")->back();
            return;
        }

        $this->call(200, "success", "FAQ encontrada", "success")->back($this->responseData($faq));
    }

    public function listByCategory(array $data): void
    {
        if (!isset($data["categoryId"]) || empty($data["categoryId"]) || !filter_var($data["categoryId"], FILTER_VALIDATE_INT)) {
            $this->call(400, "bad_request", "ID da categoria é obrigatório e deve ser um número inteiro", "error")->back();
            return;
        }

        $faq = new Faq();
        $categoryId = (int)$data["categoryId"];
        $this->call(200, "success", "Lista de FAQs por categoria", "success")
            ->back($faq->selectAll(["active = 1", "faqs_category_id = {$categoryId}"]));
    }

    public function insert(array $data): void
    {
        if (!$this->authToken(1)) {
            $this->call(401, "unauthorized", "Usuário não está autenticado (sem token ou token inválido).", "error")->back();
            return;
        }

        if (!isset($data["faqs_category_id"]) || empty($data["faqs_category_id"]) || !filter_var($data["faqs_category_id"], FILTER_VALIDATE_INT)) {
            $this->call(400, "bad_request", "O campo faqs_category_id é obrigatório e deve ser um número inteiro", "error")->back();
            return;
        }

        if (!$this->categoryExists((int)$data["faqs_category_id"])) {
            $this->call(400, "bad_request", "A categoria informada não existe ou está inativa", "error")->back();
            return;
        }

        if (!isset($data["question"]) || empty(trim($data["question"]))) {
            $this->call(400, "bad_request", "O campo question é obrigatório", "error")->back();
            return;
        }

        if (!isset($data["answer"]) || empty(trim($data["answer"]))) {
            $this->call(400, "bad_request", "O campo answer é obrigatório", "error")->back();
            return;
        }

        $active = 1;
        if (array_key_exists("active", $data)) {
            $active = filter_var($data["active"], FILTER_VALIDATE_INT);
            if ($active === false || !in_array($active, [0, 1], true)) {
                $this->call(400, "bad_request", "O campo active deve ser 0 ou 1", "error")->back();
                return;
            }
        }

        $sortOrder = null;
        if (isset($data["sort_order"]) && $data["sort_order"] !== "") {
            $sortOrder = filter_var($data["sort_order"], FILTER_VALIDATE_INT);
            if ($sortOrder === false || $sortOrder < 0) {
                $this->call(400, "bad_request", "O campo sort_order deve ser um número inteiro maior ou igual a zero", "error")->back();
                return;
            }
        }

        $faq = new Faq(null, (int)$data["faqs_category_id"], trim($data["question"]), trim($data["answer"]), $active, $sortOrder);
        if (!$faq->insert()) {
            $this->call(500, "internal_server_error", $faq->getErrorMessage(), "error")->back();
            return;
        }

        $faq->selectById((int)$faq->getId());
        $this->call(201, "created", "FAQ criada com sucesso", "success")->back($this->responseData($faq));
    }

    public function update(array $data): void
    {
        if (!$this->authToken(1)) {
            $this->call(401, "unauthorized", "Usuário não está autenticado (sem token ou token inválido).", "error")->back();
            return;
        }

        if (!isset($data["faqId"]) || !filter_var($data["faqId"], FILTER_VALIDATE_INT)) {
            $this->call(400, "bad_request", "O campo faqId é obrigatório e deve ser um número inteiro", "error")->back();
            return;
        }

        $faq = new Faq();
        if (!$faq->selectById((int)$data["faqId"])) {
            $this->call(404, "not_found", "FAQ não encontrada", "error")->back();
            return;
        }

        if (isset($data["faqs_category_id"]) && !empty($data["faqs_category_id"])) {
            if (!filter_var($data["faqs_category_id"], FILTER_VALIDATE_INT)) {
                $this->call(400, "bad_request", "O campo faqs_category_id deve ser um número inteiro", "error")->back();
                return;
            }
            if (!$this->categoryExists((int)$data["faqs_category_id"])) {
                $this->call(400, "bad_request", "A categoria informada não existe ou está inativa", "error")->back();
                return;
            }
            $faq->setFaqsCategoryId((int)$data["faqs_category_id"]);
        }

        if (isset($data["question"]) && !empty(trim($data["question"]))) {
            $faq->setQuestion(trim($data["question"]));
        }

        if (isset($data["answer"]) && !empty(trim($data["answer"]))) {
            $faq->setAnswer(trim($data["answer"]));
        }

        if (array_key_exists("active", $data)) {
            $active = filter_var($data["active"], FILTER_VALIDATE_INT);
            if ($active === false || !in_array($active, [0, 1], true)) {
                $this->call(400, "bad_request", "O campo active deve ser 0 ou 1", "error")->back();
                return;
            }
            $faq->setActive($active);
        }

        if (isset($data["sort_order"]) && $data["sort_order"] !== "") {
            $sortOrder = filter_var($data["sort_order"], FILTER_VALIDATE_INT);
            if ($sortOrder === false || $sortOrder < 0) {
                $this->call(400, "bad_request", "O campo sort_order deve ser um número inteiro maior ou igual a zero", "error")->back();
                return;
            }
            $faq->setSortOrder($sortOrder);
        }

        if (!$faq->updateById((int)$data["faqId"])) {
            $this->call(500, "internal_server_error", $faq->getErrorMessage(), "error")->back();
            return;
        }

        $this->call(200, "success", "FAQ atualizada com sucesso", "success")->back($this->responseData($faq));
    }

    public function delete(array $data): void
    {
        if (!$this->authToken(1)) {
            $this->call(401, "unauthorized", "Usuário não está autenticado (sem token ou token inválido).", "error")->back();
            return;
        }

        if (!isset($data["faqId"]) || !filter_var($data["faqId"], FILTER_VALIDATE_INT)) {
            $this->call(400, "bad_request", "ID da FAQ é obrigatório e deve ser um número inteiro", "error")->back();
            return;
        }

        $faq = new Faq();
        if (!$faq->softDeleteById((int)$data["faqId"])) {
            $this->call(404, "not_found", "FAQ não encontrada ou já inativa", "error")->back();
            return;
        }

        $this->call(200, "success", "FAQ removida com sucesso", "success")->back();
    }

    private function categoryExists(int $categoryId): bool
    {
        $category = new FaqCategory();
        return $category->selectById($categoryId) && $category->getActive() === 1;
    }

    private function responseData(Faq $faq): array
    {
        return [
            "id" => $faq->getId(),
            "faqs_category_id" => $faq->getFaqsCategoryId(),
            "question" => $faq->getQuestion(),
            "answer" => $faq->getAnswer(),
            "active" => $faq->getActive(),
            "sort_order" => $faq->getSortOrder()
        ];
    }
}
