<?php

declare(strict_types=1);

namespace B13\JustInCase\Tests\Unit\Middleware\Fixtures;

/*
 * This file is part of TYPO3 CMS-based extension "JustInCase" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Routing\RouteNotFoundException;
use TYPO3\CMS\Core\Routing\RouteResultInterface;
use TYPO3\CMS\Core\Routing\RouterInterface;
use TYPO3\CMS\Core\Routing\SiteRouteResult;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * A Site whose router only matches an explicit list of paths.
 *
 * Site::getRouter() builds a PageRouter via GeneralUtility::makeInstance and needs
 * a database, so it is replaced here to keep these tests pure unit tests.
 */
final class RoutableSite extends Site
{
    /**
     * @param string[] $matchingPaths paths the router resolves; everything else throws
     */
    public function __construct(
        string $identifier,
        int $rootPageId,
        array $configuration,
        private readonly array $matchingPaths = []
    ) {
        parent::__construct($identifier, $rootPageId, $configuration);
    }

    public function getRouter(?Context $context = null): RouterInterface
    {
        return new class($this, $this->matchingPaths) implements RouterInterface {
            /**
             * @param string[] $matchingPaths
             */
            public function __construct(
                private readonly Site $site,
                private readonly array $matchingPaths
            ) {
            }

            public function matchRequest(
                ServerRequestInterface $request,
                ?RouteResultInterface $previousResult = null
            ): RouteResultInterface {
                if (!in_array($request->getUri()->getPath(), $this->matchingPaths, true)) {
                    throw new RouteNotFoundException('No route found', 1579000000);
                }
                return new SiteRouteResult($request->getUri(), $this->site, null);
            }

            public function generateUri(
                $route,
                array $parameters = [],
                string $fragment = '',
                string $type = self::ABSOLUTE_URL
            ): UriInterface {
                throw new \BadMethodCallException('Not needed in these tests', 1579000001);
            }
        };
    }
}
