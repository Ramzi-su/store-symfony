<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\VerifyCodeType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\TwilioService;

class VerifyAccountController extends AbstractController
{
    #[Route('/verify', name: 'app_verify_account')]
    public function verify(Request $request, UserRepository $userRepository, EntityManagerInterface $em): Response
    {
        $session = $request->getSession();
        $attempts = $session->get('verify_attempts', 0);

        if ($attempts >= 5) {
            $this->addFlash('danger', 'Trop de tentatives. Veuillez réessayer plus tard.');
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(VerifyCodeType::class, [
            'email' => $request->query->get('email')
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $user = $userRepository->findOneBy(['email' => $data['email']]);

            if (!$user) {
                $this->addFlash('danger', 'Aucun utilisateur trouvé avec cet email.');
            } elseif ($user->getIsVerified()) {
                $this->addFlash('info', 'Ce compte est déjà vérifié.');
                return $this->redirectToRoute('app_login');
            } elseif ((string)$user->getVerificationCode() === (string)$data['code']) {
                $user->setIsVerified(true);
                $user->setVerificationCode(null);
                $em->flush();
                $session->remove('verify_attempts');

                $this->addFlash('success', 'Votre compte a été vérifié avec succès !');
                return $this->redirectToRoute('app_login');
            } else {
                $session->set('verify_attempts', $attempts + 1);
                $this->addFlash('danger', 'Code de vérification incorrect.');
            }
        }

        return $this->render('verify/code.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/verify/resend', name: 'app_resend_code')]
    public function resend(Request $request, UserRepository $userRepository, EntityManagerInterface $em, TwilioService $twilio): Response
    {
        $email = $request->query->get('email');
        $user = $userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            $this->addFlash('danger', 'Utilisateur introuvable.');
        } elseif ($user->getIsVerified()) {
            $this->addFlash('info', 'Ce compte est déjà vérifié.');
            return $this->redirectToRoute('app_login');
        } else {
            $code = mt_rand(100000, 999999);
            $user->setVerificationCode($code);
            $em->flush();

            $twilio->sendSms($user->getPhoneNumber(), "Votre nouveau code est : $code");

            $this->addFlash('success', 'Un nouveau code a été envoyé par SMS.');
        }

        return $this->redirectToRoute('app_verify_account', ['email' => $email]);
    }
}
