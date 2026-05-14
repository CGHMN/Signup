<?php

/*
CGHMN Signup Page - A PHP project to ease the process of joining CGHMN.
Copyright (C) 2026 Logan C. et al. loganius@cghmn.org

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of  MERCHANTABILITY or FITNESS FOR
A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <http://www.gnu.org/licenses/>.

Many thanks to Jonas Luehrig (Snep) for all his contributions to this project,
both through writing code and providing advice.
*/

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
        $newAdmin->setRoles(['ROLE_SUPER_ADMIN']);
        $newAdmin->setEmail($input->getArgument('email'));
        $newAdmin->setPubKey('0000000000000000000000000000000000000000000=');
        $newAdmin->setContactMethod('Email');
        $this->manager->persist($newAdmin);
        $this->manager->flush();

        $io->success("Admin $username successfully created!");

        return Command::SUCCESS;
    }
}
