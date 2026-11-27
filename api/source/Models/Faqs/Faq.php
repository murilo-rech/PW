<?php

namespace Source\Models\Faqs;

use PDOException;
use Source\Core\Connect;
use Source\Core\Model;

class Faq extends Model
{
    private ?int $id;
    private ?int $faqsCategoryId;
    private ?string $question;
    private ?string $answer;
    private ?int $active;
    private ?int $sortOrder;

    public function __construct(
        ?int $id = null,
        ?int $faqsCategoryId = null,
        ?string $question = null,
        ?string $answer = null,
        ?int $active = 1,
        ?int $sortOrder = null
    ) {
        $this->id = $id;
        $this->faqsCategoryId = $faqsCategoryId;
        $this->question = $question;
        $this->answer = $answer;
        $this->active = $active;
        $this->sortOrder = $sortOrder;

        $this->table = 'faqs';
        $this->primaryKey = 'id';
        $this->fillable = ['faqsCategoryId', 'question', 'answer', 'active', 'sortOrder'];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getFaqsCategoryId(): ?int
    {
        return $this->faqsCategoryId;
    }

    public function setFaqsCategoryId(int $faqsCategoryId): void
    {
        $this->faqsCategoryId = $faqsCategoryId;
    }

    public function getQuestion(): ?string
    {
        return $this->question;
    }

    public function setQuestion(string $question): void
    {
        $this->question = $question;
    }

    public function getAnswer(): ?string
    {
        return $this->answer;
    }

    public function setAnswer(string $answer): void
    {
        $this->answer = $answer;
    }

    public function getActive(): ?int
    {
        return $this->active;
    }

    public function setActive(int $active): void
    {
        $this->active = $active;
    }

    public function getSortOrder(): ?int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): void
    {
        $this->sortOrder = $sortOrder;
    }

    // As FAQs são exibidas na ordem definida pelo administrador (sort_order).
    public function selectAll(array $filters = [], ?string $orderBy = 'sort_order', string $direction = 'ASC'): array
    {
        try {
            $query = "SELECT * FROM {$this->table}";
            if (!empty($filters)) {
                $query .= " WHERE " . implode(" AND ", $filters);
            }
            $query .= " ORDER BY sort_order ASC, id ASC";

            $stmt = Connect::getInstance()->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->errorMessage = $e->getMessage();
            return [];
        }
    }
}
