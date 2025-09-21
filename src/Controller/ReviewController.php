<?php

namespace App\Controller;

use App\Entity\Car;
use App\Entity\Review;
use App\Form\ReviewType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/reviews')]
final class ReviewController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/add/{id}', name: 'app_review_add', methods: ['POST'])]
    public function add(Request $request, Car $car): Response
    {
        $form = $this->createForm(ReviewType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('danger', 'Nie udało się dodać opinii. Sprawdź formularz.');
            return $this->redirectToRoute('app_car_details', ['id' => $car->getId()]);
        }

        // Sprawdź, czy użytkownik nie dodaje drugiej opinii dla tego samego auta (opcjonalnie)
        $existing = $this->em->getRepository(Review::class)->findOneBy([
            'car' => $car,
            'user' => $this->getUser(),
        ]);
        if ($existing) {
            $this->addFlash('warning', 'Już dodałeś opinię dla tego auta. Możesz ją usunąć i dodać ponownie.');
            return $this->redirectToRoute('app_car_details', ['id' => $car->getId()]);
        }

        $data = $form->getData(); // ['rating'=>..., 'content'=>...]
        $review = (new Review())
            ->setCar($car)
            ->setUser($this->getUser())
            ->setRating((int)$data['rating'])
            ->setContent((string)$data['content']);

        $this->em->persist($review);
        $this->em->flush();

        $this->addFlash('success', 'Dziękujemy za opinię!');
        return $this->redirectToRoute('app_car_details', ['id' => $car->getId(), '_fragment' => 'reviews']);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/delete/{id}', name: 'app_review_delete', methods: ['POST'])]
    public function delete(Request $request, Review $review): Response
    {
        if ($review->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isCsrfTokenValid('delete_review_' . $review->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Błędny token.');
        }

        $carId = $review->getCar()->getId();
        $this->em->remove($review);
        $this->em->flush();

        $this->addFlash('success', 'Opinia została usunięta.');
        return $this->redirectToRoute('app_car_details', ['id' => $carId, '_fragment' => 'reviews']);
    }
}
