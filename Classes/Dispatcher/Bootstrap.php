<?php
declare(strict_types=1);
namespace SourceBroker\T3api\Dispatcher;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use SourceBroker\T3api\Exception\ExceptionInterface;
use SourceBroker\T3api\Service\RouteService;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Throwable;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspectFactory;
use TYPO3\CMS\Core\Http\Response;

/**
 * Class Bootstrap
 */
class Bootstrap extends AbstractDispatcher
{
    /**
     * @var ResponseInterface
     */
    protected $response;

    /**
     * @var HttpFoundationFactory
     */
    protected $httpFoundationFactory;

    /**
     * Bootstrap constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->response = new Response('php://temp', 200, ['Content-Type' => 'application/ld+json']);
    }

    /**
     * @param HttpFoundationFactory $httpFoundationFactory
     */
    public function injectHttpFoundationFactory(HttpFoundationFactory $httpFoundationFactory)
    {
        $this->httpFoundationFactory = $httpFoundationFactory;
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @throws Throwable
     * @return Response
     */
    public function process(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $this->setLanguage($request);
            $request = $this->httpFoundationFactory->createRequest($request);
            $context = (new RequestContext())->fromRequest($request);
            $matchedRoute = null;

            if ($this->isMainEndpointResponseClassDefined() && $this->isContextMatchingMainEndpointRoute($context)) {
                $output = $this->processMainEndpoint();
            } else {
                $output = $this->processOperationByRequest($context, $request, $this->response);
            }
        } catch (ExceptionInterface $exception) {
            $output = $this->serializerService->serialize($exception);
            $this->response = $this->response->withStatus($exception->getStatusCode(), $exception->getTitle());
        } catch (Throwable $throwable) {
            try {
                $output = $this->serializerService->serialize($throwable);
                $this->response = $this->response->withStatus(
                    SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR,
                    SymfonyResponse::$statusTexts[SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR]
                );
            } catch (Throwable $throwableSerializationException) {
                throw $throwable;
            }
        }

        $this->response->getBody()->write($output);

        return $this->response;
    }

    /**
     * @return bool
     */
    protected function isMainEndpointResponseClassDefined(): bool
    {
        return !empty($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['t3api']['mainEndpointResponseClass']);
    }

    /**
     * @param RequestContext $context
     *
     * @return bool
     */
    protected function isContextMatchingMainEndpointRoute(RequestContext $context): bool
    {
        $routes = (new RouteCollection());
        $routes->add('main_endpoint', new Route(RouteService::getFullApiBasePath() . '/'));
        $routes->add('main_endpoint_bis', new Route(RouteService::getFullApiBasePath()));

        try {
            (new UrlMatcher($routes, $context))->match($context->getPathInfo());

            return true;
        } catch (ResourceNotFoundException $resourceNotFoundException) {
        }

        return false;
    }

    /**
     * @return string
     */
    protected function processMainEndpoint(): string
    {
        return $this->serializerService->serialize(
            $this->objectManager->get($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['t3api']['mainEndpointResponseClass'])
        );
    }

    /**
     * Sets language according to language identifier sent in `languageHeader`
     *
     * @param ServerRequestInterface $request
     * @return void
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    protected function setLanguage(ServerRequestInterface $request): void
    {
        if (!$GLOBALS['TYPO3_REQUEST'] instanceof ServerRequestInterface) {
            throw new RuntimeException(
                sprintf('`TYPO3_REQUEST` is not an instance of `%s`', ServerRequestInterface::class),
                1580483236906
            );
        }
        $languageHeader = $GLOBALS['TYPO3_REQUEST']->getHeader($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['t3api']['languageHeader']);
        $languageUid = (int)(!empty($languageHeader) ? array_shift($languageHeader) : 0);
        $language = $request->getAttribute('site')->getLanguageById($languageUid);
        $this->objectManager->get(Context::class)
            ->setAspect('language', LanguageAspectFactory::createFromSiteLanguage($language));
        $GLOBALS['TYPO3_REQUEST'] = $GLOBALS['TYPO3_REQUEST']->withAttribute('language', $language);
    }
}
