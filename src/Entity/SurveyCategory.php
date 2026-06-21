<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Verknuepft eine Umfrage mit einer Kategorie (n:m ueber einfache Fremdschluessel,
 * im gleichen Stil wie SurveyHistory).
 */
#[ORM\Entity]
#[ORM\Table(name: 'survey_category')]
class SurveyCategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $survey_id = null;

    #[ORM\Column]
    private ?int $category_id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSurveyId(): ?int
    {
        return $this->survey_id;
    }

    public function setSurveyId(int $survey_id): static
    {
        $this->survey_id = $survey_id;

        return $this;
    }

    public function getCategoryId(): ?int
    {
        return $this->category_id;
    }

    public function setCategoryId(int $category_id): static
    {
        $this->category_id = $category_id;

        return $this;
    }
}