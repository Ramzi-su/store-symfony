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
use Symfony\Component\Routing\Annotation\Route;
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
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            $verificationCode = mt_rand(100000, 999999);
            $user->setVerificationCode($verificationCode);
            $user->setIsVerified(false);
            $user->setCreatedAt(new \DateTimeImmutable());
            $user->setRoles(['ROLE_USER']);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->twilioService->sendSms(
                $user->getPhoneNumber(),
                "Votre code de vérification est : $verificationCode"
            );

            return $this->redirectToRoute('app_verify_account', [
                'email' => $user->getEmail()
            ]);
        }

        return $this->render('security/register.html.twig', [
            'registrationForm' => $form->createView(),
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
