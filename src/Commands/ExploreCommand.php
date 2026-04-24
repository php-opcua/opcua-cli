<?php

declare(strict_types=1);

namespace PhpOpcua\Cli\Commands;

use PhpOpcua\Cli\Output\OutputInterface;
use PhpOpcua\Cli\Tui\ExploreApp;
use PhpOpcua\Client\ClientBuilder;
use PhpOpcua\Client\OpcUaClientInterface;
use PhpTui\Term\Actions;
use PhpTui\Term\Terminal;
use PhpTui\Tui\DisplayBuilder;

/**
 * Interactive TUI browser for the OPC UA address space.
 */
class ExploreCommand implements CommandInterface
{
    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'explore';
    }

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return 'Interactively explore the server address space (TUI)';
    }

    /**
     * {@inheritDoc}
     */
    public function getUsage(): string
    {
        return 'explore <endpoint>';
    }

    /**
     * {@inheritDoc}
     */
    public function requiresConnection(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function execute(OpcUaClientInterface|ClientBuilder $client, array $arguments, array $options, OutputInterface $output): int
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $output->error('Error: the "explore" command is not yet supported on Windows.');
            $output->writeln('Track progress at https://github.com/php-tui/php-tui (upstream TUI library does not yet support Windows).');

            return 1;
        }

        if (isset($options['json'])) {
            $output->error('Error: --json is incompatible with the interactive "explore" command.');

            return 1;
        }

        if (isset($options['debug']) && $options['debug'] === true) {
            $output->error('Error: --debug writes to stdout and would corrupt the TUI. Use --debug-stderr or --debug-file=<path>.');

            return 1;
        }

        $terminal = Terminal::new();
        $display = DisplayBuilder::default()->fullscreen()->build();

        $terminal->execute(Actions::cursorHide());
        $terminal->execute(Actions::alternateScreenEnable());
        $terminal->enableRawMode();

        try {
            $app = new ExploreApp($client, $terminal, $display);
            $exitCode = $app->run();
        } finally {
            $terminal->disableRawMode();
            $terminal->execute(Actions::alternateScreenDisable());
            $terminal->execute(Actions::cursorShow());
        }

        return $exitCode;
    }
}
