<?php

namespace App\Controller;

use App\Entity\Organization;
use App\Exceptions\SecurityException;
use App\Service\LogEvents;
use App\Service\PictureHandler;
use Psr\Log\LoggerInterface;
use App\Service\Request\ParametersValidator;
use App\Service\Request\RequestParameters;
use App\Service\Security\RequestSecurity;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Class CommonController
 * Manages common methods for all other controllers that must inherit them
 * Supports services whose logging, sending queries, capturing errors and determining responses to return.
 * @package App\Controller
 * @author Thierry FAUCONNIER <th.fauconnier@outlook.fr>
 */
class CommonController extends AbstractController
{
    /**
     * Services
     */
    protected RequestSecurity $requestSecurity;
    protected RequestParameters $requestParameters;
    protected PictureHandler $picHandler;
    protected EntityManagerInterface $entityManager;
    protected ParametersValidator $paramValidator;
    private LogEvents $logEvents;
    private LoggerInterface $logger;

    //todo dispatch in LoggerService
    private $logInfo = "";

    //todo maybe add context here?

    /**
     * @var Response|null
     * Methods that can create a response should store it here and return a boolean to indicate the existence of the response.
     */
    protected ?Response $response;

    /**
     * @var Request
     * The request a secure faith of XSS risks must be stored here.
     */
    protected Request $request;

    /**
     * @var array
     * The RequestParameters call must store the query parameters of any type in the dataRequest array. Queries will use this data to define their sending
     */
    protected array $dataRequest;

    /**
     * @var array
     * The data of the sent requests are stored in this table. Each new data will overwrite the previous one. Methods that return data, must return a booleen indicating their existence here.
     */
    protected array $dataResponse;

    /**
     * @var array
     * Storage of dynamic Event message construction for LogEvent Service
     */
    protected array $eventInfo;

    /**
     * CommonController constructor.
     * @param RequestSecurity $requestSecurity
     * @param RequestParameters $requestParameters
     * @param EntityManagerInterface $entityManager
     * @param ParametersValidator $paramValidator
     * @param PictureHandler $picHandler
     * @param LoggerInterface $logger
     * @param LogEvents $logEvents
     */
    public function __construct(RequestSecurity $requestSecurity, RequestParameters $requestParameters, EntityManagerInterface $entityManager, ParametersValidator $paramValidator, PictureHandler $picHandler, LoggerInterface $logger, LogEvents $logEvents){
            $this->requestSecurity = $requestSecurity;
            $this->requestParameters = $requestParameters;
            $this->picHandler = $picHandler;
            $this->entityManager = $entityManager;
            $this->paramValidator = $paramValidator;
            $this->logger = $logger;
            $this->logEvents = $logEvents;
        }

    /**
     * @param Request $request
     * @return bool
     */
    public function cleanXSS(Request $request) :bool
    {
        //cleanXSS
        try {
            $this->request = $this->requestSecurity->cleanXSS($request);
        } catch (SecurityException $e) {
            $this->logger->warning($e);
            $this->response = new Response(
                json_encode(["error" => "ACCESS_FORBIDDEN"]),
                Response::HTTP_FORBIDDEN,
                ["Content-Type" => "application/json"]);
        }
        return isset($this->response);
    }


    //todo renom ?
    /**
     * @param $requiredFields
     * @param $optionalFields
     * @param $className
     * @return bool
     */
    public function isInvalid($requiredFields, $optionalFields, $className) :bool
    {
        $this->paramValidator->initValidator($requiredFields, $optionalFields, $className, $this->dataRequest);
        try{
            $violationsList = $this->paramValidator->getViolations();

            if( count($violationsList) > 0 ){
                $this->response = new Response(
                    json_encode(["error" => $violationsList]),
                    Response::HTTP_BAD_REQUEST,
                    ["Content-Type" => "application/json"]
                );
            }
        }catch(Exception $e){
            $this->serverErrorResponse($e, "");
        }
        return isset($this->response);
    }

    /**
     * @param $entities
     * @param String|null $context
     * @return mixed
     */
    public function serialize($entities, String $context = null){
        foreach($entities as $key => $entity){
            if(gettype($entity) != "string"){
                $entities[$key] = $entity->serialize($context);
            }
        }
        return $entities;
    }

    /**
     * @param $entity
     * @param $fields
     * @return mixed
     */
    public function setEntity($entity, $fields) {

        if(!isset($this->response)){
            foreach($fields as $field){
                if(isset($this->dataRequest[$field])){
                    $setter = 'set'.ucfirst($field);
                    $entity->$setter($this->dataRequest[$field]);
                }
            }
        }
        return $entity;
    }

    /**
     * @param $entity
     * @return bool
     */
    public function persistEntity($entity) :bool
    {
        $this->logInfo = "POST | " . get_class($entity);
        //persist the new entity
        try{
            $this->entityManager->persist($entity);
            $this->entityManager->flush();
            $this->dataResponse = [$entity];

            $this->eventInfo =["type" => $this->getClassName($entity), "desc" => "new registration"];
            $this->logInfo .= "| REGISTRATION_SUCCESS | new id: " .$entity->getId();

        }catch(Exception $e){
            $this->serverErrorResponse($e, $this->logInfo);
        }
        return isset($this->response);
    }

    /**
     * @param $entity
     * @return bool
     */
    public function updateEntity($entity) :bool
    {
        $this->logInfo .= "PUT | ". get_class($entity) . " | " .$entity->getId();
        try{
            $this->entityManager->flush();
            $this->dataResponse = [$entity];
            $this->eventInfo =["type" => $this->getClassName($entity), "desc" => "update"];
            $this->logInfo .= "| UPDATE_SUCCESS";
        }catch(Exception $e){
            $this->serverErrorResponse($e, $this->logInfo);
        }
        return isset($this->response);
    }

    /**
     * @param String $className
     * @param array $criterias
     * @return bool
     */
    public function getEntities(String $className, array $criterias) :bool {
        //initLog
        $this->logInfo .= ' GET | ' .  $className;

        $repository = $this->entityManager->getRepository($className);
        try{
            //verifies the existence of criteria and their validity for query
            if(count($this->dataRequest) > 0 ) {
                if($this->hasAllCriteria($criterias) && !($this->isInvalid($criterias, null, $className))){
                    //initLog
                    foreach ($criterias as $key => $criteria) {
                        $this->logInfo .= " | by " . $criteria . " : " . $this->dataRequest[$criteria];
                        $criterias[$criteria] = $this->dataRequest[$criteria];
                        unset($criterias[$key]);
                    }
                    $this->dataResponse = $repository->findBy($criterias);
                }
            }else { //otherwise we return all users
                //initLog
                $this->logInfo .= " | ALL";
                $this->dataResponse = $repository->findAll();
            }
        }
        catch(Exception $e){
            $this->serverErrorResponse($e, $this->logInfo);
        }

        return isset($this->response);
    }

    //todo logg for pics

    /**
     * @param $entity
     * @return mixed
     */
    public function loadPicture($entity) {
        $className = $this->getClassName($entity);
        $this->logInfo .= " GET | picture | for $className id: ".$entity->getId();
        if($this->getuser()){
            $this->logInfo .= " by user id : " . $this->getUser()->getId();
        }else {
            $this->logInfo .= " by anonymous user ";
        }

        if($entity->getPicturePath() !== null){
            try {
                $img = $this->picHandler->getPic($className, $entity->getPicturePath());
                $entity->setPictureFile($img);
            }catch(Exception $e){
                $this->serverErrorResponse($e, $this->logInfo);
            }
        }
        return $entity;
    }

    /**
     * @param $entity
     * @param UploadedFile $file
     * @return bool
     */
    public function uploadPicture($entity, UploadedFile $file){
        $className = $this->getClassName($entity);
        $this->logInfo .= " PUT | picture | for $className id: ".$entity->getId(). " by user id : " . $this->getUser()->getId();

        try{
            //uploading file in his directory
            $newPicPath= $this->picHandler->upload($className, $file);
            $this->dataRequest["picturePath"] = $newPicPath;
            $this->logInfo .= " | new Picture add $newPicPath ";

            //if a picture already exist, need to remove it
            if($entity->getPicturePath() !== null){
                $oldPic =$entity->getPicturePath();
                $this->picHandler->removeFile($className, $entity->getPicturePath());
                $this->logInfo .= " | old picture removed $oldPic ";
            }
        }catch(Exception $e){
            $this->serverErrorResponse($e, $this->logInfo);
        }

        return isset($this->response);
    }

    /**
     * @param String $className
     * @param String $attributeName
     * @param String $idKey
     * @return bool
     */
    public function getLinkedEntity(String $className, String $attributeName, String $idKey) :bool {
        $this->dataRequest = array_merge($this->dataRequest, ["id" => $this->dataRequest[$idKey]]);
        if(!$this->getEntities($className, ["id"])){
            if(!empty($this->dataResponse)){
                $this->dataRequest[$attributeName] = $this->dataResponse[0];
                unset($this->dataRequest[$idKey]);
            }else {
                $this->notFoundResponse();
            }
        }
        return isset($this->response);
    }

    /**
     * @param array $criterias
     * @return bool
     */
    public function hasAllCriteria(array $criterias) :bool
    {
        foreach($criterias as $criteria){
            if(!isset($this->dataRequest[$criteria])){
                $this->logger->info(Response::HTTP_NOT_FOUND . " | missing parameter: " . $criteria);
                $this->response =  new Response(
                    json_encode(["error" => "missing parameter : " . $criteria . " is required "]),
                    Response::HTTP_BAD_REQUEST,
                    ["Content-Type" => "application/json"]
                );
                return false;
            }
        }
        return true;
    }

    /**
     * @param String|null $context
     * @return Response
     */
    public function successResponse(String $context = null) : Response {
        if(isset($this->eventInfo)){
            //handle case when userInterface isn't used (register)
            !$this->getUser() ? $user = $this->dataResponse[0] : $user = $this->getUser();

            $this->logEvents->addEvents($user, $this->dataResponse[0]->getId(), $this->eventInfo["type"], $this->eventInfo["desc"]);
        }
        if(empty($this->dataResponse)){
             return $this->notFoundResponse();
        }else {
            $this->logInfo .= " | GET_SUCCESS | " . count($this->dataResponse) . " DATA_FOUND";
            $this->logger->info($this->logInfo);
            return $this->response =  new Response(
                json_encode(
                    $this->serialize($this->dataResponse, $context)
                ),
                Response::HTTP_OK,
                ["content-type" => "application/json"]
            );
        }
    }

    /**
     * @return Response
     */
    public function notFoundResponse() :Response{
        return  $this->response =  new Response(
            //todo stocker/ construire la chaine message log dans le service log
            //$logInfo .= " | DATA_NOT_FOUND";
            json_encode(["DATA_NOT_FOUND"]),
            Response::HTTP_OK,
            ["content-type" => "application/json"]
        );
    }

    /**
     * @param Exception $e
     * @param String $logInfo
     */
    public function serverErrorResponse(Exception $e, String $logInfo) :void
    {
        $logInfo .= $logInfo . " | FAILED | ";
        $this->logger->log("error",$logInfo . $e);

        //todo message un peu plus précis? ou pas...
        $this->response = new Response(
            json_encode(["error" => "ERROR_SERVER"]),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            ["Content-Type" => "application/json"]
        );
    }

    //todo really usefull?
    /**
     * @param $entity
     * @return String
     */
    public function getClassName($entity) :String {
        $namespace = explode("\\", get_class($entity));
        return end($namespace);
    }
}
