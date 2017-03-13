# Rebirth your orphaned nodes

This package can help you to move orphaned node in the Neos Content Repository.

_Currently this package support only document nodes restauration_

How to use ?
------------

- For each Site where you want to restore orphaned nodes, you must create a "Trash" node. This will be used as the target for document node restoration.
- Go to the CLI and run the restore command

CLI commands
------------

You can ```list``` or ```restore``` orphaned document node with the following commands:

    flow rebirth:list
    flow rebirth:restore

Acknowledgments
---------------

Development sponsored by [ttree ltd - neos solution provider](http://ttree.ch).

We try our best to craft this package with a lots of love, we are open to
sponsoring, support request, ... just contact us.

License
-------

Licensed under MIT, see [LICENSE](LICENSE)
