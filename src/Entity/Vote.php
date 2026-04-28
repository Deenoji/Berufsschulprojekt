<?php

namespace App\Entity;

use App\Repository\VoteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VoteRepository::class)]
class Vote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $survey_id = null;

    #[ORM\Column]
    private ?int $votes = null;

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

    public function getVotes(): ?int
    {
        return $this->votes;
    }

    public function setVotes(int $votes): static
    {
        $this->votes = $votes;

        return $this;
    }
}
