<?php

namespace App\Controller\Api;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** Wishlist: the heart button on each stay. Saved per account. */
class FavoriteController extends ApiController
{
    public function __construct(private Connection $db)
    {
    }

    #[Route('/api/favorites/toggle', methods: ['POST'])]
    public function toggle(Request $request): JsonResponse
    {
        $u = $this->needLogin($request);
        $id = (int) ($this->body($request)['listing_id'] ?? 0);
        if (!$this->db->fetchOne('SELECT COUNT(*) FROM listings WHERE id = ?', [$id])) {
            $this->fail('Stay not found.', 404);
        }

        $removed = $this->db->executeStatement('DELETE FROM favorites WHERE user_id = ? AND listing_id = ?', [$u['id'], $id]);
        if (!$removed) {
            $this->db->executeStatement('INSERT IGNORE INTO favorites (user_id, listing_id) VALUES (?, ?)', [$u['id'], $id]);
        }

        return $this->json(['favorite' => !$removed]);
    }
}
