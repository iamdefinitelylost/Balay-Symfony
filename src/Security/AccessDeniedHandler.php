<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Twig\Environment;

/** Logged in but not allowed (e.g. staff typing /admin in the address bar) -> 403 Access Denied. */
class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function __construct(private Environment $twig)
    {
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): Response
    {
        if (str_starts_with($request->getPathInfo(), '/api/')) {
            return new JsonResponse(['error' => 'Access denied.'], 403);
        }

        return new Response($this->twig->render('errors/403.html.twig'), 403);
    }
}
