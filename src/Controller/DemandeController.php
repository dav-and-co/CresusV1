<?php


// permet de déclarer des types pour chacune des variables INT ...
declare(strict_types=1);

// on crée un namespace qui permet d'identifier le chemin afin d'utiliser la classe actuelle
namespace App\Controller;

// Importation des classes nécessaires pour ce contrôleur
use App\Entity\Beneficiaire;
use App\Entity\Charge;
use App\Entity\Demande;
use App\Entity\Dette;
use App\Entity\HistoriqueAvct;
use App\Entity\Origine;
use App\Entity\PositionDemande;
use App\Entity\Revenu;
use App\Entity\Site;
use App\Entity\TypeDemande;
use App\Form\ChargeType;
use App\Form\DemandeType;
use App\Form\DetteType;
use App\Form\HistoriqueAvctType;
use App\Form\ModifDemandeType;
use App\Form\RevenuType;
use App\Repository\ChargeRepository;
use App\Repository\DemandeRepository;
use App\Repository\DetteRepository;
use App\Repository\ForfaitsBDFRepository;
use App\Repository\OrigineRepository;
use App\Repository\RevenuRepository;
use App\Repository\SiteRepository;
use App\Repository\TypeChargeRepository;
use App\Repository\TypeDetteRepository;
use App\Repository\TypeRevenuRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;



// La classe DemandeController hérite d'AbstractController, fournissant des méthodes utiles
class DemandeController extends AbstractController
{

//--------------------------------------------------------------------------------------------------------------
    // affiche une demande spécifique, après avoir calculé les totaux des revenus, charges et dettes
    #[Route('/benevole/demande/affichage/{id}', name: 'affichageDemande')]
    public function affichDemande(Request $request, EntityManagerInterface $entityManager, DemandeRepository $DemandeRepository, ForfaitsBDFRepository $forfaitsBDFRepository, int $id): Response
    {
        $demande = $DemandeRepository->findDemandeWithAllRelations($id);

        if (!$demande) {
            throw $this->createNotFoundException('La demande n\'existe pas.');
        }

        // Calculer les sommes
        $sommeRevenus = 0;
        $sommeCharges = 0;
        $sommeDettes = 0;
        $sommeMens = 0;
        $sommeChargesBDF = 0;
        $budget = 0;


        // Récupérer les valeurs  de la table ForfaitsBDF et de la composition de la demande
        $base1 = $forfaitsBDFRepository->find(1)->getMontant();
        $baseplus = $forfaitsBDFRepository->find(2)->getMontant();
        $chauffage1 = $forfaitsBDFRepository->find(3)->getMontant();
        $chauffageplus = $forfaitsBDFRepository->find(4)->getMontant();
        $habitation1 = $forfaitsBDFRepository->find(5)->getMontant();
        $habitationplus = $forfaitsBDFRepository->find(6)->getMontant();
        $alterne = $forfaitsBDFRepository->find(7)->getMontant();
        $visite = $forfaitsBDFRepository->find(8)->getMontant();


        $nbBeneficiaires = count($demande->getBeneficiaires());
        $nbEnfants = $demande->getNbEnfant() ?? 0;
        $nbalterne = $demande->getGardeAlternee() ?? 0;
        $nbvisite = $demande->getDroitVisite() ?? 0;

        // Calculs

        foreach ($demande->getRevenus() as $revenu) {
            $sommeRevenus += $revenu->getMontantMensuel();
        }
        foreach ($demande->getCharges() as $charge) {
            $sommeCharges += $charge->getMontantMensuel();
            // Si isBDF est true dans l'entité TypeCharge associée à la charge
            if ($charge->getTypeCharge()->isBDF()) {
                $sommeChargesBDF += $charge->getMontantMensuel();
            }
        }
        foreach ($demande->getDettes() as $dette) {
            $sommeDettes += $dette->getMontantDu();
            $sommeMens += $dette->getMensualite();
        }
        $forfaitBDF =  $sommeRevenus - $sommeChargesBDF - ($base1 + $chauffage1 + $habitation1 +(($baseplus + $chauffageplus + $habitationplus) * ($nbBeneficiaires + $nbEnfants - 1)) + $alterne * $nbalterne + $visite * $nbvisite) ;
        if ($forfaitBDF<0) {
            $forfaitBDF = 0;
        }
        $budget = $sommeRevenus - $sommeCharges - $sommeMens;
        if ($budget<0) {
            $budget = 0;
        }

        //  affiche la demande et arrête la méthode - contrôle de la demande
        //  dd($demande);

        return $this->render('interne/page/oneDemande.html.twig', [
            'demande' => $demande,
            'sommeRevenus' => $sommeRevenus,
            'sommeCharges' => $sommeCharges,
            'sommeDettes' => $sommeDettes,
            'sommeMens' => $sommeMens,
            'forfaitBDF' => $forfaitBDF,
            'budget' =>  $budget,
        ]);
    }

//--------------------------------------------------------------------------------------------------------------
// creation demande pour un bénéficiaire déjà existant - depuis la vue recherche beneficiaire
    #[Route('/benevole/demande/insert/{id}', name: 'insertDemande')]
    public function insertDemande(Request $request, EntityManagerInterface $entityManager, int $id, Security $security): Response
    {
        // Récupére le bénéficiaire par son ID
        $beneficiaire = $entityManager->getRepository(Beneficiaire::class)->find($id);

        if (!$beneficiaire) {
            throw $this->createNotFoundException('Le bénéficiaire n\'existe pas.');
        }

        // Associe les valeurs obligatoires pour création à minima
        $typeDemande = $entityManager->getRepository(TypeDemande::class)->find(1);
        $positionDemande = $entityManager->getRepository(PositionDemande::class)->find(1);
        $origine = $entityManager->getRepository(Origine::class)->find(1);
        $siteInitial = $entityManager->getRepository(Site::class)->find(2);
        $user = $security->getUser();

        // Crée une nouvelle demande
        $demande = new Demande();
        $demande->addUser($user);
        $demande->addBeneficiaire($beneficiaire);
        $demande->setSiteInitial($siteInitial);
        $demande->setTypeDemande($typeDemande);
        $demande->setOrigine($origine);
        $demande->setPositionDemande($positionDemande);
        $demande->setCreatedAt(new \DateTimeImmutable());

        // Persiste la demande
        $entityManager->persist($demande);
        $entityManager->flush();

        // Redirige vers l'affichage de la demande
        return $this->redirectToRoute('affichageDemande', ['id' => $demande->getId()
        ]);
    }

//--------------------------------------------------------------------------------------------------------------
    // suppression d'un bénéficiaire d'une demande - contrainte : la demande doit avoir 2 bénéficiaires
    #[Route('/benevole/demande/removeBeneficiaireFromDemande/{demandeId}/{beneficiaireId}', name: 'removeBeneficiaireFromDemande')]
    public function removeBeneficiaireFromDemande(int $demandeId, int $beneficiaireId, EntityManagerInterface $entityManager): Response
    {
        // Récupérer la demande
        $demande = $entityManager->getRepository(Demande::class)->find($demandeId);

        // Récupérer le bénéficiaire
        $beneficiaire = $entityManager->getRepository(Beneficiaire::class)->find($beneficiaireId);

        if (!$demande || !$beneficiaire) {
            throw $this->createNotFoundException('La demande ou le bénéficiaire n\'existe pas.');
        }

        // Supprimer le bénéficiaire de la demande
        $demande->removeBeneficiaire($beneficiaire);

        // Persister les changements
        $entityManager->persist($demande);
        $entityManager->flush();

        // Rediriger vers l'affichage de la demande
        return $this->redirectToRoute('affichageDemande', ['id' => $demandeId]);
    }

//--------------------------------------------------------------------------------------------------------------
    // enregistrement des commentaires depuis la vue de la demande
    #[Route('/benevole/demande/updateCommentaires/{id}', name: 'MAJcommentaires', methods: ['POST'])]
    public function updateCommentaires(Request $request, EntityManagerInterface $entityManager, DemandeRepository $demandeRepository, int $id): Response
    {
        $demande = $demandeRepository->find($id);

        if (!$demande) {
            throw $this->createNotFoundException('La demande n\'existe pas.');
        }

        $commentaires = $request->request->get('commentaires');
        $demande->setCommentaires($commentaires);

        $entityManager->persist($demande);
        $entityManager->flush();

        // Rediriger vers la page de la demande après la mise à jour
        return $this->redirectToRoute('affichageDemande', [
            'id' => $id
        ]);
    }

//--------------------------------------------------------------------------------------------------------------
    // ajout d'un revenu sur une demande spécifique - affichage des types actifs
    #[Route('/benevole/demande/ajoutRevenu/{id}', name: 'insertRevenu')]
    public function insertRevenu(Request $request, Demande $demande, EntityManagerInterface $entityManager, demandeRepository $demandeRepository, TypeRevenuRepository $typeRevenuRepository,$id ): Response
    {
        $demande = $demandeRepository->find($id);

        // Créer une nouvelle instance de Revenu
        $revenu = new Revenu();
        $revenu->setDemande($demande);

        // Récupérer les types de revenus actifs
        $activeTypeRevenus = $typeRevenuRepository->findActiveTypeRevenus();

        // Récupérer les bénéficiaires de la demande
        $beneficiaires = $demande->getBeneficiaires()->toArray();;

        // Créer le formulaire, en passant les bénéficiaires dans les options
        $form = $this->createForm(RevenuType::class, $revenu, [
            'beneficiaires' => $beneficiaires,
            'type_revenus' => $activeTypeRevenus,
        ]);

        // Gérer la soumission du formulaire
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Associer le revenu à la demande
            $revenu->setDemande($demande);

            // Enregistrer le revenu dans la base de données
            $entityManager->persist($revenu);
            $entityManager->flush();

            // Si le bouton "Nouvelle saisie" est cliqué
            if ($request->request->has('nouvelle_saisie')) {

                // Ajouter un message flash pour informer l'utilisateur du succès
                $this->addFlash('success', 'Votre saisie a été enregistrée. Vous pouvez maintenant en ajouter un nouveau revenu.');

                // Rediriger vers la même page pour saisir un nouveau revenu
                return $this->redirectToRoute('insertRevenu', ['id' => $demande->getId()]);
            }

            // Rediriger vers une autre page ou afficher un message de succès
            return $this->redirectToRoute('affichageDemande', ['id' => $demande->getId()]);
        }

        // Afficher le formulaire
        return $this->render('interne/page/insertRevenu.html.twig', [
            'form' => $form->createView(),
            'beneficiaires' => $beneficiaires,
            'demande' => $demande,
        ]);
    }

//--------------------------------------------------------------------------------------------------------------
    // ajout d'une charge sur une demande spécifique - affichage des types actifs
    #[Route('/benevole/demande/ajoutCharge/{id}', name: 'insertCharge')]
    public function insertCharge(Request $request, Demande $demande, EntityManagerInterface $entityManager, demandeRepository $demandeRepository,TypeChargeRepository $typeChargeRepository,$id ): Response
    {
        $demande = $demandeRepository->find($id);

        // Créer une nouvelle instance de Charge
        $charge = new Charge();
        $charge->setDemande($demande);

        // Récupérer les types de charges actifs
        $activeTypeCharges = $typeChargeRepository->findActiveTypeCharges();

        // Récupérer les bénéficiaires de la demande
        $beneficiaires = $demande->getBeneficiaires()->toArray();;

        // Créer le formulaire, en passant les bénéficiaires dans les options
        $form = $this->createForm(ChargeType::class, $charge, [
            'beneficiaires' => $beneficiaires,
            'type_charges' => $activeTypeCharges,
        ]);

        // Gérer la soumission du formulaire
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Associer le revenu à la demande (si besoin)
            $charge->setDemande($demande);

            // Enregistrer le revenu dans la base de données
            $entityManager->persist($charge);
            $entityManager->flush();

            // Si le bouton "Nouvelle saisie" est cliqué
            if ($request->request->has('nouvelle_saisie')) {

                // Ajouter un message flash pour informer l'utilisateur du succès
                $this->addFlash('success', 'Votre saisie a été enregistrée. Vous pouvez maintenant ajouter une nouvelle charge.');

                // Rediriger vers la même page pour saisir un nouveau revenu
                return $this->redirectToRoute('insertCharge', ['id' => $demande->getId()]);
            }

            // Rediriger vers une autre page ou afficher un message de succès
            return $this->redirectToRoute('affichageDemande', ['id' => $demande->getId()]);
        }

        // Afficher le formulaire
        return $this->render('interne/page/insertCharge.html.twig', [
            'form' => $form->createView(),
            'beneficiaires' => $beneficiaires,
            'demande' => $demande,
        ]);
    }

//--------------------------------------------------------------------------------------------------------------
    // ajout d'une dette/crédit sur une demande spécifique - affichage des types actifs
    #[Route('/benevole/demande/ajoutDette/{id}', name: 'insertDette')]
    public function insertDette(Request $request, Demande $demande, EntityManagerInterface $entityManager, demandeRepository $demandeRepository,TypeDetteRepository $typeDetteRepository,$id ): Response
    {
        $demande = $demandeRepository->find($id);

        // Créer une nouvelle instance de Dette
        $dette = new Dette();
        $dette->setDemande($demande);

        // Récupérer les types de charges actifs
        $activeTypeDettes = $typeDetteRepository->findActiveTypeDettes();

        // Récupérer les bénéficiaires de la demande
        $beneficiaires = $demande->getBeneficiaires()->toArray();;

        // Créer le formulaire, en passant les bénéficiaires dans les options
        $form = $this->createForm(DetteType::class, $dette, [
            'beneficiaires' => $beneficiaires,
            'type_dettes' => $activeTypeDettes,
        ]);

        // Gérer la soumission du formulaire
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Associer le revenu à la demande (si besoin)
            $dette->setDemande($demande);

            // Enregistrer le revenu dans la base de données
            $entityManager->persist($dette);
            $entityManager->flush();

            // Si le bouton "Nouvelle saisie" est cliqué
            if ($request->request->has('nouvelle_saisie')) {

                // Ajouter un message flash pour informer l'utilisateur du succès
                $this->addFlash('success', 'Votre saisie a été enregistrée. Vous pouvez maintenant ajouter une nouvelle dette/crédit.');

                // Rediriger vers la même page pour saisir un nouveau revenu
                return $this->redirectToRoute('insertDette', ['id' => $demande->getId()]);
            }

            // Rediriger vers une autre page ou afficher un message de succès
            return $this->redirectToRoute('affichageDemande', ['id' => $demande->getId()]);
        }

        // Afficher le formulaire
        return $this->render('interne/page/insertDette.html.twig', [
            'form' => $form->createView(),
            'beneficiaires' => $beneficiaires,
            'demande' => $demande,
        ]);
    }

//--------------------------------------------------------------------------------------------------------------
    // modification d'un revenu sur une demande spécifique - affichage des types actifs
    #[Route('/benevole/demande/modifRevenu/{id}', name: 'modif_revenu')]
    public function modifRevenu(Request $request, Revenu $revenu, EntityManagerInterface $entityManager, revenuRepository $revenuRepository,TypeRevenuRepository $typeRevenuRepository, $id): Response
    {
        $revenu = $revenuRepository->find($id);

        // Récupérer la demande liée au revenu
        $demande = $revenu->getDemande();

        // Récupérer les types de revenus actifs
        $activeTypeRevenus = $typeRevenuRepository->findActiveTypeRevenus();

        // Récupérer les bénéficiaires de la demande
        $beneficiaires = $demande->getBeneficiaires()->toArray();;

        // Créer le formulaire, en passant les bénéficiaires dans les options
        $form = $this->createForm(RevenuType::class, $revenu, [
            'beneficiaires' => $beneficiaires,
            'type_revenus' => $activeTypeRevenus,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            // Rediriger après la modification
            return $this->redirectToRoute('affichageDemande', ['id' => $demande->getId()]);
        }

        return $this->render('interne/page/insertRevenu.html.twig', [
            'form' => $form->createView(),
            'demande' => $demande,
            'beneficiaires' => $beneficiaires,
            'revenu' => $revenu,
        ]);
    }

//--------------------------------------------------------------------------------------------------------------
    // suppression d'un revenu sur une demande spécifique
    #[Route('/benevole/demande/deleteRevenu/{id}', name: 'delete_revenu')]
    public function deleteRevenu(Revenu $revenu, EntityManagerInterface $entityManager): Response
    {
        // permet de connaitre l'id de la demande concernée pour reroutage
        $demandeId = $revenu->getDemande()->getId();

        $entityManager->remove($revenu);
        $entityManager->flush();

        return $this->redirectToRoute('affichageDemande', ['id' => $demandeId]);
    }

//--------------------------------------------------------------------------------------------------------------
    // modification d'une charge sur une demande spécifique - affichage des types actifs
    #[Route('/benevole/demande/modifcharge/{id}', name: 'modif_charge')]
    public function modifCharge(Request $request, Charge $charge, EntityManagerInterface $entityManager, chargeRepository $chargeRepository, TypeChargeRepository $typeChargeRepository, $id): Response
    {
        $charge = $chargeRepository->find($id);

        // Récupérer la demande liée au revenu
        $demande = $charge->getDemande();

        // Récupérer les types de charges actifs
        $activeTypeCharges = $typeChargeRepository->findActiveTypeCharges();

        // Récupérer les bénéficiaires de la demande
        $beneficiaires = $demande->getBeneficiaires()->toArray();;

        // Créer le formulaire, en passant les bénéficiaires dans les options
        $form = $this->createForm(ChargeType::class, $charge, [
            'beneficiaires' => $beneficiaires,
            'type_charges' => $activeTypeCharges,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            // Rediriger après la modification
            return $this->redirectToRoute('affichageDemande', ['id' => $demande->getId()]);
        }

        return $this->render('interne/page/insertCharge.html.twig', [
            'form' => $form->createView(),
            'demande' => $demande,
            'beneficiaires' => $beneficiaires,
            'charge' => $charge,
        ]);
    }

//--------------------------------------------------------------------------------------------------------------
    // suppression d'une charge sur une demande spécifique
    #[Route('/benevole/demande/deleteCharge/{id}', name: 'delete_charge')]
    public function deleteCharge(charge $charge, EntityManagerInterface $entityManager): Response
    {
        // permet de connaitre l'id de la demande concernée pour reroutage
        $demandeId = $charge->getDemande()->getId();

        $entityManager->remove($charge);
        $entityManager->flush();

        return $this->redirectToRoute('affichageDemande', ['id' => $demandeId]);
    }


//--------------------------------------------------------------------------------------------------------------
    // modification d'une dette/crédit sur une demande spécifique - affichage des types actifs
    #[Route('/benevole/demande/modifdette/{id}', name: 'modif_dette')]
    public function modifDette(Request $request, Dette $dette, EntityManagerInterface $entityManager, detteRepository $detteRepository, TypeDetteRepository $typeDetteRepository, $id): Response
    {
        $dette = $detteRepository->find($id);

        // Récupérer la demande liée au revenu
        $demande = $dette->getDemande();

        // Récupérer les types de charges actifs
        $activeTypeDettes = $typeDetteRepository->findActiveTypeDettes();

        // Récupérer les bénéficiaires de la demande
        $beneficiaires = $demande->getBeneficiaires()->toArray();;

        // Créer le formulaire, en passant les bénéficiaires dans les options
        $form = $this->createForm(DetteType::class, $dette, [
            'beneficiaires' => $beneficiaires,
            'type_dettes' => $activeTypeDettes,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            // Rediriger après la modification
            return $this->redirectToRoute('affichageDemande', ['id' => $demande->getId()]);
        }

        return $this->render('interne/page/insertDette.html.twig', [
            'form' => $form->createView(),
            'demande' => $demande,
            'beneficiaires' => $beneficiaires,
            'dette' => $dette,
        ]);
    }

//--------------------------------------------------------------------------------------------------------------
    // suppression d'une dette/crédit sur une demande spécifique
    #[Route('/benevole/demande/deletedette/{id}', name: 'delete_dette')]
    public function deleteDette(dette $dette, EntityManagerInterface $entityManager): Response
    {
        // permet de connaitre l'id de la demande concernée pour reroutage
        $demandeId = $dette->getDemande()->getId();

        $entityManager->remove($dette);
        $entityManager->flush();

        return $this->redirectToRoute('affichageDemande', ['id' => $demandeId]);
    }

//--------------------------------------------------------------------------------------------------------------
    // ajout d'un nouveau statut sur une demande - tient compte du type de demande
    #[Route('/benevole/demande/insert-evoldoss/{id}', name: 'insert_evoldoss')]
    public function insertEvoldoss(Demande $demande, Request $request, EntityManagerInterface $entityManager): Response
    {
        $historiqueAvct = new HistoriqueAvct();
        $form = $this->createForm(HistoriqueAvctType::class, $historiqueAvct, [
            'type_demande' => $demande->getTypeDemande(),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $historiqueAvct->setDemande($demande);
            $historiqueAvct->setCreatedAt(new \DateTimeImmutable());
            $entityManager->persist($historiqueAvct);
            $entityManager->flush();

            $this->addFlash('success', 'L\'avancement a été ajouté.');

            return $this->redirectToRoute('affichageDemande', ['id' => $demande->getId()]);
        }

        return $this->render('interne/page/insertEvoldoss.html.twig', [
            'form' => $form->createView(),
            'demande' => $demande,
        ]);
    }

//--------------------------------------------------------------------------------------------------------------
    // modification des données d'une demande spécifique
    #[Route('/benevole/modificationDemande/{id}', name: 'modif_demande')]

        public function modifDemande(Demande $demande, Request $request, EntityManagerInterface $entityManager, siteRepository $siteRepository, origineRepository $origineRepository): Response
    {
        // Récupérer les sites actifs
        $activeSite = $siteRepository->findActiveSite();

        // Récupérer les origines actives
        $activeOrigine = $origineRepository->findActiveOrigine();

        // Créer le formulaire basé sur l'entité Demande
        $form = $this->createForm(ModifDemandeType::class, $demande,[
            'origines' => $activeOrigine,
            'sites' => $activeSite,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Met à jour les données de la demande si bouton save
                $entityManager->flush();

            // Redirige vers la vue
            return $this->redirectToRoute('affichageDemande', ['id' => $demande->getId()]);
        }

        return $this->render('interne/page/modifDemande.html.twig', [
            'form' => $form->createView(),
            'demande' => $demande,
        ]);
    }

//--------------------------------------------------------------------------------------------------------------



}