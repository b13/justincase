# TYPO3 Extension "just in case" - No matter what case your URL is, we'll hit it.

When your marketing team has accidentally pushed a campaign with mixed case URLs, they should not run into 404s.

This TYPO3 extension solves your pain - just in case.

## What does it do?

By default, TYPO3 v9 is strict when you're actual page is called `https://b13.com/lets-connect/` but your marketing
dudes name it `https://b13.com/Lets-Connect/`. TYPO3 v9 saves URLs as lower-case by default.

A simple PSR-15 based middleware transforms your incoming URL into lower-case and you should be fine, as
both URLs would work then.

## Installation

Use it via `composer req b13/justincase` or install the Extension `justincase` from the TYPO3 Extension Repository.

_justincase_ requires TYPO3 v9.5.0 or later.


## ToDo

It would be REALLY cool, if an option could define whether you want this option handling 404/200 or as a redirect.

Currently, you can add

    settings:
        redirectOnUpperCase: true

to your site configuration to enable redirects.


## License

As TYPO3 Core, _justincase_ is licensed under GPL2 or later. See the LICENSE file for more details.

## Background, Credits & Further Maintenance

This extension was created as a show-case on what you can do with middlewares for TYPO3 v9 and customize
so many things. See https://forge.typo3.org/issues/87544 for the initial request.

TYPO3 community often requests functionality, which can be put in small and efficient extensions, and _justincase_ does
exactly that, without having to burden everything into TYPO3 Core.

_justincase_ was initially created by Daniel Goerz and Benni Mack for [b13, Stuttgart](https://b13.com), with the nice
support from Matthias Stegmann for providing the extension name.

Icons provided via https://www.iconspng.com/image/26353/primary-casesensitive
