<?php
/** 
namespace App\Controller;

use App\Service\TwilioService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SmsController extends AbstractController
{
    #[Route('/send-sms', name: 'send_sms')]
    public function sendSms(TwilioService $twilioService): Response
    {
        $to = '+21620892088';
        $message = 'Bonjour ! Ceci est un message Twilio depuis Symfony 😎';

        $success = $twilioService->sendSms($to, $message);

        return new Response($success ? 'SMS envoyé avec succès ✅' : 'Erreur lors de l’envoi ❌');
    }
}
*/
namespace App\Controller;

use App\Entity\User;
use App\Service\TwilioService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class SmsController extends AbstractController
{
    private $entityManager;
    private $twilioService;

    public function __construct(EntityManagerInterface $entityManager, TwilioService $twilioService)
    {
        $this->entityManager = $entityManager;
        $this->twilioService = $twilioService;
    }

    #[Route('/verify-phone', name: 'verify_phone')]
    public function verifyPhone(Request $request, UserPasswordEncoderInterface $passwordEncoder): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $phoneNumber = $request->request->get('phone_number');
        $verificationCode = rand(100000, 999999); // Génération d'un code aléatoire de 6 chiffres

        // Mise à jour de l'utilisateur avec le code de vérification et sa date d'expiration
        $user->setPhoneNumber($phoneNumber);
        $user->setVerificationCode($verificationCode);
        $user->setVerificationCodeExpiry(new \DateTime('+15 minutes'));

        // Envoi du SMS avec Twilio
        $this->twilioService->sendVerificationCode($phoneNumber, $verificationCode);

        // Sauvegarde de l'utilisateur
        $this->entityManager->flush();

        // Retourner une réponse (par exemple : une page avec un formulaire de validation du code)
        return $this->render('user/verify_phone.html.twig');
    }
}
