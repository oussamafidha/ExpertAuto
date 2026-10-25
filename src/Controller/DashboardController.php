<?php
namespace App\Controller;

use App\Entity\Appointment;
use App\Entity\Repair;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        return $this->render('dashboard/index.html.twig', $this->dashboardData($em));
    }

    #[Route('/dashboard/actualiser', name: 'app_dashboard_refresh', methods: ['GET'])]
    public function refresh(EntityManagerInterface $em): Response
    {
        return $this->render('dashboard/_content.html.twig', $this->dashboardData($em));
    }

    private function dashboardData(EntityManagerInterface $em): array
    {
        $repairs = $em->createQueryBuilder()->select('r, v, m')->from(Repair::class, 'r')
            ->join('r.vehicle', 'v')->leftJoin('r.mechanic', 'm')
            ->orderBy('r.openedAt', 'DESC')->getQuery()->getResult();

        $repairsByStatus = [
            'waitingRepairs' => [],
            'ongoingRepairs' => [],
            'completedRepairs' => [],
        ];

        foreach ($repairs as $repair) {
            if (in_array($repair->getStatus(), ['Ajouté', 'En attente'], true)) {
                $repairsByStatus['waitingRepairs'][] = $repair;
            } elseif ($repair->getStatus() === 'En réparation') {
                $repairsByStatus['ongoingRepairs'][] = $repair;
            } elseif ($repair->getStatus() === 'Réparé') {
                $repairsByStatus['completedRepairs'][] = $repair;
            }
        }

        usort(
            $repairsByStatus['completedRepairs'],
            static fn (Repair $first, Repair $second): int =>
                ($second->getClosedAt() ?? $second->getOpenedAt()) <=> ($first->getClosedAt() ?? $first->getOpenedAt())
        );
        $repairsByStatus['completedRepairs'] = array_slice($repairsByStatus['completedRepairs'], 0, 3);

        $appointments = $em->createQueryBuilder()->select('a, v')->from(Appointment::class, 'a')
            ->join('a.vehicle', 'v')
            ->andWhere('a.appointmentAt >= :now AND a.status != :cancelled')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('cancelled', 'Annulé')
            ->orderBy('a.appointmentAt', 'ASC')
            ->getQuery()->getResult();

        $plannedStartByVehicle = [];
        foreach ($appointments as $appointment) {
            $vehicleId = $appointment->getVehicle()->getId();
            if (!isset($plannedStartByVehicle[$vehicleId])) {
                $plannedStartByVehicle[$vehicleId] = $appointment->getAppointmentAt();
            }
        }

        return [
            'waitingRepairs' => $repairsByStatus['waitingRepairs'],
            'ongoingRepairs' => $repairsByStatus['ongoingRepairs'],
            'completedRepairs' => $repairsByStatus['completedRepairs'],
            'plannedStartByVehicle' => $plannedStartByVehicle,
        ];
    }
}
