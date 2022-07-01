<?php

namespace App\Controller;

use App\Entity\Membre;
use App\Entity\Produit;
use App\Entity\Commande;
use App\Form\InscriptionType;
use App\Repository\CommandeRepository;
use App\Security\AppAuthAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

class FrontController extends AbstractController
{
    #[Route('/', name: 'app_front')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $listProduit = $em->getRepository(Produit::class)->findAll();

        return $this->render('front/index.html.twig', [
            "produits" => $listProduit
        ]);
    }
    ////////////////////////Inscription client///////////////////////////////////////////////
    #[Route('/inscription', name: 'app_register', methods: ["POST", "GET"])]
    public function inscription(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $userPasswordHasher,
        AppAuthAuthenticator $formAuthenticator,
        UserAuthenticatorInterface $authenticator
    ): Response {

        $membre = new Membre();
        $formInscription = $this->createForm(InscriptionType::class, $membre);

        $formInscription->handleRequest($request);

        if ($formInscription->isSubmitted() && $formInscription->isValid()) {
            // encode the plain password

            $membre->setPassword(
                $userPasswordHasher->hashPassword(
                    $membre,
                    $formInscription->get('password')->getData()
                )
            );
            $membre->setRoles(["ROLE_MEMBRE"]);

            $em->persist($membre);
            $em->flush();

            return $authenticator->authenticateUser(
                $membre,
                $formAuthenticator,
                $request
            );
        }
        return $this->render("front/inscription.html.twig", [
            "formInscription" => $formInscription->createView(),
        ]);
    }

    #[Route('produit/{id}', name: 'produit_detail', methods: ['GET'])]
    public function detailProduit(Produit $produit): Response
    {
        return $this->render('front/detailProduit.html.twig', [
            'produit' => $produit,
        ]);
    }

///////////////////////////Commande les produits du panier////////////////////////////////////////////////
    #[Route("/panier/commander", name: "panier_commander")]
    public function commander(EntityManagerInterface $em, Request $request)
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }


        $session = $request->getSession();
        $panier = $session->get("panier");

        $produitsPanier = [];

        $i = 0;
        foreach ($panier as $id => $qte) {
            $produitsPanier[] = [
                "qte" => $qte,
                "produit" => $em->getRepository(Produit::class)->find($id)
            ];

            $commande = new Commande();

            $commande->setMembre($this->getUser())
                ->setMontant($produitsPanier[$i]["produit"]->getPrix() * $produitsPanier[$i]["qte"])
                ->setQuantite($produitsPanier[$i]["qte"])
                ->setProduit($produitsPanier[$i]["produit"])
                ->setEtat("En cours de traitement");

            $em->persist($commande);
            $em->flush();

            $i++;
        }

        $panier = $session->set("panier", []);

        return $this->redirectToRoute("app_compte");
    }

    ////////////////////////////Ajoute au panier//////////////////////////////////

    #[Route("/add_panier/{id}", name: "app_add_panier")]
    public function add_panier(Request $request)
    {
        $idProduitSelectionne = $request->attributes->get("id");
        $session = $request->getSession();

        $panier = $session->get("panier", []);

        if (!empty($panier[$idProduitSelectionne])) {
            $panier[$idProduitSelectionne]++;
        } else {
            $panier[$idProduitSelectionne] = 1;
        }

        $session->set("panier", $panier);

        $this->addFlash("message", "Le produit numéro $idProduitSelectionne a été ajouté à votre panier");
        return $this->redirectToRoute("app_front");
    }

    ///////////////////////////////Affiche le panier/////////////////////////////////////
    #[Route("/panier", name: "app_panier")]
    public function panier(EntityManagerInterface $em, Request $request)
    {
        $session = $request->getSession();
        $panier = $session->get("panier");
        $produitsDuPanier = [];
        foreach ($panier  as $id => $qte) {
            $produitsDuPanier[] =
                [
                    "produit" => $em->getRepository(Produit::class)->find($id),
                    "qte" => $qte
                ];
        }
        $totalPanier = 0;
        foreach ($panier  as $id => $qte) {
            $totalPanier += $qte * $em->getRepository(Produit::class)->find($id)->getPrix();
        }
        return $this->render("front/panier/panier.html.twig", compact("produitsDuPanier", "totalPanier"));
    }

    //////////////////////////////////Augmente la quantité du produit//////////////////////////////////////////

    #[Route("/panier/addquantite/{id}", name: "panier_add_quantite")]
    public function addQuantite(EntityManagerInterface $em, Request $request)
    {
        $idProduitAjouter = $request->attributes->get("id");
        $session = $request->getSession();

        $panier = $session->get("panier");
        if (!empty($panier[$idProduitAjouter])) {
            $panier[$idProduitAjouter]++;
        }
        $session->set("panier", $panier);
        return $this->redirectToRoute("app_panier");
    }

    //////////////////////////////Diminue la quantité du produit////////////////////////////////////////////

    #[Route("/panier/delquantite/{id}", name: "panier_del_quantite")]
    public function delQuantite(EntityManagerInterface $em, Request $request)
    {
        $idProduitAjouter = $request->attributes->get("id");
        $session = $request->getSession();

        $panier = $session->get("panier");
        if (!empty($panier[$idProduitAjouter])) {
            if ($panier[$idProduitAjouter] > 1) {

                $panier[$idProduitAjouter]--;
            } else {

                unset($panier[$idProduitAjouter]);
            }
        }
        $session->set("panier", $panier);
        return $this->redirectToRoute("app_panier");
    }

    ////////////////////////////////Supprime un produit du panier///////////////////////////////////////////

    #[Route("/panier/delProduit/{id}", name: "panier_del_produit")]
    public function delProduitPanier(EntityManagerInterface $em, Request $request)
    {
        $idProduitAjouter = $request->attributes->get("id");
        $session = $request->getSession();
        $panier = $session->get("panier");

        unset($panier[$idProduitAjouter]);
        $session->set("panier", $panier);
        return $this->redirectToRoute("app_panier");
    }

    ///////////////////////////////Affiche la liste des commandes dans le compte/////////////////////////////////////

    #[Route("/compte", name: "app_compte")]
    public function monCompte(EntityManagerInterface $em)
    {
        $commandes = $em->getRepository(Commande::class)->findBy(['membre' => $this->getUser()]);

        return $this->render('front/listeCommandes.html.twig', [
            'commandes' => $commandes
        ]);
    }
}
