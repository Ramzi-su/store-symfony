<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Crée un nouvel utilisateur Administrateur',
)]
class CreateAdminCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'L\'email de l\'administrateur')
            ->addArgument('password', InputArgument::REQUIRED, 'Le mot de passe de l\'administrateur')
            ->addArgument('phone', InputArgument::OPTIONAL, 'Le numéro de téléphone', '+33600000000')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');
        $password = $input->getArgument('password');
        $phone = $input->getArgument('phone');

        // Vérifier si l'utilisateur existe déjà
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            $existingUser->setRoles(array_unique(array_merge($existingUser->getRoles(), ['ROLE_ADMIN'])));
            $existingUser->setIsVerified(true);
            $this->entityManager->flush();
            $io->success(sprintf('Le compte existant %s a été promu Administrateur et vérifié avec succès !', $email));
            return Command::SUCCESS;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setPhoneNumber($phone);
        $user->setFirstName('Admin');
        $user->setLastName('System');
        $user->setRoles(['ROLE_ADMIN']);
        $user->setIsVerified(true);
        $user->setCreatedAt(new \DateTimeImmutable());
        
        // Hachage du mot de passe
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('Le compte Administrateur pour %s a été créé avec succès !', $email));

        return Command::SUCCESS;
    }
}
