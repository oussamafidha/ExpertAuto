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
        $q = trim((string)$request->query->get('q', ''));
        $qb = $em->getRepository(Vehicle::class)->createQueryBuilder('v')->orderBy('v.registrationNumber','ASC');
        if ($q !== '') $qb->andWhere('LOWER(v.registrationNumber) LIKE :q OR LOWER(v.make) LIKE :q OR LOWER(v.model) LIKE :q OR LOWER(v.ownerName) LIKE :q')->setParameter('q','%'.mb_strtolower($q).'%');
        return $this->render('crud/vehicles.html.twig',['items'=>$qb->getQuery()->getResult(),'q'=>$q]);
    }

    #[Route('/voitures/{id}/consulter', name: 'vehicle_show', methods: ['GET'])]
    public function vehicleShow(EntityManagerInterface $em, int $id): Response {
        $vehicle = $em->find(Vehicle::class, $id);
        if (!$vehicle) throw $this->createNotFoundException();
        return $this->render('crud/vehicle_show.html.twig', ['item' => $vehicle]);
    }

    #[Route('/voitures/nouvelle', name: 'vehicle_new', methods: ['GET','POST'])]
    #[Route('/voitures/{id}/modifier', name: 'vehicle_edit', methods: ['GET','POST'])]
    public function vehicleForm(Request $r, EntityManagerInterface $em, ?int $id = null): Response {
        if ($id !== null) $this->denyAccessUnlessGranted('ROLE_MECHANIC');
        $v = $id ? $em->find(Vehicle::class,$id) : new Vehicle();
        if (!$v) throw $this->createNotFoundException();
        if ($r->isMethod('POST')) {
            $v->setRegistrationNumber((string)$r->request->get('registrationNumber'))->setMake((string)$r->request->get('make'))->setModel((string)$r->request->get('model'))->setOwnerName((string)$r->request->get('ownerName'))->setOwnerPhone($r->request->get('ownerPhone'))->setNotes($r->request->get('notes'));
            if ($v->getRegistrationNumber() && $v->getMake() && $v->getModel() && $v->getOwnerName()) { $em->persist($v); $em->flush(); $this->addFlash('success','Voiture enregistrée.'); return $this->redirectToRoute('vehicle_index'); }
            $this->addFlash('error','Veuillez renseigner l’immatriculation, la marque, le modèle et le client.');
        }
        return $this->render('crud/vehicle_form.html.twig',['item'=>$v]);
    }

    #[Route('/rendez-vous', name: 'appointment_index')]
    public function appointments(EntityManagerInterface $em): Response {
        return $this->render('crud/appointments.html.twig',['items'=>$em->getRepository(Appointment::class)->findBy([],['appointmentAt'=>'DESC'])]);
    }

    #[Route('/rendez-vous/{id}/consulter', name: 'appointment_show', methods: ['GET'])]
    public function appointmentShow(EntityManagerInterface $em, int $id): Response {
        $appointment = $em->find(Appointment::class, $id);
        if (!$appointment) throw $this->createNotFoundException();
        return $this->render('crud/appointment_show.html.twig', ['item' => $appointment]);
    }

    #[Route('/rendez-vous/nouveau', name: 'appointment_new', methods: ['GET','POST'])]
    #[Route('/rendez-vous/{id}/modifier', name: 'appointment_edit', methods: ['GET','POST'])]
    public function appointmentForm(Request $r, EntityManagerInterface $em, ?int $id = null): Response {
        if ($id !== null) $this->denyAccessUnlessGranted('ROLE_MECHANIC');
        $appointment = $id ? $em->find(Appointment::class, $id) : new Appointment();
        if (!$appointment) throw $this->createNotFoundException();
        $vehicles=$em->getRepository(Vehicle::class)->findBy([],['registrationNumber'=>'ASC']);
        $mechanics=$em->getRepository(User::class)->findAll();
        if ($r->isMethod('POST')) {
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

    #[Route('/reparations', name: 'repair_index')]
    public function repairs(EntityManagerInterface $em): Response { return $this->render('crud/repairs.html.twig',['items'=>$em->getRepository(Repair::class)->findBy([],['openedAt'=>'DESC'])]); }

    #[Route('/reparations/{id}/consulter', name: 'repair_show', methods: ['GET'])]
    public function repairShow(EntityManagerInterface $em, int $id): Response {
        $repair = $em->find(Repair::class, $id);
        if (!$repair) throw $this->createNotFoundException();
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
        $this->denyAccessUnlessGranted('ROLE_MECHANIC');
        $actor = $this->getUser();
        if (!$actor instanceof User) {
            throw $this->createAccessDeniedException('Utilisateur connecté invalide.');
        }
        $repair = $em->find(Repair::class, $id);
        if (!$repair) throw $this->createNotFoundException();
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
        if ($id !== null) $this->denyAccessUnlessGranted('ROLE_MECHANIC');
        $repair = $id ? $em->find(Repair::class, $id) : new Repair();
        if (!$repair) throw $this->createNotFoundException();
        $vehicles=$em->getRepository(Vehicle::class)->findBy([],['registrationNumber'=>'ASC']); $mechanics=$em->getRepository(User::class)->findAll();
        if ($r->isMethod('POST')) {
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
    public function parts(EntityManagerInterface $em): Response { return $this->render('crud/parts.html.twig',['items'=>$em->getRepository(Part::class)->findBy([],['name'=>'ASC'])]); }

    #[Route('/pieces/{id}/consulter', name: 'part_show', methods: ['GET'])]
    public function partShow(EntityManagerInterface $em, int $id): Response {
        $part = $em->find(Part::class, $id);
        if (!$part) throw $this->createNotFoundException();
        return $this->render('crud/part_show.html.twig', ['item' => $part]);
    }

    #[Route('/pieces/nouvelle', name: 'part_new', methods: ['GET','POST'])]
    #[Route('/pieces/{id}/modifier', name: 'part_edit', methods: ['GET','POST'])]
    public function partForm(Request $r, EntityManagerInterface $em, ?int $id = null): Response {
        if ($id !== null) $this->denyAccessUnlessGranted('ROLE_MECHANIC');
        $part = $id ? $em->find(Part::class, $id) : new Part();
        if (!$part) throw $this->createNotFoundException();
        if ($r->isMethod('POST')) {
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
    public function invoices(EntityManagerInterface $em): Response { return $this->render('crud/invoices.html.twig',['items'=>$em->getRepository(Invoice::class)->findBy([],['issuedAt'=>'DESC'])]); }

    #[Route('/factures/nouvelle', name: 'invoice_new', methods: ['GET','POST'])]
    public function invoiceNew(Request $r, EntityManagerInterface $em): Response {
        $vehicles=$em->getRepository(Vehicle::class)->findBy([],['registrationNumber'=>'ASC']); $repairs=$em->getRepository(Repair::class)->findBy([],['openedAt'=>'DESC']);
        if ($r->isMethod('POST')) {
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
}
