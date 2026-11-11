<?php

namespace App\Controller\Api;

use App\Service\ListingImages;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class ListingController extends ApiController
{
    public function __construct(private Connection $db, private ListingImages $images)
    {
    }

    #[Route('/api/listings', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $q = $request->query;
        $user = $this->sessionUser($request);
        $sql = 'SELECT * FROM listings WHERE 1';
        $p = [];

        $type = (string) $q->get('type', '');
        if ($type !== '' && $type !== 'All') {
            $sql .= ' AND type = ?';
            $p[] = $type;
        }
        $city = (string) $q->get('city', '');
        if ($city !== '') {
            $sql .= ' AND city = ?';
            $p[] = $city;
        }
        $guests = (int) $q->get('guests', 0);
        if ($guests) {
            $sql .= ' AND max_guests >= ?';
            $p[] = $guests;
        }
        if ($q->get('favorites') === '1') { // only this person's saved stays
            if (!$user) {
                $this->fail('Please log in first.', 401);
            }
            $sql .= ' AND id IN (SELECT listing_id FROM favorites WHERE user_id = ?)';
            $p[] = $user['id'];
        }

        // Hide stays already booked for the chosen dates
        $in = (string) $q->get('check_in', '');
        $out = (string) $q->get('check_out', '');
        if ($this->validDate($in) && $this->validDate($out) && $out > $in) {
            $sql .= " AND id NOT IN (SELECT listing_id FROM bookings WHERE status = 'confirmed' AND check_in < ? AND check_out > ?)";
            array_push($p, $out, $in);
        }

        $rows = $this->images->attach($this->db->fetchAllAssociative($sql . ' ORDER BY rating DESC, id', $p));

        $saved = $user ? array_flip($this->db->fetchFirstColumn('SELECT listing_id FROM favorites WHERE user_id = ?', [$user['id']])) : [];
        foreach ($rows as &$r) {
            $r['is_favorite'] = isset($saved[$r['id']]);
            $r['latitude'] = $r['latitude'] !== null ? (float) $r['latitude'] : null;
            $r['longitude'] = $r['longitude'] !== null ? (float) $r['longitude'] : null;
        }
        unset($r);

        return $this->json($rows);
    }

    /** Booked date ranges, so the calendar on the stay page can grey them out. */
    #[Route('/api/listings/{id}/availability', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function availability(int $id): JsonResponse
    {
        return $this->json(['booked' => $this->db->fetchAllAssociative(
            "SELECT check_in, check_out FROM bookings WHERE listing_id = ? AND status = 'confirmed' AND check_out > CURDATE() ORDER BY check_in",
            [$id]
        )]);
    }
}
