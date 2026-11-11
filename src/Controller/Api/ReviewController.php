<?php

namespace App\Controller\Api;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** Guests review a stay. Only people whose trip there has ended (and was not cancelled) may review. */
class ReviewController extends ApiController
{
    public function __construct(private Connection $db)
    {
    }

    #[Route('/api/listings/{id}/reviews', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function index(int $id, Request $request): JsonResponse
    {
        $reviews = $this->db->fetchAllAssociative(
            "SELECT r.rating, r.comment, r.created_at, SUBSTRING_INDEX(u.name, ' ', 1) AS name
             FROM reviews r JOIN users u ON u.id = r.user_id
             WHERE r.listing_id = ? ORDER BY r.created_at DESC, r.id DESC LIMIT 50",
            [$id]
        );

        $u = $this->sessionUser($request);
        $mine = null;
        $can = false;
        if ($u) {
            $mine = $this->db->fetchAssociative('SELECT rating, comment FROM reviews WHERE listing_id = ? AND user_id = ?', [$id, $u['id']]) ?: null;
            $can = $this->hasStayed($u['id'], $id);
        }

        return $this->json(['reviews' => $reviews, 'can_review' => $can, 'my_review' => $mine]);
    }

    #[Route('/api/listings/{id}/reviews', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function create(int $id, Request $request): JsonResponse
    {
        $u = $this->needLogin($request);
        $b = $this->body($request);
        $rating = (int) ($b['rating'] ?? 0);
        $comment = trim((string) ($b['comment'] ?? ''));

        if ($rating < 1 || $rating > 5) {
            $this->fail('Choose a rating from 1 to 5.', 422);
        }
        if (mb_strlen($comment) > 1000) {
            $this->fail('Keep your review under 1,000 characters.', 422);
        }
        if (!$this->db->fetchOne('SELECT COUNT(*) FROM listings WHERE id = ?', [$id])) {
            $this->fail('Stay not found.', 404);
        }
        if (!$this->hasStayed($u['id'], $id)) {
            $this->fail('You can review a stay after your trip has ended.', 403);
        }

        $existing = $this->db->fetchOne('SELECT id FROM reviews WHERE listing_id = ? AND user_id = ?', [$id, $u['id']]);
        if ($existing) {
            $this->db->executeStatement('UPDATE reviews SET rating = ?, comment = ? WHERE id = ?', [$rating, $comment, $existing]);
        } else {
            $this->db->executeStatement('INSERT INTO reviews (listing_id, user_id, rating, comment) VALUES (?,?,?,?)', [$id, $u['id'], $rating, $comment]);
        }

        // The stay's rating is always the average of its real reviews.
        $this->db->executeStatement(
            'UPDATE listings SET
               rating = (SELECT COALESCE(ROUND(AVG(rating), 1), 0) FROM reviews WHERE listing_id = ?),
               reviews = (SELECT COUNT(*) FROM reviews WHERE listing_id = ?)
             WHERE id = ?',
            [$id, $id, $id]
        );
        $row = $this->db->fetchAssociative('SELECT rating, reviews FROM listings WHERE id = ?', [$id]);

        return $this->json(['ok' => true, 'rating' => $row['rating'], 'reviews' => (int) $row['reviews']]);
    }

    private function hasStayed(int $userId, int $listingId): bool
    {
        return (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM bookings WHERE user_id = ? AND listing_id = ? AND status = 'confirmed' AND check_out <= CURDATE()",
            [$userId, $listingId]
        ) > 0;
    }
}
