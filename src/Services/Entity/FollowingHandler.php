<?php


namespace App\Services\Entity;


use App\Entity\FollowingProject;
use App\Entity\Interfaces\TrackableObject;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class FollowingHandler
{
    /**
     * Manager registry to access doctrine ; injected by DI
     */
    private EntityManagerInterface $entityManager;

    /**
     * FollowingHandler constructor.
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * add and return a follower into project, if any followingProject exist for this user in this project. It's created.
     * @param TrackableObject $object
     * @param UserInterface $follower
     * @return FollowingProject
     */
    public function addFollower(TrackableObject $object, UserInterface $follower) :FollowingProject {
        //follower have already a following?
        $following = $this->getFollowingByFollowerId($object, $follower->getId());

        if($following === null){
            $following = $this->newFollowing($object, $follower);
        }
        $following->setIsFollowing(true);

        return $following;
    }

    /**
     * add and return an assigned user into project by his creator, if any followingProject exist for this user in this project. It's created.
     * @param TrackableObject $object
     * @param UserInterface $follower
     * @return FollowingProject
     */
    public function addAssigned(TrackableObject $object, UserInterface $follower) :FollowingProject {

        $following = $this->getFollowingByFollowerId($object, $follower->getId());
        if($following === null){
            $following = $this->newFollowing($object, $follower);
        }


            //follower have already a following?
            if (!$following->getIsAssigning()) {
                $following->setIsAssigning(true);
            } else {
                $following->setIsAssigning(false);
            }


        return $following;
    }

    /**
     * @param FollowingProject $following
     * @return bool
     */
    public function rmvAssigned(FollowingProject $following) :bool {
        //follower have already a following?

    //    $following = $this->getFollowingByFollowerId($object, $follower->getId());
     //   if($following === null) return false;

        $following->setIsAssigning(false);

        return true;
    }

    /**
     * if follower have a following like a follower in the trackableObject, it's remove and return true, else return false
     * @param FollowingProject $following
     * @return bool
     */
    public function rmvFollower(FollowingProject $following) :bool {
    //    if($following === null) return false;

        $following->setIsFollowing(false);
        return true;
    }

    /**
     * check if the FollowingProject object have still a followed or assigned Follower
     * @param FollowingProject $following
     * @return bool
     */
    public function isStillValid(FollowingProject $following): bool
    {
        return $following->getIsAssigning() || $following->getIsFollowing();
    }

    /**
     * return a new instance of a FollowingProject object, with his TrackableObject and his follower
     * @param TrackableObject $object
     * @param UserInterface $follower
     * @return FollowingProject
     */
    private function newFollowing(TrackableObject $object, UserInterface $follower): FollowingProject
    {
        $following = new FollowingProject();
        $following->setIsFollowing(false);
        $following->setIsAssigning(false);
        $following->setFollower($follower);
        $following->setObject($object);
        return $following;
    }

    /**
     * return the list of assigned followers from a trackableObject
     * @param TrackableObject $object
     * @return array
     */
    public function getAssignedTeam(TrackableObject $object): array
    {
        $team = [$object->getCreator()];
        foreach($object->getFollowings() as $following){
            if($following->getIsAssigning()){
                $team[]=$following->getFollower();
            }
        }
        return $team;
    }

    /**
     * return the list of followers for a trackableObject
     * @param TrackableObject $object
     * @return array
     */
    public function getFollowers(TrackableObject $object): array
    {
        $followers = [];
        foreach($object->getFollowings() as $following){
            if($following->getIsFollowing()){
                $followers[]=$following;
            }
        }
        return $followers;
    }

    /**
     * return a FollowingProject object by his followerId
     * @param TrackableObject $object
     * @param int $id
     * @return FollowingProject|null
     */
    public function getFollowingByFollowerId(TrackableObject $object, int $id): ?FollowingProject
    {
        $res = null;

        foreach($object->getFollowings() as $following){
            if($following->getFollower()->getId() === $id){
                $res = $following;
            }
        }
        return $res;
    }

    /**
     * return a boolean for assigned status between a follower(userInterface) and a TrackableObject
     * @param TrackableObject $object
     * @param UserInterface $follower
     * @return bool
     */
    public function isAssign(TrackableObject $object, UserInterface $follower): bool
    {
        $res = false;
        if($object->getCreator()->getId() === $follower->getId()){
            $res = true;
        }
        else{
            $following = $this->getFollowingByFollowerId($object, $follower->getId());
            if($following !== null && $following->getIsAssigning() === true){
                $res = true;
            }
        }
        return $res;
    }

    public function isFollowed(TrackableObject $object, UserInterface $follower): bool
    {
        $res = false;
        $following = $this->getFollowingByFollowerId($object, $follower->getId());

        if($following !== null && $following->getIsAssigning() === true){
            $res = true;
        }
        return $res;
    }
}