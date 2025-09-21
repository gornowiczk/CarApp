<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ?MailerInterface $mailer = null,
    ) {}

    /**
     * Dodaje rekord powiadomienia i (opcjonalnie) wysyła e-mail.
     */
    public function notify(
        User $user,
        string $title,
        ?string $body = null,
        ?Reservation $reservation = null,
        ?string $emailTemplate = null,   // np. 'emails/notification.html.twig'
        array $emailContext = []         // dodatkowe dane do szablonu e-mail
    ): Notification {
        $n = (new Notification())
            ->setUser($user)
            ->setTitle($title)
            ->setBody($body)
            ->setReservation($reservation);

        $this->em->persist($n);
        $this->em->flush();

        // E-mail – tylko jeżeli skonfigurowany Mailer i user ma e-mail
        if ($this->mailer && $user->getEmail()) {
            $email = (new TemplatedEmail())
                ->to($user->getEmail())
                ->subject($title);

            if ($emailTemplate) {
                $email
                    ->htmlTemplate($emailTemplate)
                    ->context(array_merge([
                        'title'       => $title,
                        'body'        => $body,
                        'reservation' => $reservation,
                        'user'        => $user,
                    ], $emailContext));
            } else {
                $email->html(sprintf('<p>%s</p><p>%s</p>', htmlspecialchars($title), nl2br((string)$body)));
            }

            try { $this->mailer->send($email); } catch (\Throwable) { /* loguj jeśli chcesz */ }
        }

        return $n;
    }

    // Szybkie aliasy – gotowe do użycia z kontrolera rezerwacji
    public function reservationCreated(Reservation $r): void
    {
        $this->notify(
            $r->getCar()->getOwner(),
            'Nowa rezerwacja na Twoje auto',
            sprintf('Użytkownik %s zarezerwował auto %s %s (%s → %s).',
                $r->getUser()->getEmail(),
                $r->getCar()->getBrand(),
                $r->getCar()->getModel(),
                $r->getStartDate()->format('Y-m-d'),
                $r->getEndDate()->format('Y-m-d')),
            $r,
            'emails/reservation_created.html.twig'
        );
    }

    public function reservationAccepted(Reservation $r): void
    {
        $this->notify(
            $r->getUser(),
            'Rezerwacja zaakceptowana',
            sprintf('Właściciel zaakceptował Twoją rezerwację %s %s (%s → %s).',
                $r->getCar()->getBrand(),
                $r->getCar()->getModel(),
                $r->getStartDate()->format('Y-m-d'),
                $r->getEndDate()->format('Y-m-d')),
            $r,
            'emails/reservation_status.html.twig',
            ['status' => 'accepted']
        );
    }

    public function reservationRejected(Reservation $r): void
    {
        $this->notify(
            $r->getUser(),
            'Rezerwacja odrzucona',
            sprintf('Właściciel odrzucił Twoją rezerwację %s %s (%s → %s).',
                $r->getCar()->getBrand(),
                $r->getCar()->getModel(),
                $r->getStartDate()->format('Y-m-d'),
                $r->getEndDate()->format('Y-m-d')),
            $r,
            'emails/reservation_status.html.twig',
            ['status' => 'rejected']
        );
    }
}
