<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Car;
use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('/', name: 'admin_dashboard', methods: ['GET'])]
    public function dashboard(EntityManagerInterface $em): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'users'        => $em->getRepository(User::class)->findAll(),
            'cars'         => $em->getRepository(Car::class)->findAll(),
            'reservations' => $em->getRepository(Reservation::class)->findAll(),
        ]);
    }

    #[Route('/cars', name: 'admin_cars', methods: ['GET'])]
    public function manageCars(EntityManagerInterface $em): Response
    {
        return $this->render('admin/cars.html.twig', [
            'cars' => $em->getRepository(Car::class)->findAll(),
        ]);
    }

    #[Route('/car/delete/{id}', name: 'admin_delete_car', methods: ['POST'])]
    public function deleteCar(Request $request, Car $car, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('admin_delete_car_'.$car->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Błędny token.');
        }

        $em->remove($car);
        $em->flush();
        $this->addFlash('success', 'Samochód został usunięty.');
        return $this->redirectToRoute('admin_cars');
    }

    #[Route('/users', name: 'admin_users', methods: ['GET'])]
    public function manageUsers(EntityManagerInterface $em): Response
    {
        return $this->render('admin/users.html.twig', [
            'users' => $em->getRepository(User::class)->findAll(),
        ]);
    }

    #[Route('/user/delete/{id}', name: 'admin_delete_user', methods: ['POST'])]
    public function deleteUser(Request $request, User $user, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('admin_delete_user_'.$user->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Błędny token.');
        }

        $em->remove($user);
        $em->flush();
        $this->addFlash('success', 'Użytkownik został usunięty.');
        return $this->redirectToRoute('admin_users');
    }

    #[Route('/reservations', name: 'admin_reservations', methods: ['GET'])]
    public function manageReservations(EntityManagerInterface $em): Response
    {
        return $this->render('admin/reservations.html.twig', [
            'reservations' => $em->getRepository(Reservation::class)->findAll(),
        ]);
    }

    #[Route('/reservation/accept/{id}', name: 'admin_accept_reservation', methods: ['POST'])]
    public function acceptReservation(Request $request, Reservation $reservation, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('admin_accept_reservation_'.$reservation->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Błędny token.');
        }
        $reservation->setStatus('accepted');
        $em->flush();
        $this->addFlash('success', 'Rezerwacja została zaakceptowana.');
        return $this->redirectToRoute('admin_reservations');
    }

    #[Route('/reservation/reject/{id}', name: 'admin_reject_reservation', methods: ['POST'])]
    public function rejectReservation(Request $request, Reservation $reservation, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('admin_reject_reservation_'.$reservation->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Błędny token.');
        }
        $reservation->setStatus('rejected');
        $em->flush();
        $this->addFlash('danger', 'Rezerwacja została odrzucona.');
        return $this->redirectToRoute('admin_reservations');
    }
}
