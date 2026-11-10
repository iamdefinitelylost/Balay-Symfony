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

        // Hide stays already booked for the chosen dates
        $in = (string) $q->get('check_in', '');
        $out = (string) $q->get('check_out', '');
        if ($this->validDate($in) && $this->validDate($out) && $out > $in) {
            $sql .= " AND id NOT IN (SELECT listing_id FROM bookings WHERE status = 'confirmed' AND check_in < ? AND check_out > ?)";
            array_push($p, $out, $in);
        }

        $rows = $this->db->fetchAllAssociative($sql . ' ORDER BY rating DESC, id', $p);

        return $this->json($this->images->attach($rows));
    }
}
