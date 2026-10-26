<?php
namespace App\Controller;

use App\Entity\Appointment;
use App\Entity\Invoice;
use App\Entity\Part;
use App\Entity\Repair;
use App\Entity\RepairStatusHistory;
use App\Entity\RepairStateTransition;
use App\Entity\RepairPart;
use App\Entity\User;
use App\Entity\Vehicle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CrudController extends AbstractController
{
    #[Route('/voitures', name: 'vehicle_index')]
    public function vehicles(EntityManagerInterface $em, Request $request): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $q = trim((string)$request->query->get('q', ''));
        $qb = $em->getRepository(Vehicle::class)->createQueryBuilder('v')->orderBy('v.registrationNumber','ASC');
        if ($this->isGranted(User::ROLE_ADMIN)) {
            // admin sees all vehicles
        } elseif ($this->isGranted(User::ROLE_CLIENT)) {
            $qb->andWhere('v.client = :client')->setParameter('client', $user);
        } elseif ($this->isGranted(User::ROLE_MECHANIC)) {
            $qb->join(Repair::class, 'assignedRepair', 'WITH', 'assignedRepair.vehicle = v')
                ->andWhere('assignedRepair.mechanic = :mechanic')
                ->setParameter('mechanic', $user)
                ->distinct();
        } elseif ($this->isGranted(User::ROLE_STOREKEEPER)) {
            // storekeeper can manage vehicle list but not client-specific data only
        } else {
            throw $this->createAccessDeniedException();
        }
        if ($q !== '') $qb->andWhere('(LOWER(v.registrationNumber) LIKE :q OR LOWER(v.make) LIKE :q OR LOWER(v.model) LIKE :q OR LOWER(v.ownerName) LIKE :q)')->setParameter('q','%'.mb_strtolower($q).'%');
        return $this->render('crud/vehicles.html.twig',['items'=>$qb->getQuery()->getResult(),'q'=>$q]);
    }

    #[Route('/voitures/{id}/consulter', name: 'vehicle_show', methods: ['GET'])]
    public function vehicleShow(EntityManagerInterface $em, int $id): Response {
        $vehicle = $em->find(Vehicle::class, $id);
        if (!$vehicle) throw $this->createNotFoundException();
        $this->denyUnlessCanViewVehicle($em, $vehicle);
        $repairs = $em->getRepository(Repair::class)->findBy(['vehicle' => $vehicle], ['openedAt' => 'DESC']);
        if ($this->isGranted(User::ROLE_MECHANIC) && !$this->isGranted(User::ROLE_ADMIN)) {
            $repairs = array_values(array_filter($repairs, fn (Repair $repair): bool => $repair->getMechanic() === $this->getUser()));
        }
        return $this->render('crud/vehicle_show.html.twig', ['item' => $vehicle, 'repairs' => $repairs]);
    }

    #[Route('/voitures/nouvelle', name: 'vehicle_new', methods: ['GET','POST'])]
    #[Route('/voitures/{id}/modifier', name: 'vehicle_edit', methods: ['GET','POST'])]
    public function vehicleForm(Request $r, EntityManagerInterface $em, ?int $id = null): Response {
        if (!$this->getUser() instanceof User) {
            return $this->redirectToRoute('app_login', ['_target_path' => $this->generateUrl('vehicle_new')]);
        }
        //$this->denyAccessUnlessGranted([User::ROLE_ADMIN, User::ROLE_MECHANIC, User::ROLE_STOREKEEPER]);
        $v = $id ? $em->find(Vehicle::class,$id) : new Vehicle();
        if (!$v) throw $this->createNotFoundException();
        $clients = array_values(array_filter(
            $em->getRepository(User::class)->findBy([], ['fullName' => 'ASC']),
            static fn (User $user): bool => in_array(User::ROLE_CLIENT, $user->getRoles(), true),
        ));
        if ($r->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('vehicle_form_' . ($id ?? 'new'), (string)$r->request->get('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }
            $clientId = (int)$r->request->get('client');
            $client = $clientId ? $em->find(User::class, $clientId) : null;
            $clientIsValid = !$clientId || ($client && in_array(User::ROLE_CLIENT, $client->getRoles(), true));
            $v->setRegistrationNumber((string)$r->request->get('registrationNumber'))->setMake((string)$r->request->get('make'))->setModel((string)$r->request->get('model'))->setOwnerName((string)$r->request->get('ownerName'))->setOwnerPhone($r->request->get('ownerPhone'))->setClient($client)->setNotes($r->request->get('notes'));
            if ($v->getRegistrationNumber() && $v->getMake() && $v->getModel() && $v->getOwnerName() && $clientIsValid) { $em->persist($v); $em->flush(); $this->addFlash('success','Voiture enregistrée.'); return $this->redirectToRoute('vehicle_index'); }
            $this->addFlash('error','Veuillez renseigner l’immatriculation, la marque, le modèle et le client.');
        }
        return $this->render('crud/vehicle_form.html.twig',['item'=>$v, 'clients' => $clients]);
    }

    #[Route('/rendez-vous', name: 'appointment_index')]
    public function appointments(EntityManagerInterface $em): Response {
        $this->denyAccessUnlessGranted(User::ROLE_ADMIN);
        return $this->render('crud/appointments.html.twig',['items'=>$em->getRepository(Appointment::class)->findBy([],['appointmentAt'=>'DESC'])]);
    }

    #[Route('/rendez-vous/{id}/consulter', name: 'appointment_show', methods: ['GET'])]
    public function appointmentShow(EntityManagerInterface $em, int $id): Response {
        $appointment = $em->find(Appointment::class, $id);
        if (!$appointment) throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted(User::ROLE_ADMIN);
        return $this->render('crud/appointment_show.html.twig', ['item' => $appointment]);
    }

    #[Route('/rendez-vous/nouveau', name: 'appointment_new', methods: ['GET','POST'])]
    #[Route('/rendez-vous/{id}/modifier', name: 'appointment_edit', methods: ['GET','POST'])]
    public function appointmentForm(Request $r, EntityManagerInterface $em, ?int $id = null): Response {
        $this->denyAccessUnlessGranted(User::ROLE_ADMIN);
        $appointment = $id ? $em->find(Appointment::class, $id) : new Appointment();
        if (!$appointment) throw $this->createNotFoundException();
        $vehicles=$em->getRepository(Vehicle::class)->findBy([],['registrationNumber'=>'ASC']);
        $mechanics=$this->mechanics($em);
        if ($r->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('appointment_form_' . ($id ?? 'new'), (string)$r->request->get('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }
            $v=$em->find(Vehicle::class,(int)$r->request->get('vehicle'));
            $dt=\DateTimeImmutable::createFromFormat('Y-m-d\TH:i',(string)$r->request->get('appointmentAt'));
            $status=(string)$r->request->get('status','Confirmé');
            $mechanicId=(int)$r->request->get('mechanic');
            $mechanic=$mechanicId ? $em->find(User::class,$mechanicId) : null;
            if ($v && $dt && trim((string)$r->request->get('service'))!=='' && in_array($status, Appointment::STATUSES, true) && (!$mechanicId || $mechanic)) {
                $appointment->setVehicle($v)->setAppointmentAt($dt)->setService((string)$r->request->get('service'))->setStatus($status)->setMechanic($mechanic)->setNotes($r->request->get('notes'));
                $em->persist($appointment); $em->flush();
                $this->addFlash('success', $id ? 'Rendez-vous modifié.' : 'Rendez-vous enregistré.'); return $this->redirectToRoute('appointment_index');
            }
            $this->addFlash('error','Vérifiez la voiture, la date et l’intervention.');
        }
        return $this->render('crud/appointment_form.html.twig',['item'=>$appointment,'vehicles'=>$vehicles,'mechanics'=>$mechanics,'statuses'=>Appointment::STATUSES]);
    }

    #[Route('/rendez-vous/{id}/creer-reparation', name: 'appointment_to_repair', methods: ['POST'])]
    public function appointmentToRepair(Request $request, EntityManagerInterface $em, int $id): Response {
        $this->denyAccessUnlessGranted([User::ROLE_ADMIN, User::ROLE_MECHANIC, User::ROLE_STOREKEEPER]);

        $appointment = $em->find(Appointment::class, $id);
        if (!$appointment) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('appointment_to_repair_' . $id, (string)$request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $actor = $this->getUser();
        if (!$actor instanceof User) {
            throw $this->createAccessDeniedException('Utilisateur connecté invalide.');
        }

        $repair = new Repair();
        $repair->setVehicle($appointment->getVehicle())
            ->setMechanic($appointment->getMechanic())
            ->setTitle(trim($appointment->getService()) !== '' ? $appointment->getService() : 'Intervention')
            ->setDescription($appointment->getNotes())
            ->setLaborCost('0.00');

        $em->persist($repair);
        $em->persist(new RepairStatusHistory($repair, null, $repair->getStatus(), $actor));
        $appointment->setStatus('En réparation');
        $em->flush();

        $this->addFlash('success', 'Le rendez-vous a été converti en fiche de réparation.');

        return $this->redirectToRoute('repair_show', ['id' => $repair->getId()]);
    }

    #[Route('/reparations', name: 'repair_index')]
    public function repairs(EntityManagerInterface $em): Response {
        $user = $this->getUser();
        if (!$user instanceof User) throw $this->createAccessDeniedException();
        $qb = $em->getRepository(Repair::class)->createQueryBuilder('repair')->orderBy('repair.openedAt', 'DESC');
        if ($this->isGranted(User::ROLE_ADMIN)) {
            // admin sees all repairs
        } elseif ($this->isGranted(User::ROLE_MECHANIC)) {
            $qb->andWhere('repair.mechanic = :user')->setParameter('user', $user);
        } elseif ($this->isGranted(User::ROLE_CLIENT)) {
            $qb->join('repair.vehicle', 'vehicle')->andWhere('vehicle.client = :user')->setParameter('user', $user);
        } elseif ($this->isGranted(User::ROLE_STOREKEEPER)) {
            // storekeeper sees all repairs but cannot update status
        } else {
            throw $this->createAccessDeniedException();
        }
        return $this->render('crud/repairs.html.twig', ['items' => $qb->getQuery()->getResult()]);
    }

    #[Route('/reparations/{id}/consulter', name: 'repair_show', methods: ['GET'])]
    public function repairShow(EntityManagerInterface $em, int $id): Response {
        $repair = $em->find(Repair::class, $id);
        if (!$repair) throw $this->createNotFoundException();
        $this->denyUnlessCanViewRepair($repair);
        $transitions = $em->getRepository(RepairStateTransition::class)
            ->findBy(['stateName' => $repair->getStatus()]);
        $history = $em->getRepository(RepairStatusHistory::class)
            ->findBy(['repair' => $repair], ['createdAt' => 'DESC', 'id' => 'DESC']);
        return $this->render('crud/repair_show.html.twig', [
            'item' => $repair,
            'transitions' => $transitions,
            'history' => $history,
        ]);
    }

    #[Route('/reparations/{id}/etat', name: 'repair_transition', methods: ['POST'])]
    public function repairTransition(Request $request, EntityManagerInterface $em, int $id): Response {
        $this->denyAccessUnlessGranted([User::ROLE_ADMIN, User::ROLE_MECHANIC]);
        $actor = $this->getUser();
        if (!$actor instanceof User) {
            throw $this->createAccessDeniedException('Utilisateur connecté invalide.');
        }
        $repair = $em->find(Repair::class, $id);
        if (!$repair) throw $this->createNotFoundException();
        if (!$this->isGranted(User::ROLE_ADMIN) && $repair->getMechanic() !== $actor) {
            throw $this->createAccessDeniedException('Cette réparation ne vous est pas affectée.');
        }
        if (!$this->isCsrfTokenValid('repair_transition_'.$id, (string)$request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $previousStatus = $repair->getStatus();
        $transition = $em->getRepository(RepairStateTransition::class)->findOneBy([
            'stateName' => $repair->getStatus(),
            'nextState' => (string)$request->request->get('nextState'),
        ]);
        if (!$transition) {
            throw $this->createAccessDeniedException('Cette transition de statut n’est pas autorisée.');
        }

        $repair->setStatus($transition->getNextState());
        $repair->setClosedAt($transition->getNextState() === 'Réparé' ? new \DateTimeImmutable() : null);
        $em->persist(new RepairStatusHistory($repair, $previousStatus, $transition->getNextState(), $actor));
        $em->flush();
        $this->addFlash('success', 'État de la réparation mis à jour.');

        return $this->redirectToRoute('repair_show', ['id' => $id]);
    }

    #[Route('/reparations/nouvelle', name: 'repair_new', methods: ['GET','POST'])]
    #[Route('/reparations/{id}/modifier', name: 'repair_edit', methods: ['GET','POST'])]
    public function repairForm(Request $r, EntityManagerInterface $em, ?int $id = null): Response {
        //$this->denyAccessUnlessGranted([User::ROLE_ADMIN, User::ROLE_MECHANIC, User::ROLE_STOREKEEPER]);
        $repair = $id ? $em->find(Repair::class, $id) : new Repair();
        if (!$repair) throw $this->createNotFoundException();
        $vehicles=$em->getRepository(Vehicle::class)->findBy([],['registrationNumber'=>'ASC']); $mechanics=$this->mechanics($em);
        if ($r->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('repair_form_' . ($id ?? 'new'), (string)$r->request->get('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }
            $v=$em->find(Vehicle::class,(int)$r->request->get('vehicle'));
            $title=trim((string)$r->request->get('title'));
            $mechanicId=(int)$r->request->get('mechanic');
            $mechanic=$mechanicId ? $em->find(User::class,$mechanicId) : null;
            $laborCost=$r->request->get('laborCost');
            if ($v && $title!=='' && (!$mechanicId || $mechanic) && is_numeric($laborCost) && (float)$laborCost >= 0) {
                $actor = $id === null ? $this->getUser() : null;
                if ($id === null && !($actor instanceof User)) {
                    throw $this->createAccessDeniedException('Utilisateur connecté invalide.');
                }
                $repair->setVehicle($v)->setTitle($title)->setDescription($r->request->get('description'))->setLaborCost((string)$laborCost)->setMechanic($mechanic);
                $em->persist($repair);
                if ($id === null) {
                    $em->persist(new RepairStatusHistory($repair, null, $repair->getStatus(), $actor));
                }
                $em->flush();
                $this->addFlash('success', $id ? 'Réparation modifiée.' : 'Réparation créée.');
                return $this->redirectToRoute('repair_index');
            }
            $this->addFlash('error','Vérifiez la voiture, la réparation, le mécanicien, le statut et le coût.');
        }
        return $this->render('crud/repair_form.html.twig',['item'=>$repair,'vehicles'=>$vehicles,'mechanics'=>$mechanics]);
    }

    #[Route('/pieces', name: 'part_index')]
    public function parts(EntityManagerInterface $em): Response { $this->denyAccessUnlessGranted([User::ROLE_ADMIN, User::ROLE_STOREKEEPER]); return $this->render('crud/parts.html.twig',['items'=>$em->getRepository(Part::class)->findBy([],['name'=>'ASC'])]); }

    #[Route('/pieces/{id}/consulter', name: 'part_show', methods: ['GET'])]
    public function partShow(EntityManagerInterface $em, int $id): Response {
        $part = $em->find(Part::class, $id);
        if (!$part) throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted([User::ROLE_ADMIN, User::ROLE_STOREKEEPER]);
        return $this->render('crud/part_show.html.twig', ['item' => $part]);
    }

    #[Route('/pieces/nouvelle', name: 'part_new', methods: ['GET','POST'])]
    #[Route('/pieces/{id}/modifier', name: 'part_edit', methods: ['GET','POST'])]
    public function partForm(Request $r, EntityManagerInterface $em, ?int $id = null): Response {
        $this->denyAccessUnlessGranted([User::ROLE_ADMIN, User::ROLE_STOREKEEPER]);
        $part = $id ? $em->find(Part::class, $id) : new Part();
        if (!$part) throw $this->createNotFoundException();
        if ($r->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('part_form_' . ($id ?? 'new'), (string)$r->request->get('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }
            $reference=trim((string)$r->request->get('reference'));
            $name=trim((string)$r->request->get('name'));
            $stock=$r->request->get('stock');
            $unitPrice=$r->request->get('unitPrice');
            $alertThreshold=$r->request->get('alertThreshold',2);
            if ($reference !== '' && $name !== '' && filter_var($stock, FILTER_VALIDATE_INT) !== false && (int)$stock >= 0 && is_numeric($unitPrice) && (float)$unitPrice >= 0 && filter_var($alertThreshold, FILTER_VALIDATE_INT) !== false && (int)$alertThreshold >= 0) {
                $part->setReference($reference)->setName($name)->setStock((int)$stock)->setUnitPrice((string)$unitPrice)->setSupplier($r->request->get('supplier'))->setAlertThreshold((int)$alertThreshold);
                $em->persist($part); $em->flush(); $this->addFlash('success', $id ? 'Pièce modifiée.' : 'Pièce ajoutée.'); return $this->redirectToRoute('part_index');
            }
            $this->addFlash('error','Vérifiez la référence, le nom, le stock, le prix et le seuil d’alerte.');
        }

        return $this->render('crud/part_form.html.twig',['item'=>$part]);
    }

    #[Route('/factures', name: 'invoice_index')]
    public function invoices(EntityManagerInterface $em): Response { $this->denyAccessUnlessGranted(User::ROLE_ADMIN); return $this->render('crud/invoices.html.twig',['items'=>$em->getRepository(Invoice::class)->findBy([],['issuedAt'=>'DESC'])]); }

    #[Route('/factures/nouvelle', name: 'invoice_new', methods: ['GET','POST'])]
    public function invoiceNew(Request $r, EntityManagerInterface $em): Response {
        $this->denyAccessUnlessGranted(User::ROLE_ADMIN);
        $vehicles=$em->getRepository(Vehicle::class)->findBy([],['registrationNumber'=>'ASC']); $repairs=$em->getRepository(Repair::class)->findBy([],['openedAt'=>'DESC']);
        if ($r->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('invoice_new', (string)$r->request->get('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }
            $v=$em->find(Vehicle::class,(int)$r->request->get('vehicle'));
            if ($v) {
                $i=(new Invoice())->setVehicle($v)->setLaborAmount((string)max(0,(float)$r->request->get('laborAmount',0)))->setPartsAmount((string)max(0,(float)$r->request->get('partsAmount',0)))->setTaxRate((string)max(0,min(100,(float)$r->request->get('taxRate',19))))->setStatus((string)$r->request->get('status','Brouillon'))->setNotes($r->request->get('notes'));
                $repair=$em->find(Repair::class,(int)$r->request->get('repair')); if ($repair) $i->setRepair($repair);
                $em->persist($i); $em->flush(); $this->addFlash('success','Facture créée.'); return $this->redirectToRoute('invoice_index');
            }
            $this->addFlash('error','Choisissez une voiture.');
        }
        return $this->render('crud/invoice_form.html.twig',['vehicles'=>$vehicles,'repairs'=>$repairs]);
    }

    private function denyUnlessCanViewVehicle(EntityManagerInterface $em, Vehicle $vehicle): void
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isGranted(User::ROLE_ADMIN) || $this->isGranted(User::ROLE_MECHANIC) || $this->isGranted(User::ROLE_STOREKEEPER)) {
            return;
        }

        if ($this->isGranted(User::ROLE_CLIENT) && $vehicle->getClient() === $user) {
            return;
        }

        throw $this->createAccessDeniedException();
    }

    private function denyUnlessCanViewRepair(Repair $repair): void
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isGranted(User::ROLE_ADMIN) || $this->isGranted(User::ROLE_STOREKEEPER)) {
            return;
        }

        if ($this->isGranted(User::ROLE_MECHANIC) && $repair->getMechanic() === $user) {
            return;
        }

        if ($this->isGranted(User::ROLE_CLIENT) && $repair->getVehicle()?->getClient() === $user) {
            return;
        }

        throw $this->createAccessDeniedException();
    }

    private function mechanics(EntityManagerInterface $em): array
    {
        return array_values(array_filter(
            $em->getRepository(User::class)->findBy([], ['fullName' => 'ASC']),
            static fn (User $user): bool => in_array(User::ROLE_MECHANIC, $user->getRoles(), true)
        ));
    }
}
