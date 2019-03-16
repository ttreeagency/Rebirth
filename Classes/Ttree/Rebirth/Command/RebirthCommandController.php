<?php
namespace Ttree\Rebirth\Command;

/*
 * This file is part of the Ttree.Rebirth package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 */

use Closure;
use Ttree\Rebirth\Service\OrphanNodeService;
use TYPO3\Eel\FlowQuery\FlowQuery;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Cli\CommandController;
use TYPO3\Neos\Controller\CreateContentContextTrait;
use TYPO3\Neos\Controller\Exception\NodeNotFoundException;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;

class RebirthCommandController extends CommandController
{
    use CreateContentContextTrait;

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
    public function listCommand($workspace = 'live', $type = 'TYPO3.Neos:Document')
    {
        $context = $this->createContentContext('live');

        $log = function (NodeInterface $node, $leftPad = '') use ($context) {
            $this->output->outputLine('');
            $this->output->outputLine($leftPad . "<b>%s</b> (%s)", [$node->getLabel(), $node->getIdentifier()]);
            $this->output->outputLine($leftPad . "%s in <info>%s</info>", [$node->getNodeType(), $node->getPath()]);
        };

        $showChildren = function (NodeInterface $parentNode, $leftPad = '') use (&$showChildren, $log) {
            $query = (new FlowQuery([$parentNode]))->children('[instanceof TYPO3.Neos:Document]')->get();
            foreach ($query as $children) {
                $log($children, $leftPad);
            }
        };

        $this->command(function (NodeInterface $node) use ($log, $showChildren) {
            $log($node);
            $showChildren($node, '    ');
        }, $workspace, $type, false);
    }

    /**
     * List orphan documents
     *
     * @param string $workspace
     * @param string $type
     */
    public function pruneCommand($workspace = 'live', $type = 'TYPO3.Neos:Document')
    {
        $this->command(function (NodeInterface $node) {
            $this->output->outputLine('%s <comment>%s</comment> (%s) in <b>%s</b>', [$node->getIdentifier(), $node->getLabel(), $node->getNodeType(), $node->getPath()]);
            $node->remove();
            $this->outputLine('  <info>Done, node removed</info>');
        }, $workspace, $type, false);
    }

    /**
     * Restore orphan document
     *
     * @param string $workspace
     * @param string $type
     * @param string $target
     */
    public function restoreCommand($workspace = 'live', $type = 'TYPO3.Neos:Document', $target = null)
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
     * @param string $parentPath Previous parent path
     * @param string $target Node identifier of the target node
     * @param string $type Node type
     * @param string $workspace Current workspace
     * @throws \TYPO3\Flow\Mvc\Exception\StopActionException
     */
    public function restoreByParentPathCommand($parentPath, $target, $workspace = 'live', $type = 'TYPO3.Neos:Document')
    {
        $context = $this->createContentContext('live');
        $targetNode = $context->getNodeByIdentifier($target);
        if (!$targetNode instanceof NodeInterface) {
            $this->outputLine('Missing target node');
            $this->quit(1);
        }

        $this->outputLine();
        $this->outputLine('<b>Restore orphaned nodes to "%s" (%s)</b>', [$targetNode->getLabel(), $targetNode->getNodeType()]);
        $this->outputLine('New parent path: <info>%s</info>', [$targetNode->getPath()]);
        $this->outputLine();

        $this->command(function (NodeInterface $node) use ($parentPath, $targetNode) {
            if ($node->getParentPath() !== $parentPath) {
                return;
            }
            $this->outputLine();
            $this->outputLine('Restore <info>%s</info>...', [$node->getLabel()]);
            if ($this->output->askConfirmation('Do you want to restore the current node? [<b>y</b>|n]')) {
                $this->orphanNodeService->restore($node, $targetNode);
            }
        }, $workspace, $type, false);
    }

    /**
     * @param Closure $func
     * @param string $workspace
     * @param string $type
     * @param bool $restore
     * @param string $targetIdentifier
     */
    protected function command(Closure $func, $workspace, $type, $restore = false, $targetIdentifier = null)
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
}
