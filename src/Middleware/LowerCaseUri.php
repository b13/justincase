<?php

declare(strict_types=1);

namespace B13\JustInCase\Middleware;

/*
 * This file is part of TYPO3 CMS-based extension "JustInCase" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Routing\RouteNotFoundException;
use TYPO3\CMS\Core\Routing\SiteRouteResult;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Middleware to ensure lowercase URI
 *
 * Example:
 * Request to https://b13.com/Lets-connect/
 * will be redirected to https://b13.com/lets-connect/
 */
class LowerCaseUri implements MiddlewareInterface
{
    protected EventDispatcher $eventDispatcher;

    public function __construct(EventDispatcher $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $beforeMiddlewareIsAppliedEvent = new BeforeMiddlewareIsAppliedEvent($request);
        $this->eventDispatcher->dispatch($beforeMiddlewareIsAppliedEvent);
        if ($beforeMiddlewareIsAppliedEvent->shouldBeApplied() === false) {
            return $handler->handle($request);
        }
        $site = $request->getAttribute('site');

        if ($site instanceof Site) {
            try {
                $previousResult = $request->getAttribute('routing');

                $site->getRouter()->matchRequest($request, $previousResult);
                return $handler->handle($request);
            } catch (RouteNotFoundException $e) {
            }
        }

        $originalUri = $request->getUri();
        $path = $originalUri->getPath();
        $lowerCasePath = $this->lowerCasePath($path);

        // nothing has changed, nothing to do
        if ($lowerCasePath === $path) {
            return $handler->handle($request);
        }

        $updatedUri = $request->getUri()->withPath($lowerCasePath);

        $doRedirect = false;
        $redirectStatusCode = 307;
        if ($site instanceof Site) {
            $siteLanguage = $request->getAttribute('language')->toArray();
            $doRedirect = (bool)($site->getConfiguration()['settings']['redirectOnUpperCase'] ?? $siteLanguage['redirectOnUpperCase'] ?? false);
            $redirectStatusCode = (int)($site->getConfiguration()['settings']['redirectStatusCode'] ?? $siteLanguage['redirectStatusCode'] ?? 307);
        }
        // Redirects only work on GET and HEAD requests
        if ($doRedirect && in_array($request->getMethod(), ['GET', 'HEAD'])) {
            return new RedirectResponse($updatedUri, $redirectStatusCode);
        }
        // Update the path to just work as before, and continue with the process
        $request = $request->withUri($updatedUri);
        $routeResult = $request->getAttribute('routing');

        if ($routeResult instanceof SiteRouteResult) {
            $routeResult = new SiteRouteResult(
                $updatedUri,
                $routeResult->getSite(),
                $routeResult->getLanguage(),
                $this->lowerCasePath((string)$routeResult->getTail())
            );
            $request = $request->withAttribute('routing', $routeResult);
        }

        return $handler->handle($request);
    }

    /**
     * Lowers the path but leaves percent-encoded octets ("%E5") untouched.
     *
     * The hex digits of an octet are case-insensitive (RFC 3986, section 2.1), so
     * "%E5" and "%e5" denote the same URI. Lowering them would only trigger a
     * pointless redirect, or an endless one when a proxy in front of TYPO3
     * normalizes octets back to uppercase (RFC 3986, section 6.2.2.1).
     */
    protected function lowerCasePath(string $path): string
    {
        return preg_replace_callback(
            '/(%[0-9A-Fa-f]{2})|[^%]+/',
            static fn (array $matches): string => ($matches[1] ?? '') !== '' ? $matches[1] : mb_strtolower($matches[0]),
            $path
        ) ?? mb_strtolower($path);
    }
}
