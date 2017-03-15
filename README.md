# Rebirth your orphaned nodes

This package can help you to move orphaned node in the Neos Content Repository.

*Warning* Working with broken / orphaned nodes can be hard, please backup your data before using this package.

_Currently this package support only document nodes restauration_

How to use ?
------------

- Optional: For each Site where you want to restore orphaned nodes, you must create a "Trash" node. This will be used as the target for document node restoration.
- Go to the CLI and run the restore command

CLI commands
------------

## List the current orphaned document nodes

    flow rebirth:list

## List the current orphaned nodes for a specific type

    flow rebirth:list --type TYPO3.Neos:Document
    
## Restore everything under the "Trash" node of the current site

    flow rebirth:restore

## Restore everything under the "Trash" node of the current site, only for the given type

    flow rebirth:restore --type TYPO3.Neos:Document
    
## Restore everything under the given target node

    flow rebirth:restore --target f34f834b-c36b-43eb-a580-f0e2f168b241

## Prune all orphaned document nodes

    flow rebirth:prune

## Prune all orphaned document nodes for a specific type

    flow rebirth:list --type TYPO3.Neos:Document
    
Acknowledgments
---------------

Development sponsored by [ttree ltd - neos solution provider](http://ttree.ch).

We try our best to craft this package with a lots of love, we are open to
sponsoring, support request, ... just contact us.

License
-------

Licensed under MIT, see [LICENSE](LICENSE)
