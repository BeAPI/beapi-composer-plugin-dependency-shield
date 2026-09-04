<?php

namespace BEAPI\Composer\DependencyShieldPlugin\Command;

use BEAPI\Composer\DependencyShieldPlugin\Checker;
use Composer\Command\BaseCommand;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DependencyShieldCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->setName('dependency-shield');
        $this->setDescription(
            'Check WordPress plugin PHP/WP header requirements against the current project.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $composer = $this->getComposer();
        $io = $this->getIO();

        try {
            (new Checker($composer, $io))->check();
        } catch (RuntimeException $e) {
            return 1;
        }

        return 0;
    }
}
