<?php
namespace Ttree\Rebirth\Command;

/*
 * This file is part of the Ttree.Rebirth package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 */

use Ttree\Rebirth\Service\OrphanNodeService;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Cli\CommandController;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;

class RebirthCommandController extends CommandController
{
    /**
     * @var OrphanNodeService
     * @Flow\Inject
     */
    protected $orphanNodeService;

    /**
     * List orphan documents
     * @param string $workspace
     * @param string $type
     */
    public function listCommand($workspace = 'live', $type = 'TYPO3.Neos:Document')
    {
        $nodes = $this->orphanNodeService->listByWorkspace($workspace, $type);

        $this->outputLine();
        $this->outputLine('<info>Found orphan node named in "%s" workspace with "%s" type ...</info>', [$workspace, $type]);
        $this->outputLine();

        $nodes->map(function (NodeInterface $node) {
            $this->output->outputLine('<comment>%s</comment> (%s) in <b>%s</b>', [$node->getLabel(), $node->getNodeType(), $node->getPath()]);
            $this->orphanNodeService->restore($node);
        });
        $this->outputLine('<b>Processed nodes:</b> %d', [$nodes->count()]);
    }

    /**
     * Restore orphan document
     */
    public function restoreCommand()
    {

    }
}
