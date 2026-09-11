<?php

declare(strict_types=1);

namespace B13\JustInCase\Tests\Unit\Middleware;

/*
 * This file is part of TYPO3 CMS-based extension "JustInCase" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use B13\JustInCase\Middleware\BeforeMiddlewareIsAppliedEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Http\ServerRequest;

final class BeforeMiddlewareIsAppliedEventTest extends TestCase
{
    #[Test]
    public function middlewareIsAppliedByDefault(): void
    {
        $event = new BeforeMiddlewareIsAppliedEvent(new ServerRequest('https://example.com/Foo'));

        self::assertTrue($event->shouldBeApplied());
    }

    #[Test]
    public function doNotApplyPreventsMiddlewareFromBeingApplied(): void
    {
        $event = new BeforeMiddlewareIsAppliedEvent(new ServerRequest('https://example.com/Foo'));

        $event->doNotApply();

        self::assertFalse($event->shouldBeApplied());
    }

    #[Test]
    public function serverRequestIsExposedToListeners(): void
    {
        $request = new ServerRequest('https://example.com/Foo');
        $event = new BeforeMiddlewareIsAppliedEvent($request);

        self::assertSame($request, $event->getServerRequest());
    }
}
