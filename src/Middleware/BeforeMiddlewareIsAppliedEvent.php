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

use Psr\Http\Message\ServerRequestInterface;

final class BeforeMiddlewareIsAppliedEvent
{
    protected bool $shouldBeApplied = true;
    protected ServerRequestInterface $serverRequest;

    public function __construct(ServerRequestInterface $serverRequest)
    {
        $this->serverRequest = $serverRequest;
    }

    public function doNotApply(): void
    {
        $this->shouldBeApplied = false;
    }

    public function getServerRequest(): ServerRequestInterface
    {
        return $this->serverRequest;
    }

    public function shouldBeApplied(): bool
    {
        return $this->shouldBeApplied;
    }
}
