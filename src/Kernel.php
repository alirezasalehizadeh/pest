<?php

declare(strict_types=1);

namespace Pest;

use Pest\Contracts\Bootstrapper;
use Pest\Exceptions\NoDirtyTestsFound;
use Pest\Plugins\Actions\CallsAddsOutput;
use Pest\Plugins\Actions\CallsBoot;
use Pest\Plugins\Actions\CallsHandleArguments;
use Pest\Plugins\Actions\CallsShutdown;
use Pest\Support\Container;
use PHPUnit\TextUI\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class Kernel
{
    /**
     * The Kernel bootstrappers.
     *
     * @var array<int, class-string>
     */
    private const BOOTSTRAPPERS = [
        Bootstrappers\BootOverrides::class,
        Bootstrappers\BootSubscribers::class,
        Bootstrappers\BootFiles::class,
        Bootstrappers\BootView::class,
        Bootstrappers\BootKernelDump::class,
    ];

    /**
     * Creates a new Kernel instance.
     */
    public function __construct(
        private readonly Application $application,
        private readonly OutputInterface $output,
    ) {
        register_shutdown_function(function (): void {
            if (error_get_last() !== null) {
                return;
            }

            $this->shutdown();
        });
    }

    /**
     * Boots the Kernel.
     */
    public static function boot(TestSuite $testSuite, InputInterface $input, OutputInterface $output): self
    {
        $container = Container::getInstance();

        $container
            ->add(TestSuite::class, $testSuite)
            ->add(InputInterface::class, $input)
            ->add(OutputInterface::class, $output)
            ->add(Container::class, $container);

        foreach (self::BOOTSTRAPPERS as $bootstrapper) {
            $bootstrapper = Container::getInstance()->get($bootstrapper);
            assert($bootstrapper instanceof Bootstrapper);

            $bootstrapper->boot();
        }

        CallsBoot::execute();

        return new self(
            new Application(),
            $output,
        );
    }

    /**
     * Runs the application, and returns the exit code.
     *
     * @param  array<int, string>  $args
     */
    public function handle(array $args): int
    {
        $args = CallsHandleArguments::execute($args);

        try {
            $this->application->run($args);
        } catch (NoDirtyTestsFound) {
            $this->output->writeln([
                '',
                '  <fg=white;options=bold;bg=blue> INFO </> No tests found.',
                '',
            ]);
        }

        return CallsAddsOutput::execute(
            Result::exitCode(),
        );
    }

    /**
     * Shutdown the Kernel.
     */
    public function shutdown(): void
    {
        $preBufferOutput = Container::getInstance()->get(KernelDump::class);

        assert($preBufferOutput instanceof KernelDump);

        $preBufferOutput->shutdown();

        CallsShutdown::execute();
    }
}
