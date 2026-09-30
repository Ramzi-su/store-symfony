<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Form\LoginFormType;
use App\Repository\UserRepository;
use App\Service\TwilioService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    private TwilioService $twilioService;
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;

    public function __construct(
        TwilioService $twilioService,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository
    ) {
        $this->twilioService = $twilioService;
        $this->entityManager = $entityManager;
        $this->userRepository = $userRepository;
    }
    #[Route('/redirect-after-login', name: 'app_redirect_after_login')]
    public function redirectAfterLogin(): Response
    {
    $user = $this->getUser();

    if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
        return $this->redirectToRoute('admin_dashboard');
    }

    return $this->redirectToRoute('app_account');
    }
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        RateLimiterFactoryInterface $registrationLimiter
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Each registration sends a paid SMS: limit per client IP.
            if (!$registrationLimiter->create($request->getClientIp())->consume()->isAccepted()) {
                $this->addFlash('danger', 'Trop d’inscriptions depuis votre connexion. Veuillez réessayer plus tard.');
                return $this->redirectToRoute('app_register');
            }

            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            $verificationCode = random_int(100000, 999999);
            $user->setVerificationCode($verificationCode);
            $user->setIsVerified(false);
            $user->setCreatedAt(new \DateTimeImmutable());
            $user->setRoles(['ROLE_USER']);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $sent = $this->twilioService->sendSms(
                $user->getPhoneNumber(),
                "Votre code de vérification est : $verificationCode"
            );
            if (!$sent) {
                // --- DÉBUT : MODE DÉBOGAGE / CONTOURNEMENT TWILIO ---
                // À SUPPRIMER EN PRODUCTION (Remettre le message d'erreur normal ci-dessous)
                $this->addFlash('warning', "Le SMS n'a pas pu être envoyé. Code de secours (pour tester) : $verificationCode");
                // Message normal pour la production :
                // $this->addFlash('danger', 'Le SMS n’a pas pu être envoyé. Utilisez « Renvoyer le code ».');
                // --- FIN : MODE DÉBOGAGE ---
            }

            return $this->redirectToRoute('app_verify_account', [
                'email' => $user->getEmail()
            ]);
        }

        return $this->render('security/register.html.twig', [
            // Passing the form (not its view) makes Symfony answer 422 when it is invalid.
            'registrationForm' => $form,
        ]);
    }
    #[Route('/login', name: 'app_login')]
    public function login(
    AuthenticationUtils $authenticationUtils,
    FormFactoryInterface $formFactory,
    Request $request
    ): Response {
    // Si l'utilisateur est déjà connecté
     $user = $this->getUser();
     if ($user) {
        // Redirection selon le rôle
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return $this->redirectToRoute('admin_dashboard');
        } else {
            return $this->redirectToRoute('app_account');
        }
        }

     $error = $authenticationUtils->getLastAuthenticationError();
     $lastUsername = $authenticationUtils->getLastUsername();

        $form = $formFactory->create(LoginFormType::class, [
         'email' => $lastUsername
    ]);

        return $this->render('security/login.html.twig', [
        'loginForm' => $form->createView(),
        'last_username' => $lastUsername,
        'error' => $error,
        'verified' => $request->query->get('verified')
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('Logout is handled by Symfony.');
    }
}
