<?php

namespace App\Controller\Api;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class BookingController extends ApiController
{
    public function __construct(private Connection $db)
    {
    }

    #[Route('/api/book', methods: ['POST'])]
    public function book(Request $request): JsonResponse
    {
        $u = $this->needLogin($request);
        $b = $this->body($request);

        $id = (int) ($b['listing_id'] ?? 0);
        $guests = (int) ($b['guests'] ?? 0);
        $in = $b['check_in'] ?? '';
        $out = $b['check_out'] ?? '';

        if (!$this->validDate($in) || !$this->validDate($out) || $out <= $in) {
            $this->fail('Check-out must be after check-in.', 422);
        }
        if ($in < date('Y-m-d')) {
            $this->fail('Check-in cannot be in the past.', 422);
        }

        $this->db->beginTransaction();
        try {
            $l = $this->db->fetchAssociative('SELECT * FROM listings WHERE id = ? FOR UPDATE', [$id]);
            if (!$l) {
                $this->fail('Stay not found.', 404);
            }
            if ($guests < 1 || $guests > $l['max_guests']) {
                $this->fail("This stay fits up to {$l['max_guests']} guests.", 422);
            }

            $clash = $this->db->fetchOne(
                "SELECT COUNT(*) FROM bookings WHERE listing_id = ? AND status = 'confirmed' AND check_in < ? AND check_out > ?",
                [$id, $out, $in]
            );
            if ($clash > 0) {
                $this->fail('Those dates are already booked. Try other dates.', 409);
            }

            // Price is always calculated on the server
            $nights = (new \DateTime($in))->diff(new \DateTime($out))->days;
            $sub = $nights * (int) $l['price'];
            $total = $sub + (int) round($sub * 0.10);

            $this->db->executeStatement(
                'INSERT INTO bookings (listing_id,user_id,guest_name,guest_email,check_in,check_out,guests,total) VALUES (?,?,?,?,?,?,?,?)',
                [$id, $u['id'], $u['name'], $u['email'], $in, $out, $guests, $total]
            );
            $ref = $this->db->lastInsertId();
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $this->json([
            'ok' => true,
            'ref' => 'BAL-' . str_pad((string) $ref, 5, '0', STR_PAD_LEFT),
            'total' => $total,
            'title' => $l['title'],
        ]);
    }

    #[Route('/api/my-bookings', methods: ['GET'])]
    public function mine(Request $request): JsonResponse
    {
        $u = $this->needLogin($request);

        return $this->json($this->db->fetchAllAssociative(
            "SELECT b.*, l.title, l.city, gr.rating AS host_rating, gr.comment AS host_comment, hu.name AS host_name
             FROM bookings b JOIN listings l ON l.id = b.listing_id
             LEFT JOIN guest_reviews gr ON gr.booking_id = b.id
             LEFT JOIN users hu ON hu.id = gr.reviewer_id
             WHERE b.user_id = ? ORDER BY b.check_in DESC",
            [$u['id']]
        ));
    }

    #[Route('/api/my-bookings', methods: ['POST'])]
    public function cancel(Request $request): JsonResponse
    {
        $u = $this->needLogin($request);
        $id = (int) ($this->body($request)['cancel'] ?? 0);

        $n = $this->db->executeStatement(
            "UPDATE bookings SET status = 'cancelled' WHERE id = ? AND user_id = ? AND status = 'confirmed' AND check_in >= CURDATE()",
            [$id, $u['id']]
        );
        if (!$n) {
            $this->fail('This booking cannot be cancelled.', 409);
        }

        return $this->json(['ok' => true]);
    }
}
