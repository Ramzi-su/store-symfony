<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\VerifyCodeType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\TwilioService;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

class VerifyAccountController extends AbstractController
{
    #[Route('/verify', name: 'app_verify_account')]
    public function verify(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        RateLimiterFactoryInterface $verificationCodeCheckLimiter
    ): Response {
        $form = $this->createForm(VerifyCodeType::class, [
            'email' => $request->query->get('email')
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            // Limited per email (server-side), not per session: deleting the
            // session cookie no longer resets the attempt counter.
            $limiter = $verificationCodeCheckLimiter->create(mb_strtolower((string) $data['email']));
            if (!$limiter->consume()->isAccepted()) {
                $this->addFlash('danger', 'Trop de tentatives. Veuillez réessayer plus tard.');
                return $this->redirectToRoute('app_login');
            }

            $user = $userRepository->findOneBy(['email' => $data['email']]);

            if (!$user) {
                $this->addFlash('danger', 'Aucun utilisateur trouvé avec cet email.');
            } elseif ($user->getIsVerified()) {
                $this->addFlash('info', 'Ce compte est déjà vérifié.');
                return $this->redirectToRoute('app_login');
            } elseif (
                $user->getVerificationCode() !== null
                && hash_equals((string) $user->getVerificationCode(), (string) $data['code'])
            ) {
                $user->setIsVerified(true);
                $user->setVerificationCode(null);
                $em->flush();
                $limiter->reset();

                $this->addFlash('success', 'Votre compte a été vérifié avec succès !');
                return $this->redirectToRoute('app_login');
            } else {
                $this->addFlash('danger', 'Code de vérification incorrect.');
            }
        }

        return $this->render('verify/code.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    // POST + CSRF token: a link or an <img> on another site can no longer trigger SMS sends.
    #[Route('/verify/resend', name: 'app_resend_code', methods: ['POST'])]
    public function resend(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        TwilioService $twilio,
        RateLimiterFactoryInterface $verificationCodeSendLimiter
    ): Response {
        $email = (string) $request->request->get('email');

        if (!$this->isCsrfTokenValid('resend-code', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_verify_account', ['email' => $email]);
        }

        if (!$verificationCodeSendLimiter->create(mb_strtolower($email))->consume()->isAccepted()) {
            $this->addFlash('danger', 'Trop de codes envoyés. Veuillez réessayer plus tard.');
            return $this->redirectToRoute('app_verify_account', ['email' => $email]);
        }

        $user = $userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            $this->addFlash('danger', 'Utilisateur introuvable.');
        } elseif ($user->getIsVerified()) {
            $this->addFlash('info', 'Ce compte est déjà vérifié.');
            return $this->redirectToRoute('app_login');
        } else {
            // random_int() uses a cryptographically secure generator; mt_rand() is predictable.
            $code = random_int(100000, 999999);
            $user->setVerificationCode($code);
            $em->flush();

            $sent = $twilio->sendSms($user->getPhoneNumber(), "Votre nouveau code est : $code");
            if (!$sent) {
                // --- DÉBUT : MODE DÉBOGAGE / CONTOURNEMENT TWILIO ---
                // À SUPPRIMER EN PRODUCTION (Remettre le message normal ci-dessous)
                $this->addFlash('warning', "Le SMS n'a pas pu être envoyé. Code de secours (pour tester) : $code");
                // Message normal pour la production :
                // $this->addFlash('danger', 'Le SMS n’a pas pu être envoyé. Utilisez « Renvoyer le code ».');
                // --- FIN : MODE DÉBOGAGE ---
            } else {
                $this->addFlash('success', 'Un nouveau code a été envoyé par SMS.');
            }
        }

        return $this->redirectToRoute('app_verify_account', ['email' => $email]);
    }
}
