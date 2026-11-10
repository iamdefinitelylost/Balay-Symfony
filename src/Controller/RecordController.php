<?php

namespace App\Controller;

use App\Security\AppUser;
use App\Security\RecordVoter;
use App\Service\PhotoStorage;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Records = the stays (listings).
 *  - Staff: create, see all shared records, edit/delete ONLY their own (RecordVoter).
 *  - Admin: full access to every record (ROLE_ADMIN inherits ROLE_STAFF).
 * Anyone else is stopped by security.yaml (^/records -> ROLE_STAFF) and #[IsGranted] below.
 */
#[IsGranted('ROLE_STAFF')]
class RecordController extends AbstractController
{
    private const TYPES = ['Beach', 'Mountain', 'City'];

    public function __construct(private Connection $db, private PhotoStorage $photos)
    {
    }

    #[Route('/records', name: 'app_records')]
    public function index(Request $request): Response
    {
        $q = trim($request->query->getString('q'));
        $type = $request->query->getString('type');
        $owner = $request->query->getString('owner');

        $sql = 'SELECT l.id, l.title, l.city, l.type, l.price, l.max_guests, l.host, l.created_by, l.created_at, u.name AS owner_name
                FROM listings l LEFT JOIN users u ON u.id = l.created_by WHERE 1';
        $p = [];
        if ($q !== '') {
            $sql .= ' AND (l.title LIKE ? OR l.city LIKE ? OR l.host LIKE ?)';
            array_push($p, "%$q%", "%$q%", "%$q%");
        }
        if (in_array($type, self::TYPES, true)) {
            $sql .= ' AND l.type = ?';
            $p[] = $type;
        }
        if ($owner === 'mine') {
            $sql .= ' AND l.created_by = ?';
            $p[] = $this->currentId();
        }

        return $this->render('records/index.html.twig', [
            'records' => $this->db->fetchAllAssociative($sql . ' ORDER BY l.id DESC', $p),
            'q' => $q, 'type' => $type, 'owner' => $owner, 'types' => self::TYPES,
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
                $cols = implode(',', array_keys($form));
                $marks = implode(',', array_fill(0, count($form), '?'));
                $this->db->executeStatement(
                    "INSERT INTO listings ($cols, created_by) VALUES ($marks, ?)",
                    [...array_values($form), $this->currentId()]
                );
                $id = (int) $this->db->lastInsertId();
                $this->flashUploads($this->photos->store($id, $request->files->get('photos')));
                $this->addFlash('success', 'Record created.');

                return $this->redirectToRoute('app_records');
            }
        }

        return $this->render('records/form.html.twig', [
            'mode' => 'new', 'form' => $form, 'errors' => $errors, 'record' => null, 'images' => [], 'types' => self::TYPES,
        ], new Response('', $errors ? 422 : 200));
    }

    #[Route('/records/{id}/edit', name: 'app_record_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $record = $this->findOwned($id); // 403 if staff opens someone else's record
        $form = $record;
        $errors = [];

        if ($request->isMethod('POST')) {
            $this->checkCsrf($request, 'record_form');
            $form = $this->readForm($request);
            $errors = $this->validate($form);
            if (!$errors) {
                $set = implode(',', array_map(fn (string $k) => "$k = ?", array_keys($form)));
                $this->db->executeStatement("UPDATE listings SET $set WHERE id = ?", [...array_values($form), $id]);
                $this->flashUploads($this->photos->store($id, $request->files->get('photos')));
                $this->addFlash('success', 'Record updated.');

                return $this->redirectToRoute('app_records');
            }
        }

        return $this->render('records/form.html.twig', [
            'mode' => 'edit', 'form' => $form, 'errors' => $errors, 'record' => $record,
            'images' => $this->db->fetchAllAssociative('SELECT id, filename FROM listing_images WHERE listing_id = ? ORDER BY sort_order, id', [$id]),
            'types' => self::TYPES,
        ], new Response('', $errors ? 422 : 200));
    }

    #[Route('/records/{id}/photos/{photoId}/delete', name: 'app_record_photo_delete', requirements: ['id' => '\d+', 'photoId' => '\d+'], methods: ['POST'])]
    public function deletePhoto(int $id, int $photoId, Request $request): RedirectResponse
    {
        $this->findOwned($id);
        $this->checkCsrf($request, 'photo_action');
        $this->photos->deleteImage($id, $photoId);
        $this->addFlash('success', 'Photo removed.');

        return $this->redirectToRoute('app_record_edit', ['id' => $id]);
    }

    /** GET shows the confirmation prompt, POST deletes. */
    #[Route('/records/{id}/delete', name: 'app_record_delete', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function delete(int $id, Request $request): Response
    {
        $record = $this->findOwned($id);

        if ($request->isMethod('POST')) {
            $this->checkCsrf($request, 'record_action');
            $this->photos->deleteFilesFor($id);
            $this->db->executeStatement('DELETE FROM listings WHERE id = ?', [$id]);
            $this->addFlash('success', 'Record "' . $record['title'] . '" deleted.');

            return $this->redirectToRoute('app_records');
        }

        return $this->render('records/delete.html.twig', [
            'record' => $record,
            'bookings' => (int) $this->db->fetchOne('SELECT COUNT(*) FROM bookings WHERE listing_id = ?', [$id]),
        ]);
    }

    // ---------- helpers ----------

    /** Loads a record and enforces the ownership rule (staff: own only, admin: any). */
    private function findOwned(int $id): array
    {
        $record = $this->db->fetchAssociative('SELECT * FROM listings WHERE id = ?', [$id]);
        if (!$record) {
            throw $this->createNotFoundException('Record not found.');
        }
        $this->denyAccessUnlessGranted(RecordVoter::MANAGE, $record);

        return $record;
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

        return $u instanceof AppUser ? $u->getId() : 0;
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
        if (!in_array($f['type'], self::TYPES, true)) {
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
