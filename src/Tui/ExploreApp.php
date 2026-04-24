<?php

declare(strict_types=1);

namespace PhpOpcua\Cli\Tui;

use PhpOpcua\Client\OpcUaClientInterface;
use PhpOpcua\Client\Types\NodeClass;
use PhpOpcua\Client\Types\NodeId;
use PhpOpcua\Client\Types\ReferenceDescription;
use PhpOpcua\Client\Types\StatusCode;
use PhpTui\Term\Event\CharKeyEvent;
use PhpTui\Term\Event\CodedKeyEvent;
use PhpTui\Term\KeyCode;
use PhpTui\Term\Terminal;
use PhpTui\Tui\Color\AnsiColor;
use PhpTui\Tui\Display\Display;
use PhpTui\Tui\Extension\Core\Widget\BlockWidget;
use PhpTui\Tui\Extension\Core\Widget\GridWidget;
use PhpTui\Tui\Extension\Core\Widget\List\ListItem;
use PhpTui\Tui\Extension\Core\Widget\List\ListState;
use PhpTui\Tui\Extension\Core\Widget\ListWidget;
use PhpTui\Tui\Extension\Core\Widget\ParagraphWidget;
use PhpTui\Tui\Layout\Constraint;
use PhpTui\Tui\Style\Style;
use PhpTui\Tui\Text\Title;
use PhpTui\Tui\Widget\Borders;
use PhpTui\Tui\Widget\Direction;
use PhpTui\Tui\Widget\Widget;

/**
 * Interactive TUI state + event loop for the `explore` command.
 */
class ExploreApp
{
    private const MAX_LOGS = 100;

    /** @var TreeNode[] */
    private array $roots = [];

    /** @var array{node: TreeNode, depth: int}[] */
    private array $visibleRows = [];

    private ListState $listState;

    /** @var string[] */
    private array $logs = [];

    /** @var array<string, array{value: string, type: string, status: string, sourceTs: string}|array{error: string}> */
    private array $valueCache = [];

    private bool $running = true;

    /**
     * @param OpcUaClientInterface $client
     * @param Terminal $terminal
     * @param Display $display
     */
    public function __construct(
        private readonly OpcUaClientInterface $client,
        private readonly Terminal $terminal,
        private readonly Display $display,
    ) {
        $this->listState = new ListState(0, null);
        $this->log('Connected. Browsing Objects folder (i=85)...');
        $this->loadRoots(NodeId::parse('i=85'));
    }

    /**
     * @return int
     */
    public function run(): int
    {
        while ($this->running) {
            while (($event = $this->terminal->events()->next()) !== null) {
                $this->handleEvent($event);
                if (! $this->running) {
                    break;
                }
            }
            $this->display->draw($this->buildLayout());
            usleep(50_000);
        }

        return 0;
    }

    /**
     * @param mixed $event
     * @return void
     */
    private function handleEvent(mixed $event): void
    {
        if ($event instanceof CharKeyEvent) {
            match ($event->char) {
                'q' => $this->running = false,
                'r' => $this->refreshValueForCurrent(force: true),
                default => null,
            };

            return;
        }

        if ($event instanceof CodedKeyEvent) {
            match ($event->code) {
                KeyCode::Up => $this->moveSelection(-1),
                KeyCode::Down => $this->moveSelection(1),
                KeyCode::Right, KeyCode::Enter => $this->expandSelected(),
                KeyCode::Left => $this->collapseOrAscend(),
                KeyCode::Esc => $this->running = false,
                default => null,
            };
        }
    }

    /**
     * @param int $delta
     * @return void
     */
    private function moveSelection(int $delta): void
    {
        if ($this->visibleRows === []) {
            return;
        }

        $current = $this->listState->selected ?? 0;
        $max = count($this->visibleRows) - 1;
        $this->listState->selected = max(0, min($max, $current + $delta));
        $this->refreshValueForCurrent();
    }

    /**
     * @return void
     */
    private function expandSelected(): void
    {
        $row = $this->currentRow();
        if ($row === null) {
            return;
        }
        $node = $row['node'];

        if ($node->expanded) {
            return;
        }
        if (! $node->childrenLoaded) {
            $this->loadChildren($node);
        }
        if ($node->children === []) {
            $node->hasChildren = false;

            return;
        }
        $node->hasChildren = true;
        $node->expanded = true;
        $this->rebuildVisibleRows();
    }

    /**
     * @return void
     */
    private function collapseOrAscend(): void
    {
        $row = $this->currentRow();
        if ($row === null) {
            return;
        }
        $node = $row['node'];

        if ($node->expanded) {
            $node->expanded = false;
            $this->rebuildVisibleRows();

            return;
        }

        $selected = $this->listState->selected;
        if ($selected === null) {
            return;
        }
        $currentDepth = $row['depth'];
        for ($i = $selected - 1; $i >= 0; $i--) {
            if ($this->visibleRows[$i]['depth'] < $currentDepth) {
                $this->listState->selected = $i;

                return;
            }
        }
    }

    /**
     * @return ?array{node: TreeNode, depth: int}
     */
    private function currentRow(): ?array
    {
        $selected = $this->listState->selected;
        if ($selected === null || ! isset($this->visibleRows[$selected])) {
            return null;
        }

        return $this->visibleRows[$selected];
    }

    /**
     * @param NodeId $parent
     * @return void
     */
    private function loadRoots(NodeId $parent): void
    {
        try {
            $refs = $this->client->browseAll($parent);
            $this->roots = array_map(static fn (ReferenceDescription $ref): TreeNode => new TreeNode($ref), $refs);
            $this->rebuildVisibleRows();
            $this->listState = new ListState(0, $this->roots === [] ? null : 0);
            $this->log(sprintf('Loaded %d root children.', count($refs)));
            $this->refreshValueForCurrent();
        } catch (\Throwable $e) {
            $this->log('Browse failed: ' . $e->getMessage());
        }
    }

    /**
     * @param bool $force
     * @return void
     */
    private function refreshValueForCurrent(bool $force = false): void
    {
        $row = $this->currentRow();
        if ($row === null) {
            return;
        }
        $node = $row['node'];
        if ($node->ref->nodeClass !== NodeClass::Variable) {
            return;
        }

        $key = (string) $node->ref->nodeId;
        if (! $force && isset($this->valueCache[$key])) {
            return;
        }

        try {
            $dv = $this->client->read($node->ref->nodeId);
            $variant = $dv->getVariant();
            $statusCode = $dv->getStatusCode();
            $this->valueCache[$key] = [
                'value' => $this->formatValue($dv->getValue()),
                'type' => $variant?->type->name ?? '?',
                'status' => sprintf('%s (0x%08X)', StatusCode::getName($statusCode), $statusCode),
                'sourceTs' => $dv->getSourceTimestamp()?->format('c') ?? '-',
            ];
            if ($force) {
                $this->log('Refreshed ' . $key);
            }
        } catch (\Throwable $e) {
            $this->valueCache[$key] = ['error' => $e->getMessage()];
            $this->log('Read failed for ' . $key . ': ' . $e->getMessage());
        }
    }

    /**
     * @param mixed $value
     * @return string
     */
    private function formatValue(mixed $value): string
    {
        if ($value === null) {
            return '(null)';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '(unserialisable)';
    }

    /**
     * @param TreeNode $node
     * @return void
     */
    private function loadChildren(TreeNode $node): void
    {
        try {
            $refs = $this->client->browseAll($node->ref->nodeId);
            $node->children = array_map(static fn (ReferenceDescription $ref): TreeNode => new TreeNode($ref), $refs);
            $node->childrenLoaded = true;
            $this->log(sprintf('Browsed %s: %d children.', (string) $node->ref->nodeId, count($refs)));
        } catch (\Throwable $e) {
            $node->childrenLoaded = true;
            $node->children = [];
            $this->log('Browse failed: ' . $e->getMessage());
        }
    }

    /**
     * @return void
     */
    private function rebuildVisibleRows(): void
    {
        $rows = [];
        foreach ($this->roots as $root) {
            $this->flattenNode($root, 0, $rows);
        }
        $this->visibleRows = $rows;

        if ($this->visibleRows === []) {
            $this->listState->selected = null;

            return;
        }
        $max = count($this->visibleRows) - 1;
        $this->listState->selected = max(0, min($max, $this->listState->selected ?? 0));
    }

    /**
     * @param TreeNode $node
     * @param int $depth
     * @param array{node: TreeNode, depth: int}[] $rows
     * @return void
     */
    private function flattenNode(TreeNode $node, int $depth, array &$rows): void
    {
        $rows[] = ['node' => $node, 'depth' => $depth];
        if (! $node->expanded) {
            return;
        }
        foreach ($node->children as $child) {
            $this->flattenNode($child, $depth + 1, $rows);
        }
    }

    /**
     * @param string $message
     * @return void
     */
    private function log(string $message): void
    {
        $this->logs[] = sprintf('[%s] %s', date('H:i:s'), $message);
        if (count($this->logs) > self::MAX_LOGS) {
            $this->logs = array_slice($this->logs, -self::MAX_LOGS);
        }
    }

    /**
     * @return Widget
     */
    private function buildLayout(): Widget
    {
        return GridWidget::default()
            ->direction(Direction::Vertical)
            ->constraints(
                Constraint::percentage(70),
                Constraint::min(3),
            )
            ->widgets(
                $this->buildMainPane(),
                $this->buildLogPane(),
            );
    }

    /**
     * @return Widget
     */
    private function buildMainPane(): Widget
    {
        return GridWidget::default()
            ->direction(Direction::Horizontal)
            ->constraints(
                Constraint::percentage(50),
                Constraint::percentage(50),
            )
            ->widgets(
                $this->buildTreePane(),
                $this->buildDetailsPane(),
            );
    }

    /**
     * @return Widget
     */
    private function buildTreePane(): Widget
    {
        $items = [];
        foreach ($this->visibleRows as $row) {
            $node = $row['node'];
            $marker = $node->expanded ? '▾' : ($node->hasChildren ? '▸' : ' ');
            $items[] = ListItem::fromString(sprintf(
                '%s%s %s [%s]',
                str_repeat('  ', $row['depth']),
                $marker,
                (string) $node->ref->displayName,
                $node->ref->nodeClass->name,
            ));
        }

        $list = ListWidget::default()
            ->items(...$items)
            ->state($this->listState)
            ->highlightSymbol('› ')
            ->highlightStyle(Style::default()->bg(AnsiColor::Blue)->fg(AnsiColor::White));

        return BlockWidget::default()
            ->borders(Borders::ALL)
            ->titles(Title::fromString(' Address space '))
            ->widget($list);
    }

    /**
     * @return Widget
     */
    private function buildDetailsPane(): Widget
    {
        $row = $this->currentRow();
        if ($row === null) {
            $text = 'No selection.';
        } else {
            $node = $row['node'];
            $text = sprintf(
                "NodeId:      %s\nBrowseName:  %s\nDisplayName: %s\nNodeClass:   %s",
                (string) $node->ref->nodeId,
                (string) $node->ref->browseName,
                (string) $node->ref->displayName,
                $node->ref->nodeClass->name,
            );

            if ($node->ref->nodeClass === NodeClass::Variable) {
                $key = (string) $node->ref->nodeId;
                $cached = $this->valueCache[$key] ?? null;
                if ($cached === null) {
                    $text .= "\n\nValue:       (reading…)";
                } elseif (isset($cached['error'])) {
                    $text .= "\n\nValue:       <error>\nError:       " . $cached['error'];
                } else {
                    $text .= sprintf(
                        "\n\nValue:       %s\nType:        %s\nStatus:      %s\nSource:      %s",
                        $cached['value'],
                        $cached['type'],
                        $cached['status'],
                        $cached['sourceTs'],
                    );
                }
            }
        }

        return BlockWidget::default()
            ->borders(Borders::ALL)
            ->titles(Title::fromString(' Details '))
            ->widget(ParagraphWidget::fromString($text));
    }

    /**
     * @return Widget
     */
    private function buildLogPane(): Widget
    {
        $text = $this->logs === []
            ? '(no log messages yet)'
            : implode("\n", array_slice($this->logs, -20));

        return BlockWidget::default()
            ->borders(Borders::ALL)
            ->titles(Title::fromString(' Log — q=quit  ↑↓=move  →/Enter=expand  ←=collapse/up  r=refresh '))
            ->widget(ParagraphWidget::fromString($text));
    }
}
