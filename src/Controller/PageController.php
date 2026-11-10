<?php

namespace App\Controller;

use App\Security\AppUser;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Public pages: the home page and "My bookings" (/account is protected in security.yaml). */
class PageController extends AbstractController
{
    public function __construct(private Connection $db)
    {
    }

    #[Route('/', name: 'home')]
    public function home(): Response
    {
        $user = $this->getUser();

        return $this->render('home.html.twig', [
            'cities' => $this->db->fetchFirstColumn('SELECT DISTINCT city FROM listings ORDER BY city'),
            'me_json' => json_encode($user instanceof AppUser ? $user->toArray() : null, JSON_HEX_TAG | JSON_HEX_AMP),
        ]);
    }

    #[Route('/account', name: 'account')]
    public function account(): Response
    {
        return $this->render('account.html.twig', ['user' => $this->getUser()->toArray()]);
    }
}
