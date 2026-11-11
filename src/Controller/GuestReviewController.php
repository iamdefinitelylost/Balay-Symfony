<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\GuestReview;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Repository\GuestReviewRepository;
use App\Security\GuestReviewVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The owner of a stay reviews the customers who stayed there.
 *  - Staff see and review guests of THEIR stays; admin sees every booking.
 *  - A review is possible once the stay has ended (check-out day reached) and the booking was not cancelled.
 */
#[IsGranted('ROLE_STAFF')]
class GuestReviewController extends AbstractController
{
    public function __construct(
        private BookingRepository $bookings,
        private GuestReviewRepository $reviews,
        private EntityManagerInterface $em,
    ) {
    }

    #[Route('/records/guests', name: 'app_guests')]
    public function index(): Response
    {
        $list = $this->bookings->forHost($this->isGranted('ROLE_ADMIN') ? null : $this->currentId());
        $given = $this->reviews->byBookings($list);
        $summaries = $this->reviews->summariesForGuests(array_values(array_unique(
            array_map(fn (Booking $b) => (int) $b->getUser()->getId(), $list)
        )));

        $items = array_map(fn (Booking $b) => [
            'booking' => $b,
            'review' => $given[$b->getId()] ?? null,
            'summary' => $summaries[$b->getUser()->getId()] ?? null,
            'canReview' => $this->canReview($b),
        ], $list);

        return $this->render('guests/index.html.twig', ['items' => $items]);
    }

    #[Route('/records/guests/{id}/review', name: 'app_guest_review', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function review(int $id, Request $request): Response
    {
        $booking = $this->bookings->find($id) ?? throw $this->createNotFoundException('Booking not found.');
        $this->denyAccessUnlessGranted(GuestReviewVoter::REVIEW, $booking); // staff: only their own stays

        if (!$this->canReview($booking)) {
            $this->addFlash('error', 'You can review a guest after their stay has ended.');

            return $this->redirectToRoute('app_guests');
        }

        $review = $this->reviews->findForBooking($booking);
        $form = ['rating' => $review?->getRating() ?? 5, 'comment' => $review?->getComment() ?? ''];
        $errors = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('guest_review', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }
            $form = ['rating' => (int) $request->request->getString('rating'), 'comment' => trim($request->request->getString('comment'))];
            if ($form['rating'] < 1 || $form['rating'] > 5) {
                $errors[] = 'Choose a rating from 1 to 5.';
            }
            if (mb_strlen($form['comment']) > 1000) {
                $errors[] = 'Keep the comment under 1,000 characters.';
            }
            if (!$errors) {
                $me = $this->em->getReference(User::class, $this->currentId());
                if (!$review) {
                    $review = new GuestReview($booking, $me);
                    $this->em->persist($review);
                }
                $review->setRating($form['rating'])->setComment($form['comment'])->setReviewer($me);
                $this->em->flush();
                $this->addFlash('success', 'Review saved for ' . $booking->getGuestName() . '.');

                return $this->redirectToRoute('app_guests');
            }
        }

        $summary = $this->reviews->summariesForGuests([(int) $booking->getUser()->getId()])[$booking->getUser()->getId()] ?? null;

        return $this->render('guests/review.html.twig', [
            'booking' => $booking, 'form' => $form, 'errors' => $errors, 'existing' => $review !== null, 'summary' => $summary,
        ], new Response('', $errors ? 422 : 200));
    }

    private function canReview(Booking $booking): bool
    {
        return $booking->getStatus() === 'confirmed' && $booking->getCheckOut() <= new \DateTimeImmutable('today');
    }

    private function currentId(): int
    {
        $u = $this->getUser();

        return $u instanceof User ? (int) $u->getId() : 0;
    }
}
