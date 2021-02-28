<?php

namespace App\Entity;

use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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
     *     minMessage="the title must be at least 2 characters long",
     *     maxMessage="the title must not exceed 50 characters")
     */
    private ?string $title;

    //todo add timezone
    /**
     * @ORM\Column(type="date")
     * @Assert\NotBlank(message="the startDate is required")
     * @Assert\Type(type={"DateTime", "Y-m-d"}, message= "the date must be in the format Y-m-d")
     * @Assert\LessThanOrEqual(propertyPath="endDate", message="start date must be less or equal than end date")
     */
    private ?\DateTimeInterface $startDate = null;

    /**
     * @ORM\Column(type="date", nullable=true)
     * @Assert\Type(type={"DateTime", "Y-m-d"}, message= "the date must be a DateTime Object")
     * @Assert\GreaterThanOrEqual(propertyPath="startDate", message="end date must be greater or equal than start date")
     */
    private ?\DateTimeInterface $endDate = null;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="projects")
     * @ORM\JoinColumn(nullable=false)
     * @Assert\Type(type={"App\Entity\User", "integer"})
     */
    private ?User $creator;

    /**
     * @ORM\ManyToOne(targetEntity=Organization::class, inversedBy="projects")
     * @Assert\Type(type={"App\Entity\Organization", "integer"})
     */
    private ?Organization $organization = null;

    /**
     * @ORM\OneToMany(targetEntity=Activity::class, mappedBy="project")
     * @Assert\Collection(
     *     fields={
     *         @Assert\Type(type="App\Entity\Activity")
     *     }
     * )
     */
    private $activities;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $picturePath;

    /**
     * base64_encode(picture)
     */
    private $pictureFile;

    /**
     * @ORM\Column(type="json")
     */
    private $description = [];

    /**
     * @ORM\OneToMany(targetEntity=FollowingProject::class, mappedBy="project")
     * @Assert\Collection(
     *     fields={
     *         @Assert\Type(type="App\Entity\FollowingProject")
     *     }
     * )
     */
    private $followers;

    public function __construct()
    {
        $this->followers = new ArrayCollection();
    }

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
            "startDate" => $this->startDate->format('Y-m-d'),
            "creator" => $this->creator->serialize()
        ];

        //Check some attributes to see if they are sets
        if($this->endDate){
            $data["endDate"] = $this->endDate->format('Y-m-d');
        }

        if($this->pictureFile){
            $data["picture"] = $this->pictureFile;
        }

        /*if($context != "creator"){
            $data["creator"] = $this->creator->serialize("read_project");
        }*/

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

    /**
     * @return array
     */
    public function getDescription(): array
    {
        return $this->description;
    }

    /**
     * @param array $description
     */
    public function setDescription(array $description): void
    {
        $this->description = $description;
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

    /**
     * @return ArrayCollection
     */
    public function getActivities(): ArrayCollection
    {
        return $this->activities;
    }

    public function setActivities(?Activity $activities): self
    {
        // unset the owning side of the relation if necessary
        if ($activities === null && $this->activities !== null) {
            $this->activities->setProject(null);
        }

        // set the owning side of the relation if necessary
        if ($activities !== null && $activities->getProject() !== $this) {
            $activities->setProject($this);
        }

        $this->activities = $activities;

        return $this;
    }

    public function getPicturePath(): ?string
    {
        return $this->picturePath;
    }

    public function setPicturePath(?string $picturePath): self
    {
        $this->picturePath = $picturePath;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getPictureFile()
    {
        return $this->pictureFile;
    }

    /**
     * @param mixed $pictureFile
     */
    public function setPictureFile($pictureFile): void
    {
        $this->pictureFile = $pictureFile;
    }

    /**
     * @return Collection|FollowingProject[]
     */
    public function getFollowers(): Collection
    {
        return $this->followers;
    }

    public function addFollower(FollowingProject $follower): self
    {
        if (!$this->followers->contains($follower)) {
            $this->followers[] = $follower;
            $follower->setProject($this);
        }

        return $this;
    }

    public function removeFollower(FollowingProject $follower): self
    {
        if ($this->followers->removeElement($follower)) {
            // set the owning side to null (unless already changed)
            if ($follower->getProject() === $this) {
                $follower->setProject(null);
            }
        }

        return $this;
    }

}
