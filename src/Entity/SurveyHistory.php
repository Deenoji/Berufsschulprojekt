<?php

namespace App\Entity;

use App\Repository\SurveyHistoryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SurveyHistoryRepository::class)]
class SurveyHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $survey_id = null;

    #[ORM\Column]
    private ?int $user_id = null;

    #[ORM\Column]
    private ?int $selected_option_id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
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

    public function getUserId(): ?int
    {
        return $this->user_id;
    }

    public function setUserId(int $user_id): static
    {
        $this->user_id = $user_id;

        return $this;
    }

    public function getSelectedOptionId(): ?int
    {
        return $this->selected_option_id;
    }

    public function setSelectedOptionId(int $selected_option_id): static
    {
        $this->selected_option_id = $selected_option_id;

        return $this;
    }
}
