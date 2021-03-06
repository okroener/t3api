<?php

declare(strict_types=1);
namespace SourceBroker\T3api\Controller;

use GoldSpecDigital\ObjectOrientedOAS\Exceptions\InvalidArgumentException as OasInvalidArgumentException;
use ReflectionException;
use SourceBroker\T3api\Domain\Repository\ApiResourceRepository;
use SourceBroker\T3api\Service\OpenApiBuilder;
use SourceBroker\T3api\Service\SerializerService;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * Administration controller
 */
class AdministrationController extends ActionController
{
    /**
     * @var ApiResourceRepository
     */
    protected $apiResourceRepository;

    /**
     * @var SerializerService
     */
    protected $serializerService;

    /**
     * @param ApiResourceRepository $apiResourceRepository
     */
    public function injectApiResourceRepository(ApiResourceRepository $apiResourceRepository): void
    {
        $this->apiResourceRepository = $apiResourceRepository;
    }

    /**
     * @param SerializerService $serializerService
     */
    public function injectSerializerService(SerializerService $serializerService): void
    {
        $this->serializerService = $serializerService;
    }

    /**
     * @return void
     */
    public function documentationAction(): void
    {
        $this->view->assign(
            'dataUrl',
            $this->uriBuilder->reset()->uriFor('openApiData')
        );
    }

    /**
     * @throws ReflectionException
     * @throws OasInvalidArgumentException
     * @return string
     */
    public function openApiDataAction(): string
    {
        return OpenApiBuilder::build($this->apiResourceRepository->getAll())->toJson();
    }
}
