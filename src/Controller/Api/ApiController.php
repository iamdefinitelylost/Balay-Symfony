<?php

namespace App\Controller\Api;

use App\Security\AppUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Shared helpers for the JSON API (the public site's bookings, listings and sign-up). */
abstract class ApiController extends AbstractController
{
    /** The logged-in user as ['id','name','email','role'], or null. */
    protected function sessionUser(Request $request): ?array
    {
        $u = $this->getUser();

        return $u instanceof AppUser ? $u->toArray() : null;
    }

    protected function needLogin(Request $request): array
    {
        $u = $this->sessionUser($request);
        if (!$u) {
            $this->fail('Please log in first.', 401);
        }

        return $u;
    }

    protected function body(Request $request): array
    {
        $data = json_decode($request->getContent(), true);

        return is_array($data) ? $data : [];
    }

    protected function fail(string $message, int $status): never
    {
        throw new HttpException($status, $message);
    }

    protected function validDate(?string $d): bool
    {
        $x = \DateTime::createFromFormat('Y-m-d', (string) $d);

        return $x && $x->format('Y-m-d') === $d;
    }
}
