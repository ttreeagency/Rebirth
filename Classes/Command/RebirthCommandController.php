<?php
declare(strict_types=1);

namespace Ttree\Rebirth\Command;

/*
 * This file is part of the Ttree.Rebirth package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 */

use Closure;
use Doctrine\Common\Collections\ArrayCollection;
use Ttree\Rebirth\Service\OrphanNodeService;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Neos\Controller\Exception\NodeNotFoundException;
use Neos\ContentRepository\Domain\Model\NodeInterface;

class RebirthCommandController extends CommandController
{
    /**
     * @var OrphanNodeService
     * @Flow\Inject
     */
    protected $orphanNodeService;

    /**
     * List orphan documents
     *
     * @param string $workspace
     * @param string $type
     */
    public function listCommand(string $workspace = 'live', string $type = 'Neos.Neos:Document'): void
    {
        $nodes = $this->orphanNodeService->listByWorkspace($workspace, $type);

        $this->output->outputTable(
            array_map(static function (array $nodeInfo) {
                $nodeInfo['info'] = sprintf('Label: %s%sPath: %s' . PHP_EOL, $nodeInfo['label'], PHP_EOL, $nodeInfo['path']);
                $nodeInfo['dimension'] = str_replace(',', PHP_EOL, $nodeInfo['dimension']);
                unset($nodeInfo['label'], $nodeInfo['path']);
                return $nodeInfo;
            }, $this->convertNodesToNodeInfo($nodes)),
            ['Site', 'Dimension', 'Identifier', 'Node Type', 'Info']
        );

        if (count($nodes)) {
            $this->outputLine('<b>Found nodes:</b> %d', [count($nodes)]);
        } else {
            $this->outputLine('<b>No orphaned document nodes</b>');
        }
    }

    /**
     * Prune orphan documents
     *
     * @param string $workspace
     * @param string $type
     */
    public function pruneAllCommand($workspace = 'live', $type = 'Neos.Neos:Document'): void
    {
        $this->command(function (NodeInterface $node) {
            $this->output->outputLine('%s <comment>%s</comment> (%s) in <b>%s</b>', [$node->getIdentifier(), $node->getLabel(), $node->getNodeType(), $node->getPath()]);
            $node->remove();
            $this->outputLine('  <info>Done, node removed</info>');
        }, $workspace, $type, false);
    }

    /**
     * Restore orphan documents
     *
     * @param string $workspace
     * @param string $type
     * @param string $target
     */
    public function restoreAllCommand($workspace = 'live', $type = 'Neos.Neos:Document', $target = null): void
    {
        $this->command(function (NodeInterface $node, $restore, $targetIdentifier) {
            $this->output->outputLine('%s <comment>%s</comment> (%s) in <b>%s</b>', [$node->getIdentifier(), $node->getLabel(), $node->getNodeType(), $node->getPath()]);
            if ($restore) {
                try {
                    $target = $this->orphanNodeService->target($node, $targetIdentifier);
                    $this->outputLine('  <info>Restore to %s (%s)</info>', [$node->getPath(), $node->getIdentifier()]);
                    $this->orphanNodeService->restore($node, $target);
                    $this->outputLine('  <info>Done, check your node at "%s"</info>', [$node->getPath()]);
                } catch (NodeNotFoundException $exception) {
                    $this->outputLine('  <error>Missing restoration target for the current node</error>');
                    return;
                }
            }
        }, $workspace, $type, true, $target);
    }

    /**
     * @param Closure $func
     * @param string $workspace
     * @param string $type
     * @param bool $restore
     * @param string $targetIdentifier
     */
    protected function command(Closure $func, $workspace, $type, $restore = false, $targetIdentifier = null): void
    {
        $nodes = $this->orphanNodeService->listByWorkspace($workspace, $type);
        $nodes->map(function (NodeInterface $node) use ($func, $restore, $targetIdentifier) {
            $func($node, $restore, $targetIdentifier);
        });

        if ($nodes->count()) {
            $this->outputLine('<b>Processed nodes:</b> %d', [$nodes->count()]);
        } else {
            $this->outputLine('<b>No orphaned document nodes</b>');
        }
    }

    protected function convertNodesToNodeInfo(ArrayCollection $nodes): array
    {
        return $nodes->map(function (NodeInterface $node) {
            return [
                'site' => $node->getContext()->getCurrentSite()->getName(),
                'dimension' => str_replace(['{', '}', '[', ']', '"'], '', json_encode($node->getContext()->getDimensions(), JSON_THROW_ON_ERROR)),
                'identifier' => $node->getIdentifier(),
                'nodeType' => $node->getNodeType(),
                'label' => $node->getLabel(),
                'path' => $node->getPath(),
            ];
        })->toArray();
    }
}
