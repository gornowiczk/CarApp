<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/owner')]
final class OwnerController extends AbstractController
{
    #[Route('/owner/dashboard', name: 'app_owner_dashboard', methods: ['GET'])]
    public function dashboard(ReservationRepository $repo): Response
    {
        $user = $this->getUser();
        $carReservations = $repo->createQueryBuilder('r')
            ->join('r.car', 'c')
            ->andWhere('c.owner = :owner')
            ->setParameter('owner', $user)
            ->getQuery()->getResult();

        return $this->render('owner/dashboard.html.twig', [
            'reservations' => $carReservations
        ]);
    }
}
