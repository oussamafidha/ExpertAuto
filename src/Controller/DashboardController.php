<?php
namespace App\Controller;

use App\Entity\Appointment;
use App\Entity\Repair;
use App\Entity\RepairStatusHistory;
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
        ];

        foreach ($repairs as $repair) {
            if (in_array($repair->getStatus(), ['Ajouté', 'En attente'], true)) {
                $repairsByStatus['waitingRepairs'][] = $repair;
            } elseif ($repair->getStatus() === 'En réparation') {
                $repairsByStatus['ongoingRepairs'][] = $repair;
            }
        }

        usort(
            $repairsByStatus['waitingRepairs'],
            static fn (Repair $first, Repair $second): int => $first->getOpenedAt() <=> $second->getOpenedAt()
        );

        $timezone = new \DateTimeZone('Africa/Tunis');
        $today = new \DateTimeImmutable('today', $timezone);
        $tomorrow = $today->modify('+1 day');
        $completedHistory = $em->createQueryBuilder()
            ->select('history.id')
            ->from(RepairStatusHistory::class, 'history')
            ->where('history.repair = repair')
            ->andWhere('history.newStatus = :completedStatus')
            ->andWhere('history.createdAt >= :today')
            ->andWhere('history.createdAt < :tomorrow');

        $completedRepairs = $em->createQueryBuilder()
            ->select('repair, vehicle, mechanic')
            ->from(Repair::class, 'repair')
            ->join('repair.vehicle', 'vehicle')
            ->leftJoin('repair.mechanic', 'mechanic')
            ->where('repair.status = :completedStatus')
            ->andWhere($em->createQueryBuilder()->expr()->exists($completedHistory->getDQL()))
            ->setParameter('completedStatus', 'Réparé')
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->orderBy('repair.closedAt', 'DESC')
            ->getQuery()
            ->getResult();

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
            'completedRepairs' => $completedRepairs,
            'plannedStartByVehicle' => $plannedStartByVehicle,
        ];
    }
}
