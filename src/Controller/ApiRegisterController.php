<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\TwilioService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ApiRegisterController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        ValidatorInterface $validator,
        TwilioService $twilioService
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        foreach (['email', 'password', 'firstName', 'lastName', 'phoneNumber'] as $field) {
            if (empty($data[$field])) {
                return new JsonResponse(['error' => "$field is required"], Response::HTTP_BAD_REQUEST);
            }
        }

        $email = trim($data['email']);
        $password = trim($data['password']);
        $firstName = trim($data['firstName']);
        $lastName = trim($data['lastName']);
        $phoneNumber = trim($data['phoneNumber']);

        $violations = $validator->validate($email, [new Assert\Email()]);
        if (count($violations) > 0) {
            return new JsonResponse(['error' => $violations[0]->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            return new JsonResponse(['error' => 'User already exists.'], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setPhoneNumber($phoneNumber);
        $user->setCreatedAt(new \DateTimeImmutable());
        $user->setIsVerified(false);
        $user->setVerificationCode(mt_rand(100000, 999999));

        $hashedPassword = $passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        try {
            $twilioService->sendSms(
                $phoneNumber,
                "Votre code de vérification est : " . $user->getVerificationCode()
            );
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Erreur envoi SMS : ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['success' => 'User registered. Verification code sent.'], Response::HTTP_CREATED);
    }

    #[Route('/api/verify', name: 'api_verify', methods: ['POST'])]
    public function verifyCode(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['email']) || empty($data['code'])) {
            return new JsonResponse(['error' => 'Email and code are required.'], Response::HTTP_BAD_REQUEST);
        }

        $email = trim($data['email']);
        $code = trim($data['code']);

        /** @var User|null $user */
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user) {
            return new JsonResponse(['error' => 'User not found.'], Response::HTTP_NOT_FOUND);
        }

        if ($user->getIsVerified()) {
            return new JsonResponse(['message' => 'User is already verified.'], Response::HTTP_OK);
        }

        if ((string)$user->getVerificationCode() !== (string)$code) {
            return new JsonResponse(['error' => 'Invalid verification code.'], Response::HTTP_UNAUTHORIZED);
        }

        $user->setIsVerified(true);
        $user->setVerificationCode(null); // optionnel
        $this->entityManager->flush();

        return new JsonResponse(['success' => 'User verified successfully.'], Response::HTTP_OK);
    }
}
