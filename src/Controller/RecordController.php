<?php

namespace App\Controller;

use App\Entity\Listing;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Repository\ListingImageRepository;
use App\Repository\ListingRepository;
use App\Security\RecordVoter;
use App\Service\PhotoStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Records = the stays (Listing entities).
 *  - Staff: create, see all shared records, edit/delete ONLY their own (RecordVoter).
 *  - Admin: full access to every record (ROLE_ADMIN inherits ROLE_STAFF).
 * Anyone else is stopped by security.yaml (^/records -> ROLE_STAFF) and #[IsGranted] below.
 */
#[IsGranted('ROLE_STAFF')]
class RecordController extends AbstractController
{
    public function __construct(
        private ListingRepository $listings,
        private ListingImageRepository $images,
        private BookingRepository $bookings,
        private EntityManagerInterface $em,
        private PhotoStorage $photos,
    ) {
    }

    #[Route('/records', name: 'app_records')]
    public function index(Request $request): Response
    {
        $q = trim($request->query->getString('q'));
        $type = $request->query->getString('type');
        $owner = $request->query->getString('owner');

        return $this->render('records/index.html.twig', [
            'records' => $this->listings->search($q, $type, $owner === 'mine' ? $this->currentId() : null),
            'q' => $q, 'type' => $type, 'owner' => $owner, 'types' => Listing::TYPES,
        ]);
    }

    #[Route('/records/new', name: 'app_record_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $form = $this->blank();
        $errors = [];

        if ($request->isMethod('POST')) {
            $this->checkCsrf($request, 'record_form');
            $form = $this->readForm($request);
            $errors = $this->validate($form);
            if (!$errors) {
                $listing = (new Listing())->fill($form)->setCreatedBy($this->em->getReference(User::class, $this->currentId()));
                $this->em->persist($listing);
                $this->em->flush();
                $this->flashUploads($this->photos->store($listing, $request->files->get('photos')));
                $this->addFlash('success', 'Record created.');

                return $this->redirectToRoute('app_records');
            }
        }

        return $this->render('records/form.html.twig', [
            'mode' => 'new', 'form' => $form, 'errors' => $errors, 'record' => null, 'images' => [], 'types' => Listing::TYPES,
        ], new Response('', $errors ? 422 : 200));
    }

    #[Route('/records/{id}/edit', name: 'app_record_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $listing = $this->findOwned($id); // 403 if staff opens someone else's record
        $form = $listing->toFormArray();
        $errors = [];

        if ($request->isMethod('POST')) {
            $this->checkCsrf($request, 'record_form');
            $form = $this->readForm($request);
            $errors = $this->validate($form);
            if (!$errors) {
                $listing->fill($form);
                $this->em->flush();
                $this->flashUploads($this->photos->store($listing, $request->files->get('photos')));
                $this->addFlash('success', 'Record updated.');

                return $this->redirectToRoute('app_records');
            }
        }

        return $this->render('records/form.html.twig', [
            'mode' => 'edit', 'form' => $form, 'errors' => $errors, 'record' => $listing,
            'images' => $this->images->forListing($listing), 'types' => Listing::TYPES,
        ], new Response('', $errors ? 422 : 200));
    }

    #[Route('/records/{id}/photos/{photoId}/delete', name: 'app_record_photo_delete', requirements: ['id' => '\d+', 'photoId' => '\d+'], methods: ['POST'])]
    public function deletePhoto(int $id, int $photoId, Request $request): RedirectResponse
    {
        $listing = $this->findOwned($id);
        $this->checkCsrf($request, 'photo_action');
        $this->photos->deleteImage($listing, $photoId);
        $this->addFlash('success', 'Photo removed.');

        return $this->redirectToRoute('app_record_edit', ['id' => $id]);
    }

    /** GET shows the confirmation prompt, POST deletes. */
    #[Route('/records/{id}/delete', name: 'app_record_delete', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function delete(int $id, Request $request): Response
    {
        $listing = $this->findOwned($id);

        if ($request->isMethod('POST')) {
            $this->checkCsrf($request, 'record_action');
            $title = $listing->getTitle();
            $this->photos->deleteFilesFor($listing);
            $this->em->remove($listing); // the database cascades its photo rows and bookings
            $this->em->flush();
            $this->addFlash('success', 'Record "' . $title . '" deleted.');

            return $this->redirectToRoute('app_records');
        }

        return $this->render('records/delete.html.twig', [
            'record' => $listing,
            'bookings' => $this->bookings->count(['listing' => $listing]),
        ]);
    }

    // ---------- helpers ----------

    /** Loads a record and enforces the ownership rule (staff: own only, admin: any). */
    private function findOwned(int $id): Listing
    {
        $listing = $this->listings->find($id) ?? throw $this->createNotFoundException('Record not found.');
        $this->denyAccessUnlessGranted(RecordVoter::MANAGE, $listing);

        return $listing;
    }

    private function checkCsrf(Request $request, string $tokenId): void
    {
        if (!$this->isCsrfTokenValid($tokenId, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    private function currentId(): int
    {
        $u = $this->getUser();

        return $u instanceof User ? (int) $u->getId() : 0;
    }

    private function blank(): array
    {
        return [
            'title' => '', 'city' => '', 'type' => 'Beach', 'price' => '', 'max_guests' => '', 'host' => '',
            'is_new' => 0, 'amenities' => '', 'description' => '', 'color1' => '#1F8A8A', 'color2' => '#F2A93B',
        ];
    }

    private function readForm(Request $request): array
    {
        $b = $request->request;

        return [
            'title' => trim($b->getString('title')),
            'city' => trim($b->getString('city')),
            'type' => $b->getString('type'),
            'price' => (int) $b->getString('price'),
            'max_guests' => (int) $b->getString('max_guests'),
            'host' => trim($b->getString('host')),
            'is_new' => $b->getBoolean('is_new') ? 1 : 0,
            'amenities' => trim($b->getString('amenities')),
            'description' => trim($b->getString('description')),
            'color1' => $b->getString('color1'),
            'color2' => $b->getString('color2'),
        ];
    }

    private function validate(array $f): array
    {
        $errors = [];
        if ($f['title'] === '' || $f['city'] === '' || $f['host'] === '') {
            $errors[] = 'Title, city and host are required.';
        }
        if (mb_strlen($f['title']) > 120 || mb_strlen($f['city']) > 60 || mb_strlen($f['host']) > 60 || mb_strlen($f['amenities']) > 255) {
            $errors[] = 'One of the fields is too long.';
        }
        if (!in_array($f['type'], Listing::TYPES, true)) {
            $errors[] = 'Choose a valid type.';
        }
        if ($f['price'] < 1 || $f['max_guests'] < 1) {
            $errors[] = 'Price and max guests must be at least 1.';
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $f['color1']) || !preg_match('/^#[0-9a-fA-F]{6}$/', $f['color2'])) {
            $errors[] = 'Colors must look like #1F8A8A.';
        }

        return $errors;
    }

    private function flashUploads(array $result): void
    {
        if ($result['skipped']) {
            $this->addFlash('error', 'Some photos were not saved: ' . implode(' ', $result['skipped']));
        }
    }
}
