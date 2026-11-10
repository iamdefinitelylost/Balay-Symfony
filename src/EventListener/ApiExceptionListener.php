<?php

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

/**
 * Same idea as api_fail() / json_out() in config.php: every /api/* error is JSON {"error": "..."}.
 * Controllers just throw HttpException($status, $message).
 */
#[AsEventListener]
class ApiExceptionListener
{
    public function __construct(
        private LoggerInterface $logger,
        #[Autowire('%kernel.debug%')] private bool $debug,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }

        $e = $event->getThrowable();

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $message = $e->getMessage();
            if ($e->getPrevious() instanceof ResourceNotFoundException) {
                $message = 'Not found.';
            } elseif ($status === 405) {
                $message = 'Method not allowed.';
            }
        } else {
            $this->logger->error((string) $e);
            $status = 500;
            $message = $this->debug ? 'Server error: ' . $e->getMessage() : 'Server error. Please try again.';
        }

        $event->setResponse(new JsonResponse(['error' => $message], $status));
    }
}
