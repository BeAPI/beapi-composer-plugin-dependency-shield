<?php

namespace BEAPI\Composer\DependencyShieldPlugin\Command;

use BEAPI\Composer\DependencyShieldPlugin\Checker;
use Composer\Command\BaseCommand;
use Composer\Composer;
use Composer\IO\IOInterface;
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
        $composer = $this->resolveComposerInstance();
        $io = $this->getIO();

        try {
            $this->createChecker($composer, $io)->check();
        } catch (RuntimeException $e) {
            return 1;
        }

        return 0;
    }

    /**
     * @internal Test hook.
     */
    protected function createChecker(Composer $composer, IOInterface $io): Checker
    {
        return new Checker($composer, $io);
    }

    /**
     * @internal Test hook.
     */
    protected function resolveComposerInstance(): Composer
    {
        if (method_exists($this, 'requireComposer')) {
            return $this->requireComposer();
        }

        return $this->getComposer();
    }
}
