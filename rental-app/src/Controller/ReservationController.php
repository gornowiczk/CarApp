<?php

namespace App\Controller;

use App\Entity\Car;
use App\Entity\Reservation;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\HttpFoundation\Request;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;


class ReservationController extends AbstractController
{
    #[Route('/reservations', name: 'app_reservations')]
    public function index(Security $security, EntityManagerInterface $entityManager): Response
    {
        $user = $security->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $myReservations = $entityManager->getRepository(Reservation::class)->findBy(['user' => $user]);

        $ownedCars = $entityManager->getRepository(Car::class)->findBy(['owner' => $user]);
        $carReservations = [];
        foreach ($ownedCars as $car) {
            $carReservations = array_merge($carReservations, $entityManager->getRepository(Reservation::class)->findBy(['car' => $car]));
        }

        return $this->render('reservations/reservations.html.twig', [
            'myReservations' => $myReservations,
            'carReservations' => $carReservations,
        ]);
    }
    #[Route('/reservations/my', name: 'app_my_reservations')]
    public function myReservations(Security $security, EntityManagerInterface $entityManager): Response
    {
        $user = $security->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Pobieramy rezerwacje użytkownika
        $reservations = $entityManager->getRepository(Reservation::class)->findBy(['user' => $user]);

        return $this->render('reservations/my_reservations.html.twig', [
            'reservations' => $reservations
        ]);
    }
    #[Route('/cars/{id}/details', name: 'app_car_details')]
    public function carDetails(Car $car): Response
    {
        return $this->render('cars/reservation_details.html.twig', [
            'car' => $car,
        ]);
    }



    #[Route('/cars/{id}/reserve', name: 'app_reserve_car')]
    public function reserveCar(Request $request, Car $car, EntityManagerInterface $entityManager, Security $security): Response
    {
        $user = $security->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if ($car->getOwner() === $user) {
            $this->addFlash('danger', 'Nie możesz zarezerwować własnego samochodu.');
            return $this->redirectToRoute('app_car_details', ['id' => $car->getId()]);
        }

        $reservation = new Reservation();
        $form = $this->createFormBuilder($reservation)
            ->add('startDate', DateTimeType::class, ['widget' => 'single_text'])
            ->add('endDate', DateTimeType::class, ['widget' => 'single_text'])
            ->add('comment', TextareaType::class, ['required' => false])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $reservation->setUser($user);
            $reservation->setCar($car);
            $reservation->setStatus('pending');

            $entityManager->persist($reservation);
            $entityManager->flush();

            $this->addFlash('success', 'Rezerwacja została złożona!');
            return $this->redirectToRoute('app_reservations');
        }

        return $this->render('reservations/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }



    #[Route('/reservations/{id}/accept', name: 'app_accept_reservation', methods: ['POST'])]
    public function acceptReservation(Reservation $reservation, Security $security, EntityManagerInterface $entityManager): Response
    {
        $user = $security->getUser();

        if (!$user || $reservation->getCar()->getOwner() !== $user) {
            $this->addFlash('danger', 'Nie masz uprawnień do akceptowania tej rezerwacji.');
            return $this->redirectToRoute('app_reservations');
        }

        if ($reservation->getStatus() !== 'pending') {
            $this->addFlash('warning', 'Nie można już zmienić statusu tej rezerwacji.');
            return $this->redirectToRoute('app_reservations');
        }

        $reservation->setStatus('accepted');
        $entityManager->flush();

        $this->addFlash('success', 'Rezerwacja została zaakceptowana.');
        return $this->redirectToRoute('app_reservations');
    }

    #[Route('/reservations/{id}/reject', name: 'app_reject_reservation', methods: ['POST'])]
    public function rejectReservation(Reservation $reservation, Security $security, EntityManagerInterface $entityManager): Response
    {
        $user = $security->getUser();

        if (!$user || $reservation->getCar()->getOwner() !== $user) {
            $this->addFlash('danger', 'Nie masz uprawnień do odrzucenia tej rezerwacji.');
            return $this->redirectToRoute('app_reservations');
        }

        if ($reservation->getStatus() !== 'pending') {
            $this->addFlash('warning', 'Nie można już zmienić statusu tej rezerwacji.');
            return $this->redirectToRoute('app_reservations');
        }

        $reservation->setStatus('rejected');
        $entityManager->flush();

        $this->addFlash('danger', 'Rezerwacja została odrzucona.');
        return $this->redirectToRoute('app_reservations');
    }




    #[Route('/reservations/history', name: 'app_reservation_history')]
    public function reservationHistory(Security $security, EntityManagerInterface $entityManager): Response
    {
        $user = $security->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Pobieramy rezerwacje użytkownika
        $reservations = $entityManager->getRepository(Reservation::class)->findBy([
            'user' => $user
        ]);

        return $this->render('reservations/history_reservations.html.twig', [
            'reservations' => $reservations
        ]);
    }

    #[Route('/reservations/{id}/contract', name: 'app_generate_contract')]
    public function generateContract(Reservation $reservation): Response
    {
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $pdfOptions->set('isHtml5ParserEnabled', true);
        $dompdf = new Dompdf($pdfOptions);

        $user = $reservation->getUser();
        $car = $reservation->getCar();
        $owner = $car->getOwner();
        $startDate = $reservation->getStartDate()->format('Y-m-d');
        $endDate = $reservation->getEndDate()->format('Y-m-d');
        $days = $reservation->getStartDate()->diff($reservation->getEndDate())->days;
        $totalPrice = $days * $car->getPricePerDay();

        $html = ' 
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; line-height: 1.4; }
        h1, h2 { text-align: center; font-size: 16px; }
        .container { max-width: 700px; margin: auto; padding: 15px; border: 1px solid #000; background-color: #fff; }
        .section { margin-bottom: 10px; padding: 5px; border-bottom: 1px solid #ccc; }
        .section:last-child { border-bottom: none; }
        .signature-container { display: flex; justify-content: space-between; margin-top: 30px; }
        .signature-left { width: 45%; text-align: left; }
        .signature-right { width: 45%; text-align: right; }
        .signature-line { display: inline-block; width: 180px; border-top: 1px solid #000; margin-top: 10px; }
    </style>
    <div class="container">
        <h1>Faktura Najmu Samochodu</h1>
        <p style="text-align: right;"><strong>Data wystawienia:</strong> ' . date('Y-m-d') . '</p>
        <div class="section">
            <h2>Strony umowy</h2>
            <p><strong>Wynajmujący:</strong> ' . $owner->getFullName() . ', ' . $owner->getAddress() . '</p>
            <p><strong>Najemca:</strong> ' . $user->getFullName() . ', ' . $user->getAddress() . '</p>
        </div>
        <div class="section">
            <h2>Przedmiot wynajmu</h2>
            <p>Pojazd: <strong>' . $car->getBrand() . ' ' . $car->getModel() . ', rok ' . $car->getYear() . ', nr rej. ' . $car->getRegistrationNumber() . '</strong></p>
        </div>
        <div class="section">
            <h2>Szczegóły Wynajmu</h2>
            <p><strong>Okres wynajmu:</strong> ' . $startDate . ' - ' . $endDate . ' (' . $days . ' dni)</p>
            <h2><strong>Łączna kwota:</strong> ' . number_format($totalPrice, 2) . ' PLN</h2>
        </div>
        <div class="signature-container">
            <div class="signature-left">
                <p class="signature-line"></p>
                <p><strong>Podpis Najemcy</strong></p>
            </div>
            <div class="signature-right">
                <p class="signature-line"></p>
                <p><strong>Podpis Wynajmującego</strong></p>
            </div>
        </div>
    </div>';

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="faktura.pdf"'
        ]);
    }



    #[Route('/reservations/{id}/rental-agreement', name: 'app_generate_rental_agreement')]
    public function generateRentalAgreement(Reservation $reservation): Response
    {
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $pdfOptions->set('isHtml5ParserEnabled', true);
        $dompdf = new Dompdf($pdfOptions);

        $user = $reservation->getUser();
        $car = $reservation->getCar();
        $owner = $car->getOwner();
        $startDate = $reservation->getStartDate()->format('Y-m-d');
        $endDate = $reservation->getEndDate()->format('Y-m-d');
        $days = $reservation->getStartDate()->diff($reservation->getEndDate())->days;
        $totalPrice = $days * $car->getPricePerDay();
        $rentalFee = $totalPrice * 0.30;

        $html = ' 
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; line-height: 1.4; }
        h1, h2 { text-align: center; font-size: 16px; }
        .container { max-width: 700px; margin: auto; padding: 15px; border: 1px solid #000; background-color: #fff; }
        .section { margin-bottom: 10px; padding: 5px; border-bottom: 1px solid #ccc; }
        .section:last-child { border-bottom: none; }
        .signature-container { display: flex; justify-content: space-between; margin-top: 50px; align-items: center; }
        .signature-left { width: 45%; text-align: left; }
        .signature-right { width: 45%; text-align: right; }
        .signature-line { display: inline-block; width: 200px; border-top: 1px solid #000; margin-top: 40px; }
    </style>
    <div class="container">
        <h1>Umowa Najmu Samochodu</h1>
        <p style="text-align: right;"><strong>Zawarta w dniu:</strong> ' . $startDate . '</p>
        <div class="section">
            <h2>Strony umowy</h2>
            <p><strong>Wynajmujący:</strong> ' . $owner->getFullName() . ', ' . $owner->getAddress() . ', PESEL/NIP: ' . $owner->getPeselOrNip() . '</p>
            <p><strong>Najemca:</strong> ' . $user->getFullName() . ', ' . $user->getAddress() . ', PESEL/NIP: ' . $user->getPeselOrNip() . '</p>
        </div>
        <div class="section">
            <h2>Przedmiot umowy</h2>
            <p>Wynajmujący oświadcza, że jest właścicielem pojazdu:</p>
            <p><strong>' . $car->getBrand() . ' ' . $car->getModel() . ', rok ' . $car->getYear() . ', nr rej. ' . $car->getRegistrationNumber() . '</strong></p>
        </div>
        <div class="section">
            <h2>Warunki najmu</h2>
            <p>1. Okres wynajmu: <strong>' . $startDate . ' - ' . $endDate . ' (' . $days . ' dni)</strong>.</p>
            <p>2. Czynsz najmu: <strong>' . number_format($rentalFee, 2) . ' PLN</strong> (30% ostatecznej kwoty).</p>
            <p>3. Łączna kwota do zapłaty: <strong>' . number_format($totalPrice, 2) . ' PLN</strong>.</p>
            <p>4. Najemca zobowiązuje się do zwrotu pojazdu w stanie niepogorszonym.</p>
        </div>
        <div class="signature-container">
            <div class="signature-left">
                <p class="signature-line"></p>
                <p><strong>Podpis Najemcy</strong></p>
            </div>
            <div class="signature-right">
                <p class="signature-line"></p>
                <p><strong>Podpis Wynajmującego</strong></p>
            </div>
        </div>
    </div>';

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="umowa_najmu.pdf"'
        ]);
    }


    #[Route('/reservations/{id<\d+>}', name: 'app_reservation_details')]
    public function reservationDetails(int $id, EntityManagerInterface $entityManager, Security $security): Response
    {
        $reservation = $entityManager->getRepository(Reservation::class)->find($id);

        if (!$reservation) {
            throw $this->createNotFoundException('Nie znaleziono rezerwacji o ID: ' . $id);
        }

        $user = $security->getUser();

        // Sprawdź, czy użytkownik ma dostęp do tej rezerwacji
        if ($reservation->getCar()->getOwner() !== $user && $reservation->getUser() !== $user) {
            throw $this->createAccessDeniedException('Nie masz uprawnień do podglądu tej rezerwacji.');
        }

        return $this->render('reservations/reservation_details.html.twig', [
            'reservation' => $reservation,
        ]);
    }
    #[Route('/cars/{id}/availability', name: 'app_car_availability', methods: ['GET'])]
    public function getCarAvailability(Car $car, EntityManagerInterface $entityManager): Response
    {
        $reservations = $entityManager->getRepository(Reservation::class)->findBy(['car' => $car]);
        $events = [];

        foreach ($reservations as $reservation) {
            $events[] = [
                'title' => 'Zajęty',
                'start' => $reservation->getStartDate()->format('Y-m-d'),
                'end' => $reservation->getEndDate()->format('Y-m-d'),
                'color' => 'red',
                'status' => 'reserved'
            ];
        }

        return $this->json($events);
    }
    #[Route('/cars/{id}/confirm-reservation', name: 'app_confirm_reservation', methods: ['GET', 'POST'])]
    public function confirmReservation(Request $request, Car $car, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('GET')) {
            $startDate = $request->query->get('start_date');
            $endDate = $request->query->get('end_date');

            if (!$startDate || !$endDate) {
                throw $this->createNotFoundException('Nie podano daty rezerwacji.');
            }

            return $this->render('cars/confirm_reservation.html.twig', [
                'car' => $car,
                'startDate' => $startDate,
                'endDate' => $endDate,
            ]);
        }

        // Jeśli metoda to POST – tworzymy rezerwację
        $startDate = new \DateTime($request->request->get('start_date'));
        $endDate = new \DateTime($request->request->get('end_date'));
        $phoneNumber = $request->request->get('phoneNumber');
        $comments = $request->request->get('comments');

        $today = new \DateTime();
        $today->setTime(0, 0);

        // ❌ Blokowanie rezerwacji w przeszłości
        if ($startDate < $today || $endDate < $today) {
            $this->addFlash('danger', 'Nie można dokonać rezerwacji na przeszłe dni.');
            return $this->redirectToRoute('app_car_details', ['id' => $car->getId()]);
        }

        $reservation = new Reservation();
        $reservation->setCar($car);
        $reservation->setUser($this->getUser());
        $reservation->setStartDate($startDate);
        $reservation->setEndDate($endDate);
        $reservation->setPhoneNumber($phoneNumber);
        $reservation->setComments($comments);
        $reservation->setStatus('pending');

        $entityManager->persist($reservation);
        $entityManager->flush();

        $this->addFlash('success', 'Rezerwacja została pomyślnie złożona.');
        return $this->redirectToRoute('app_reservations');
    }
    #[Route('/cars/{id}/finalize-reservation', name: 'app_finalize_reservation', methods: ['POST'])]
    public function finalizeReservation(Request $request, Car $car, EntityManagerInterface $entityManager, Security $security): Response
    {
        $user = $security->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $startDate = new \DateTime($request->request->get('start_date'));
        $endDate = new \DateTime($request->request->get('end_date'));
        $phoneNumber = $request->request->get('phone_number');
        $rentalLocation = $request->request->get('rental_location');
        $comments = $request->request->get('comments');

        $reservation = new Reservation();
        $reservation->setUser($user);
        $reservation->setCar($car);
        $reservation->setStartDate($startDate);
        $reservation->setEndDate($endDate);
        $reservation->setPhoneNumber($phoneNumber);
        $reservation->setRentalLocation($rentalLocation);
        $reservation->setComments($comments);
        $reservation->setConfirmed(false);

        $entityManager->persist($reservation);
        $entityManager->flush();

        $this->addFlash('success', 'Rezerwacja została złożona i oczekuje na potwierdzenie.');
        return $this->redirectToRoute('app_list_cars');
    }





}

