<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Entity\User;

#[AsCommand(
    name: 'app:make-admin',
    description: 'Create a new admin from the command line',
)]
class MakeAdminCommand extends Command
{
    public function __construct(EntityManagerInterface $manager, UserPasswordHasherInterface $passwordHasher)
    {
        $this->manager = $manager;
        $this->passwordHasher = $passwordHasher;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'Admin Username')
            ->addArgument('email', InputArgument::REQUIRED, 'Admin Email Address')
            ->addArgument('password', InputArgument::REQUIRED, 'Admin Password')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $username = $input->getArgument('username');
        $newAdmin = new User();
        $newAdmin->setUsername($username);
        $newAdmin->setPassword($this->passwordHasher->hashPassword($newAdmin, $input->getArgument('password')));
        $newAdmin->setEmail($input->getArgument('email'));
        $newAdmin->setPubKey('0000000000000000000000000000000000000000000=');
        $newAdmin->setContactMethod('Email');
        $this->manager->persist($newAdmin);
        $this->manager->flush();

        $io->success("Admin $username successfully created!");

        return Command::SUCCESS;
    }
}
