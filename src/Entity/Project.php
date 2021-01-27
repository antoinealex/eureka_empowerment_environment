<?php

namespace App\Entity;

use App\Repository\ProjectRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass=ProjectRepository::class)
 */
class Project
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @Assert\Type(type="numeric", message=" id is not valid")
     */
    private ?int $id;

    /**
     * @ORM\Column(type="string", length=50)
     * @Assert\NotBlank(message="the title is required")
     * @Assert\Length(min="2", max="50",
     *     minMessage="the firstname must be at least 2 characters long",
     *     maxMessage="the firstname must not exceed 50 characters")
     */
    private ?string $title;

    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\NotBlank(message="the description is required")
     * @Assert\Length(min="2", max="255",
     *     minMessage="the firstname must be at least 2 characters long",
     *     maxMessage="the firstname must not exceed 255 characters")
     */
    private ?string $description;

    //todo add zone horaire
    /**
     * @ORM\Column(type="date")
     * @Assert\NotBlank(message="the startDate is required")
     * @Assert\Type(type={"DateTime", "Y-m-d"}, message= "the date must be in the format YYYY-mm-dd")
     * @Assert\GreaterThanOrEqual("today", message="start date must be today or greater date")
     */
    private ?\DateTimeInterface $startDate;

    /**
     * @ORM\Column(type="date", nullable=true)
     * @Assert\Type(type={"DateTime", "Y-m-d"}, message= "the date must be a DateTime Object")
     * @Assert\GreaterThan(propertyPath="startDate", message="end date must be greater than start date")
     */
    private ?\DateTimeInterface $endDate = null;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="projects")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?User $creator;

    /**
     * @ORM\ManyToOne(targetEntity=Organization::class, inversedBy="projects")
     */
    private ?Organization $organization = null;

    /**
     * @ORM\Column(type="boolean")
     */
    private ?bool $isPublic;

    /**
     * Return an array containing object attributes
     * $context allows to give a context to avoid circular references
     * @param String|null $context
     * @return array
     */
    public function serialize(String $context = null): array
    {
        $data = [
            "id" => $this->id,
            "title" => $this->title,
            "description" => $this->description,
            "startDate" => $this->startDate->format('Y-m-d')
        ];

        //Check some attributes to see if they are sets
        if($this->endDate){
            $data["endDate"] = $this->endDate->format('Y-m-d');
        }

        if($context != "read_by_creator"){
            $data["creator"] = $this->creator->serialize("read_project");
        }

        //Check some attributes with contexts to see if they are sets
        if($this->organization && $context != "read_organization"){
            $data["organization"] = $this->organization->serialize("read_project");
        }



        return $data;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): self
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeInterface $endDate): self
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getCreator(): ?User
    {
        return $this->creator;
    }

    public function setCreator(?User $creator): self
    {
        $this->creator = $creator;

        return $this;
    }

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function setOrganization(?Organization $organization): self
    {
        $this->organization = $organization;

        return $this;
    }

    public function getIsPublic(): ?bool
    {
        return $this->isPublic;
    }

    public function setIsPublic(bool $isPublic): self
    {
        $this->isPublic = $isPublic;

        return $this;
    }
}
