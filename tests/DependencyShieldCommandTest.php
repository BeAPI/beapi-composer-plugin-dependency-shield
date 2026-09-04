<?php

namespace BEAPI\Composer\DependencyShieldPlugin\Tests;

use BEAPI\Composer\DependencyShieldPlugin\Checker;
use BEAPI\Composer\DependencyShieldPlugin\Command\DependencyShieldCommand;
use Composer\Composer;
use Composer\IO\BufferIO;
use Composer\IO\IOInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class DependencyShieldCommandTest extends TestCase
{
    public function testReturnsZeroWhenCheckPasses(): void
    {
        $command = $this->createCommandWithChecker($this->createPassingChecker());

        self::assertSame(0, $this->executeCommand($command));
    }

    public function testReturnsOneWhenCheckThrows(): void
    {
        $command = $this->createCommandWithChecker($this->createFailingChecker());

        self::assertSame(1, $this->executeCommand($command));
    }

    /**
     * @return Checker&MockObject
     */
    private function createPassingChecker(): Checker
    {
        $checker = $this->createMock(Checker::class);
        $checker->expects(self::once())->method('check');

        return $checker;
    }

    /**
     * @return Checker&MockObject
     */
    private function createFailingChecker(): Checker
    {
        $checker = $this->createMock(Checker::class);
        $checker->expects(self::once())
            ->method('check')
            ->willThrowException(new RuntimeException('Dependency Shield found incompatible WordPress plugin requirements.'));

        return $checker;
    }

    private function createCommandWithChecker(Checker $checker): DependencyShieldCommand
    {
        $composer = $this->createMock(Composer::class);

        return new class($checker, $composer) extends DependencyShieldCommand {
            /** @var Checker */
            private $checker;

            /** @var Composer */
            private $composer;

            public function __construct(Checker $checker, Composer $composer)
            {
                parent::__construct();
                $this->checker = $checker;
                $this->composer = $composer;
            }

            protected function resolveComposerInstance(): Composer
            {
                return $this->composer;
            }

            protected function createChecker(Composer $composer, IOInterface $io): Checker
            {
                return $this->checker;
            }

            public function getIO()
            {
                return new BufferIO();
            }
        };
    }

    private function executeCommand(DependencyShieldCommand $command): int
    {
        $method = new ReflectionMethod($command, 'execute');
        $method->setAccessible(true);

        return $method->invoke($command, new ArrayInput([]), new BufferedOutput());
    }
}
