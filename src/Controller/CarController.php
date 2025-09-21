<?php

namespace App\Controller;

use App\Entity\Car;
use App\Entity\Reservation;
use App\Form\CarType;
use App\Form\ReservationType;
use App\Form\ReviewType;
use App\Repository\CarRepository;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/cars')]
final class CarController extends AbstractController
{
    public function __construct(private readonly string $uploadDirectory) {}

    #[Route('/all', name: 'app_car_list', methods: ['GET'])]
    public function list(Request $request, CarRepository $carRepository): Response
    {
        $filters = [
            'brand'       => $request->query->get('brand'),
            'model'       => $request->query->get('model'),
            'yearFrom'    => $request->query->get('yearFrom'),
            'yearTo'      => $request->query->get('yearTo'),
            'priceMin'    => $request->query->get('priceMin'),
            'priceMax'    => $request->query->get('priceMax'),
            'location'    => $request->query->get('location'),
            'isAvailable' => $request->query->get('isAvailable'),
        ];
        $sortBy = $request->query->get('sortBy', 'year');
        $order  = $request->query->get('order', 'DESC');

        $cars = method_exists($carRepository, 'findCarsByFilters')
            ? $carRepository->findCarsByFilters($filters, $sortBy, $order)
            : $carRepository->findBy([], [$sortBy => $order]);

        return $this->render('cars/list.html.twig', compact('cars','filters','sortBy','order'));
    }

    #[Route('/{id}', name: 'app_car_details', methods: ['GET'])]
    public function details(
        int $id,
        CarRepository $carRepository,
        ReviewRepository $reviewRepository,
        Request $request,
    ): Response {
        $car = $carRepository->find($id);
        if (!$car) {
            throw $this->createNotFoundException('Samochód nie został znaleziony.');
        }

        // Czy zalogowany jest właścicielem?
        $isOwner = $this->getUser() && $car->getOwner() && $this->getUser()->getId() === $car->getOwner()->getId();

        // Opinie + średnia
        $reviews = method_exists($reviewRepository, 'findBy')
            ? $reviewRepository->findBy(['car' => $car], ['createdAt' => 'DESC'])
            : [];
        $avg = 0.0;
        if (!empty($reviews)) {
            $sum = 0;
            foreach ($reviews as $r) { $sum += (int)$r->getRating(); }
            $avg = $sum / count($reviews);
        }

        // Formularz opinii (dla zalogowanych)
        $reviewForm = null;
        if ($this->getUser()) {
            $reviewForm = $this->createForm(ReviewType::class)->createView();
        }

        // Formularz rezerwacji (z tokenem CSRF) – pokazuj tylko, jeśli NIE właściciel i auto dostępne
        $reservationForm = null;
        if ($this->getUser() && !$isOwner && $car->isAvailable()) {
            $reservationForm = $this->createForm(ReservationType::class)->createView();
        }

        // Prefill dat do kalendarza (opcjonalnie ?start=YYYY-MM-DD&end=YYYY-MM-DD)
        $prefill = [
            'start' => $request->query->get('start'),
            'end'   => $request->query->get('end'),
        ];

        return $this->render('cars/reservation_details.html.twig', [
            'car'             => $car,
            'isOwner'         => $isOwner,
            'reviews'         => $reviews,
            'avg'             => $avg,
            'reviewForm'      => $reviewForm,
            'reservationForm' => $reservationForm, // ważne dla CSRF
            'prefill'         => $prefill,
        ]);
    }

    #[Route('/{id}/availability', name: 'app_car_availability', methods: ['GET'])]
    public function availability(Car $car, EntityManagerInterface $em): JsonResponse
    {
        $reservations = $em->getRepository(Reservation::class)
            ->findBy(['car' => $car, 'status' => ['pending','accepted']]);

        $events = [];
        foreach ($reservations as $r) {
            $events[] = [
                'title' => 'Zarezerwowane',
                'start' => $r->getStartDate()->format('Y-m-d'),
                'end'   => (clone $r->getEndDate())->modify('+1 day')->format('Y-m-d'), // EXCLUSIVE END
            ];
        }
        return new JsonResponse($events);
    }


    #[IsGranted('ROLE_USER')]
    #[Route('/add', name: 'app_add_car', methods: ['GET','POST'])]
    public function addCar(Request $request, EntityManagerInterface $em): Response
    {
        $car  = new Car();
        $form = $this->createForm(CarType::class, $car, [
            'validation_groups' => ['Default'],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $car->setOwner($this->getUser());

                if ($main = $form->get('mainImage')->getData()) {
                    $name = bin2hex(random_bytes(8)).'.'.$main->guessExtension();
                    $main->move($this->uploadDirectory, $name);
                    $car->setMainImage($name);
                }

                if ($gallery = $form->get('gallery')->getData()) {
                    $files = [];
                    foreach ($gallery as $g) {
                        $name = bin2hex(random_bytes(8)).'.'.$g->guessExtension();
                        $g->move($this->uploadDirectory, $name);
                        $files[] = $name;
                    }
                    $car->setGallery($files);
                }

                $em->persist($car);
                $em->flush();

                $this->addFlash('success', 'Samochód został dodany.');
                return $this->redirectToRoute('app_my_cars');
            }
            $this->addFlash('danger', 'Formularz zawiera błędy. Popraw je poniżej.');
        }

        return $this->render('cars/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{id}/edit', name: 'app_car_edit', methods: ['GET','POST'])]
    public function edit(Request $request, Car $car, EntityManagerInterface $em): Response
    {
        if ($car->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(CarType::class, $car, [
            'validation_groups' => ['Default'],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            // usuwanie plików — jak miałeś wcześniej

            if ($form->isValid()) {
                if ($main = $form->get('mainImage')->getData()) {
                    if ($car->getMainImage()) {
                        $old = rtrim($this->uploadDirectory, '/').'/'.$car->getMainImage();
                        if (is_file($old)) { @unlink($old); }
                    }
                    $name = bin2hex(random_bytes(8)).'.'.$main->guessExtension();
                    $main->move($this->uploadDirectory, $name);
                    $car->setMainImage($name);
                }

                if ($newGallery = $form->get('gallery')->getData()) {
                    $existing = $car->getGallery() ?? [];
                    foreach ($newGallery as $g) {
                        $n = bin2hex(random_bytes(8)).'.'.$g->guessExtension();
                        $g->move($this->uploadDirectory, $n);
                        $existing[] = $n;
                    }
                    $car->setGallery($existing);
                }

                $em->flush();
                $this->addFlash('success', 'Zmiany zapisane.');
                return $this->redirectToRoute('app_my_cars');
            }
            $this->addFlash('danger', 'Formularz zawiera błędy. Popraw je poniżej.');
        }

        return $this->render('cars/edit.html.twig', [
            'form' => $form->createView(),
            'car'  => $car,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/my', name: 'app_my_cars', methods: ['GET'])]
    public function myCars(CarRepository $cars): Response
    {
        $list = $cars->findBy(['owner' => $this->getUser()], ['id' => 'DESC']);
        return $this->render('cars/my_cars.html.twig', ['cars' => $list]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/delete/{id}', name: 'app_delete_car', methods: ['POST'])]
    public function delete(Request $request, Car $car, EntityManagerInterface $em): Response
    {
        if ($car->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isCsrfTokenValid('delete_car_'.$car->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Błędny token.');
        }

        $em->remove($car);
        $em->flush();
        $this->addFlash('success', 'Samochód usunięty.');
        return $this->redirectToRoute('app_my_cars');
    }

    /** Wstrzymanie / przywrócenie dostępności przez właściciela */
    #[IsGranted('ROLE_USER')]
    #[Route('/{id}/toggle', name: 'app_car_toggle', methods: ['POST'])]
    public function toggleAvailability(Request $request, Car $car, EntityManagerInterface $em): Response
    {
        if ($car->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isCsrfTokenValid('toggle_car_'.$car->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Błędny token.');
        }

        $car->setIsAvailable(!$car->isAvailable());
        $em->flush();

        $msg = $car->isAvailable() ? 'Ogłoszenie ponownie dostępne.' : 'Ogłoszenie wstrzymane.';
        $this->addFlash('success', $msg);

        return $this->redirectToRoute('app_car_details', ['id' => $car->getId()]);
    }
}
