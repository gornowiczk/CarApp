<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class InvoiceController extends AbstractController
{
    #[Route('/invoice/{id}', name: 'app_invoice_generate', methods: ['GET'])]
    public function generate(Reservation $reservation, EntityManagerInterface $em): Response
    {
        $doc = (new Document())
            ->setUser($this->getUser())
            ->setReservation($reservation)
            ->setType(Document::TYPE_INVOICE);
        $em->persist($doc);
        $em->flush();

        $html = $this->renderView('invoice/invoice.html.twig', ['reservation' => $reservation]);

        $ops = new Options();
        $ops->set('defaultFont', 'Arial');
        $ops->set('isHtml5ParserEnabled', true);
        $dompdf = new Dompdf($ops);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Faktura.pdf"',
        ]);
    }
}
