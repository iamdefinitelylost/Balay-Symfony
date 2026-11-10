<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

/** Replaces attach_images() from config.php. */
class ListingImages
{
    public function __construct(private Connection $db)
    {
    }

    /** Adds an "images" list to each listing row (filenames for the public site, id+filename for admin). */
    public function attach(array $rows, bool $full = false): array
    {
        if (!$rows) {
            return $rows;
        }
        $ids = array_column($rows, 'id');
        $in = implode(',', array_fill(0, count($ids), '?'));
        $map = [];
        $images = $this->db->fetchAllAssociative(
            "SELECT id, listing_id, filename FROM listing_images WHERE listing_id IN ($in) ORDER BY sort_order, id",
            $ids
        );
        foreach ($images as $im) {
            $map[$im['listing_id']][] = $full
                ? ['id' => (int) $im['id'], 'filename' => $im['filename']]
                : $im['filename'];
        }
        foreach ($rows as &$r) {
            $r['images'] = $map[$r['id']] ?? [];
        }
        unset($r);

        return $rows;
    }
}
