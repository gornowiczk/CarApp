<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ContractController extends AbstractController
{
    #[Route('/contracts', name: 'app_contract_list')]
    public function list(ReservationRepository $repo): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Pobieramy tylko rezerwacje zalogowanego użytkownika
        $reservations = $repo->findBy(['user' => $user], ['startDate' => 'DESC']);

        return $this->render('contract/list.html.twig', [
            'reservations' => $reservations,
        ]);
    }

    #[Route('/contract/{id}', name: 'app_contract_generate', methods: ['GET'])]
    public function generateContract(Reservation $reservation): Response
    {
        $html = $this->renderView('contract/contract.html.twig', [
            'reservation' => $reservation,
        ]);

        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $pdfOptions->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($pdfOptions);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdfOutput = $dompdf->output();

        return new Response($pdfOutput, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Umowa_Najmu.pdf"',
        ]);
    }
}
