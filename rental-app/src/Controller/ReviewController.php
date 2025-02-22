<?php
namespace App\Controller;

use App\Entity\Review;
use App\Form\ReviewType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Car;

class ReviewController extends AbstractController
{
    #[Route('/reviews/new/{carId}', name: 'review_new', methods: ['GET', 'POST'])]
    public function new(int $carId, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $car = $em->getRepository(Car::class)->find($carId);
        if (!$car) {
            throw $this->createNotFoundException('Samochód nie istnieje.');
        }

        $review = new Review();
        $form = $this->createForm(ReviewType::class, $review);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $review->setUser($this->getUser());
            $review->setCar($car);
            $em->persist($review);
            $em->flush();

            $this->addFlash('success', 'Twoja opinia została dodana.');
            return $this->redirectToRoute('app_car_details', ['id' => $carId]);
        }

        return $this->render('review/new.html.twig', [
            'form' => $form->createView(),
            'car' => $car,
        ]);
    }
}
