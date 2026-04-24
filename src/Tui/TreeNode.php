<?php

declare(strict_types=1);

namespace PhpOpcua\Cli\Tui;

use PhpOpcua\Client\Types\NodeClass;
use PhpOpcua\Client\Types\ReferenceDescription;

/**
 * A single node in the interactive explore tree. Children are lazy-loaded on first expansion.
 */
class TreeNode
{
    /** @var TreeNode[] */
    public array $children = [];

    public bool $expanded = false;

    public bool $childrenLoaded = false;

    public bool $hasChildren;

    /**
     * @param ReferenceDescription $ref
     */
    public function __construct(
        public readonly ReferenceDescription $ref,
    ) {
        $this->hasChildren = ! in_array(
            $ref->nodeClass,
            [NodeClass::Variable, NodeClass::Method, NodeClass::Unspecified],
            true,
        );
    }
}
