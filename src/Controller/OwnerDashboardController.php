<?php

namespace App\Controller;

use App\Repository\CarRepository;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/owner')]
class OwnerDashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_owner_dashboard', methods: ['GET'])]
    public function index(
        CarRepository $carRepository,
        ReservationRepository $reservationRepository
    ): Response {
        $user = $this->getUser();

        // auta właściciela
        $cars = $carRepository->findBy(['owner' => $user]);

        // rezerwacje na auta właściciela
        $reservations = $reservationRepository->createQueryBuilder('r')
            ->join('r.car', 'c')
            ->andWhere('c.owner = :owner')
            ->setParameter('owner', $user)
            ->getQuery()->getResult();

        // proste statystyki
        $totalIncome = 0;
        foreach ($reservations as $r) {
            if ($r->getStatus() === 'accepted') {
                $days = $r->getStartDate()->diff($r->getEndDate())->days + 1;
                $totalIncome += $days * $r->getCar()->getPricePerDay();
            }
        }

        return $this->render('owner/dashboard.html.twig', [
            'cars' => $cars,
            'reservations' => $reservations,
            'totalIncome' => $totalIncome,
        ]);
    }
}
