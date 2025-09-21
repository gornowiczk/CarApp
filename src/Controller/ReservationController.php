<?php

namespace App\Controller;

use App\Entity\Car;
use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/reservations')]
final class ReservationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CsrfTokenManagerInterface $csrf,
        private readonly NotificationService $notifier,
    ) {}

    #[IsGranted('ROLE_USER')]
    #[Route('', name: 'app_reservations', methods: ['GET'])]
    public function index(ReservationRepository $repo): Response
    {
        $user = $this->getUser();

        $myReservations = $repo->findBy(['user' => $user], ['startDate' => 'DESC']);

        $carReservations = $repo->createQueryBuilder('r')
            ->join('r.car', 'c')
            ->andWhere('c.owner = :owner')
            ->setParameter('owner', $user)
            ->orderBy('r.startDate', 'DESC')
            ->getQuery()->getResult();

        return $this->render('reservations/reservations.html.twig', [
            'myReservations'  => $myReservations,
            'carReservations' => $carReservations,
        ]);
    }



    #[IsGranted('ROLE_USER')]
    #[Route('/reserve/{id}', name: 'app_reserve_car', methods: ['POST'])]
    public function reserveCar(Request $request, Car $car): Response
    {
        if ($car->getOwner() === $this->getUser() || !$car->isAvailable()) {
            $this->addFlash('danger', 'Nie możesz zarezerwować tego auta.');
            return $this->redirectToRoute('app_car_details', ['id' => $car->getId()]);
        }

        $token = $request->request->get('_token');
        if (!$this->csrf->isTokenValid(new CsrfToken('reserve_car_'.$car->getId(), $token))) {
            $this->addFlash('danger', 'Błędny token.');
            return $this->redirectToRoute('app_car_details', ['id' => $car->getId()]);
        }

        try {
            $start = self::parseYmdOrFail($request->get('start'))->setTime(0,0);
            $end   = self::parseYmdOrFail($request->get('end'))->setTime(0,0);
        } catch (\Throwable) {
            $this->addFlash('danger', 'Nieprawidłowe daty.');
            return $this->redirectToRoute('app_car_details', ['id' => $car->getId()]);
        }

        if ($end < $start) {
            $this->addFlash('danger', 'Data zakończenia musi być po rozpoczęciu.');
            return $this->redirectToRoute('app_car_details', ['id' => $car->getId()]);
        }

        $overlap = $this->em->getRepository(Reservation::class)->createQueryBuilder('r')
            ->andWhere('r.car = :car')
            ->andWhere('r.status IN (:active)')
            ->andWhere('r.startDate <= :end AND r.endDate >= :start')
            ->setParameter('car', $car)
            ->setParameter('active', ['pending','accepted'])
            ->setParameter('start', $start)
            ->setParameter('end',   $end)
            ->getQuery()->getResult();

        if ($overlap) {
            $this->addFlash('danger', 'Termin jest zajęty.');
            return $this->redirectToRoute('app_car_details', ['id' => $car->getId()]);
        }

        $reservation = (new Reservation())
            ->setUser($this->getUser())
            ->setCar($car)
            ->setStartDate($start)
            ->setEndDate($end)
            ->setStatus('pending');

        $this->em->persist($reservation);
        $this->em->flush();

        // 🔔 powiadom właściciela
        $this->notifier->reservationCreated($reservation);

        $this->addFlash('success', 'Rezerwacja złożona.');
        return $this->redirectToRoute('app_reservations');
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/confirm/{id}', name: 'app_reservation_confirm', methods: ['POST'])]
    public function confirm(Request $request, Car $car): Response
    {
        $start = self::parseYmdOrFail($request->get('start'))->setTime(0,0);
        $end   = self::parseYmdOrFail($request->get('end'))->setTime(0,0);

        if ($end < $start) {
            $this->addFlash('danger', 'Nieprawidłowe daty.');
            return $this->redirectToRoute('app_car_details', ['id' => $car->getId()]);
        }

        $days = $start->diff($end)->days + 1;
        $total = $days * (float) $car->getPricePerDay();

        return $this->render('reservations/confirm.html.twig', [
            'car'   => $car,
            'start' => $start,
            'end'   => $end,
            'days'  => $days,
            'pricePerDay' => $car->getPricePerDay(),
            'total' => $total,
            'csrf'  => $this->csrf->getToken('reserve_store_'.$car->getId())->getValue(),
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/store/{id}', name: 'app_reservation_store', methods: ['POST'])]
    public function store(Request $request, Car $car): Response
    {
        $start = self::parseYmdOrFail($request->get('start'))->setTime(0,0);
        $end   = self::parseYmdOrFail($request->get('end'))->setTime(0,0);

        $reservation = (new Reservation())
            ->setUser($this->getUser())
            ->setCar($car)
            ->setStartDate($start)
            ->setEndDate($end)
            ->setStatus('pending');

        $this->em->persist($reservation);
        $this->em->flush();

        // 🔔 powiadom właściciela
        $this->notifier->reservationCreated($reservation);

        $this->addFlash('success', 'Rezerwacja złożona.');
        return $this->redirectToRoute('app_reservations');
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{id}/accept', name: 'app_reservation_accept', methods: ['POST'])]
    public function acceptReservation(Request $request, Reservation $reservation): Response
    {
        $reservation->setStatus('accepted');
        $this->em->flush();

        // 🔔 powiadom najemcę
        $this->notifier->reservationAccepted($reservation);

        $this->addFlash('success', 'Rezerwacja zaakceptowana.');
        return $this->redirectToRoute('app_reservations');
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{id}/reject', name: 'app_reservation_reject', methods: ['POST'])]
    public function rejectReservation(Request $request, Reservation $reservation): Response
    {
        $reservation->setStatus('rejected');
        $this->em->flush();

        // 🔔 powiadom najemcę
        $this->notifier->reservationRejected($reservation);

        $this->addFlash('success', 'Rezerwacja odrzucona.');
        return $this->redirectToRoute('app_reservations');
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{id}', name: 'app_reservations_show', methods: ['GET'])]
    public function show(Request $request, Reservation $reservation): Response
    {
        $user = $this->getUser();

        // Autoryzacja – tylko najemca albo właściciel
        if ($reservation->getUser() !== $user && $reservation->getCar()->getOwner() !== $user) {
            throw $this->createAccessDeniedException();
        }

        // Skąd użytkownik przyszedł? (notifications / reservations)
        $from = $request->query->get('from', 'reservations');

        return $this->render('reservations/show.html.twig', [
            'reservation' => $reservation,
            'from'        => $from,
        ]);
    }


    private static function parseYmdOrFail(?string $value): \DateTimeImmutable
    {
        if (!$value) throw new \InvalidArgumentException('Brak daty.');
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $dt ?: new \DateTimeImmutable($value);
    }
}
