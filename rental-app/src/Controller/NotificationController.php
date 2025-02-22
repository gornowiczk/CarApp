<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

class NotificationController extends AbstractController
{
    #[Route('/notifications', name: 'app_notifications')]
    public function notifications(Security $security, NotificationRepository $notificationRepository): Response
    {
        $user = $security->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $notifications = $notificationRepository->findBy(['user' => $user], ['createdAt' => 'DESC']);

        return $this->render('notifications/index.html.twig', [
            'notifications' => $notifications,
        ]);
    }

    #[Route('/notifications/{id}/mark-as-read', name: 'app_mark_notification_as_read')]
    public function markAsRead(Notification $notification, EntityManagerInterface $entityManager): Response
    {
        if ($notification->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Nie masz uprawnień do tej akcji.');
        }

        $notification->markAsRead();
        $entityManager->flush();

        return $this->redirectToRoute('app_notifications');
    }
}
