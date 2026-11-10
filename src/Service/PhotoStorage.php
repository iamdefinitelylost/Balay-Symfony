<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Saves and removes stay photos (JPG/PNG/WebP, max 5 MB each, up to 10 per stay). */
class PhotoStorage
{
    private const ALLOWED = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private const MAX_PHOTOS = 10;

    public function __construct(
        private Connection $db,
        #[Autowire('%uploads_dir%')] private string $dir,
    ) {
    }

    /**
     * @param mixed $files value of $request->files->get('photos')
     * @return array{saved:int, skipped:string[]}
     */
    public function store(int $listingId, mixed $files): array
    {
        if ($files instanceof UploadedFile) {
            $files = [$files];
        }
        $saved = 0;
        $skipped = [];
        if (!is_array($files)) {
            return ['saved' => 0, 'skipped' => []];
        }
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0755, true);
        }
        $count = (int) $this->db->fetchOne('SELECT COUNT(*) FROM listing_images WHERE listing_id = ?', [$listingId]);

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue; // nothing chosen
            }
            $label = basename($file->getClientOriginalName());
            if ($count + $saved >= self::MAX_PHOTOS) {
                $skipped[] = 'Limit is ' . self::MAX_PHOTOS . ' photos per stay.';
                break;
            }
            if (!$file->isValid()) {
                $skipped[] = "$label: upload failed (too large?).";
                continue;
            }
            if ($file->getSize() > 5 * 1024 * 1024) {
                $skipped[] = "$label: over 5 MB.";
                continue;
            }
            $info = @getimagesize($file->getPathname());
            $mime = $info['mime'] ?? '';
            if (!isset(self::ALLOWED[$mime])) {
                $skipped[] = "$label: only JPG, PNG or WebP.";
                continue;
            }
            $name = bin2hex(random_bytes(8)) . '.' . self::ALLOWED[$mime];
            try {
                $file->move($this->dir, $name);
            } catch (\Throwable) {
                $skipped[] = "$label: could not save.";
                continue;
            }
            $this->db->executeStatement(
                'INSERT INTO listing_images (listing_id, filename, sort_order) VALUES (?,?,?)',
                [$listingId, $name, $count + $saved]
            );
            ++$saved;
        }

        return ['saved' => $saved, 'skipped' => $skipped];
    }

    public function deleteImage(int $listingId, int $imageId): void
    {
        $fn = $this->db->fetchOne('SELECT filename FROM listing_images WHERE id = ? AND listing_id = ?', [$imageId, $listingId]);
        if ($fn) {
            @unlink($this->dir . '/' . basename($fn));
            $this->db->executeStatement('DELETE FROM listing_images WHERE id = ?', [$imageId]);
        }
    }

    /** Remove the files of every photo of a stay (database rows go with the stay itself). */
    public function deleteFilesFor(int $listingId): void
    {
        foreach ($this->db->fetchFirstColumn('SELECT filename FROM listing_images WHERE listing_id = ?', [$listingId]) as $fn) {
            @unlink($this->dir . '/' . basename($fn));
        }
    }
}
