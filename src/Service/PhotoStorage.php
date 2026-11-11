<?php

namespace App\Service;

use App\Entity\Listing;
use App\Entity\ListingImage;
use App\Repository\ListingImageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Saves and removes stay photos (JPG/PNG/WebP, max 5 MB each, up to 10 per stay). */
class PhotoStorage
{
    private const ALLOWED = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private const MAX_PHOTOS = 10;

    public function __construct(
        private EntityManagerInterface $em,
        private ListingImageRepository $images,
        #[Autowire('%uploads_dir%')] private string $dir,
    ) {
    }

    /**
     * @param mixed $files value of $request->files->get('photos')
     * @return array{saved:int, skipped:string[]}
     */
    public function store(Listing $listing, mixed $files): array
    {
        if ($files instanceof UploadedFile) {
            $files = [$files];
        }
        if (!is_array($files)) {
            return ['saved' => 0, 'skipped' => []];
        }
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0755, true);
        }
        $count = $this->images->count(['listing' => $listing]);
        $saved = 0;
        $skipped = [];

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
            $this->em->persist(new ListingImage($listing, $name, $count + $saved));
            ++$saved;
        }
        $this->em->flush();

        return ['saved' => $saved, 'skipped' => $skipped];
    }

    public function deleteImage(Listing $listing, int $imageId): void
    {
        $image = $this->images->findOneBy(['id' => $imageId, 'listing' => $listing]);
        if ($image) {
            @unlink($this->dir . '/' . basename($image->getFilename()));
            $this->em->remove($image);
            $this->em->flush();
        }
    }

    /** Remove the files of every photo of a stay (database rows go with the stay itself). */
    public function deleteFilesFor(Listing $listing): void
    {
        foreach ($this->images->findBy(['listing' => $listing]) as $image) {
            @unlink($this->dir . '/' . basename($image->getFilename()));
        }
    }
}
