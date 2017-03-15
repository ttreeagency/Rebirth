<?php
namespace Ttree\Rebirth\Service;

/*
 * This file is part of the Ttree.Rebirth package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 */

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Neos\Controller\CreateContentContextTrait;
use TYPO3\Neos\Controller\Exception\NodeNotFoundException;
use TYPO3\Neos\Domain\Service\ContentContext;
use TYPO3\TYPO3CR\Domain\Factory\NodeFactory;
use TYPO3\TYPO3CR\Domain\Model\NodeData;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;
use TYPO3\TYPO3CR\Domain\Model\Workspace;
use TYPO3\TYPO3CR\Domain\Repository\WorkspaceRepository;

/**
 * @Flow\Scope("session")
 */
class OrphanNodeService
{
    use CreateContentContextTrait;

    /**
     * @var ObjectManager
     * @Flow\Inject
     */
    protected $entityManager;

    /**
     * @var WorkspaceRepository
     * @Flow\Inject
     */
    protected $workspaceRepository;

    /**
     * @var NodeFactory
     * @Flow\Inject
     */
    protected $nodeFactory;

    /**
     * @param string $workspaceName
     * @param string $type
     * @return ArrayCollection
     */
    public function listByWorkspace($workspaceName, $type = 'TYPO3.Neos:Document')
    {
        $nodes = $this->nodeDataByWorkspace($workspaceName);

        $nodes = $nodes->filter(function (NodeData $nodeData) use ($type) {
            return $nodeData->getNodeType()->isOfType($type);
        });

        return $nodes->map(function (NodeData $nodeData) {
            $context = $this->createContextMatchingNodeData($nodeData);
            return $this->nodeFactory->createFromNodeData($nodeData, $context);
        });
    }

    /**
     * @param NodeInterface $node
     * @param NodeInterface $target
     */
    public function restore(NodeInterface $node, NodeInterface $target)
    {
        $node->moveInto($target);
    }

    /**
     * @param NodeInterface $node
     * @param null $targetIdentifier
     * @return NodeInterface
     * @throws NodeNotFoundException
     */
    public function target(NodeInterface $node, $targetIdentifier = null)
    {
        if ($targetIdentifier === null) {
            return $this->resolveTargetInCurrentSite($node);
        }
        $context = $node->getContext();
        $targetNode = $context->getNodeByIdentifier($targetIdentifier);
        $this->isValidTargetNode($targetNode, $targetIdentifier);
        return $targetNode;
    }

    /**
     * @param NodeInterface $targetNode
     * @param string $expectedIdentifier
     * @return NodeInterface
     * @throws NodeNotFoundException
     */
    protected function isValidTargetNode(NodeInterface $targetNode, $expectedIdentifier)
    {
        if ($targetNode === null) {
            throw new NodeNotFoundException(vsprintf('The given target node is not found (%s)', [$expectedIdentifier]), 1489566677);
        }
        if (!$targetNode->getNodeType()->isOfType('TYPO3.Neos:Document')) {
            throw new NodeNotFoundException(vsprintf('Target node must a of type TYPO3.Neos:Document (current type: %s)', [$targetNode->getNodeType()]), 1489566677);
        }
        return $targetNode;
    }

    /**
     * @param string $workspaceName
     * @return ArrayCollection
     */
    protected function nodeDataByWorkspace($workspaceName)
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = $this->entityManager->createQueryBuilder();

        $workspaceList = [];
        /** @var Workspace $workspace */
        $workspace = $this->workspaceRepository->findByIdentifier($workspaceName);
        while ($workspace !== null) {
            $workspaceList[] = $workspace->getName();
            $workspace = $workspace->getBaseWorkspace();
        }

        return new ArrayCollection($queryBuilder
            ->select('n')
            ->from(NodeData::class, 'n')
            ->leftJoin(
                NodeData::class,
                'n2',
                Join::WITH,
                'n.parentPathHash = n2.pathHash AND n2.workspace IN (:workspaceList)'
            )
            ->where('n2.path IS NULL')
            ->andWhere($queryBuilder->expr()->not('n.path = :slash'))
            ->andWhere('n.workspace = :workspace')
            ->setParameters(['workspaceList' => $workspaceList, 'slash' => '/', 'workspace' => $workspaceName])
            ->getQuery()->getResult());
    }

    /**
     * @param NodeInterface $node
     * @return NodeInterface
     * @throws NodeNotFoundException
     */
    protected function resolveTargetInCurrentSite(NodeInterface $node)
    {
        $siteNode = $this->siteNode($node);
        $childNodes = $siteNode->getChildNodes('Ttree.Rebirth:Trash', 1);
        if ($childNodes === []) {
            throw new NodeNotFoundException(vsprintf('Missing Trash node under %s', [$node->getLabel(), $node->getIdentifier()]), 1489424180);
        }
        return $childNodes[0];
    }

    /**
     * @param NodeInterface $node
     * @return NodeInterface
     */
    protected function siteNode(NodeInterface $node)
    {
        /** @var ContentContext $context */
        $context = $node->getContext();
        return $context->getCurrentSiteNode();
    }
}
