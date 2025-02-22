<?php

namespace App\Controller;

use App\Entity\Car;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use App\Entity\Notification;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(Security $security, EntityManagerInterface $entityManager): Response
    {
        $user = $security->getUser();
        $notifications = [];

        if ($user) {
            $notifications = $entityManager->getRepository(Notification::class)->findBy(['user' => $user, 'isRead' => false]);
        }

        // Pobieranie samochodów z bazy danych
        $cars = $entityManager->getRepository(Car::class)->findAll();

        return $this->render('home/index.html.twig', [
            'notifications' => $notifications,
            'cars' => $cars,  // <- tutaj przekazujemy zmienną "cars" do widoku
        ]);
    }
}

