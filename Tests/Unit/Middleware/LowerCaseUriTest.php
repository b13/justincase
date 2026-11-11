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
use B13\JustInCase\Middleware\LowerCaseUri;
use B13\JustInCase\Tests\Unit\Middleware\Fixtures\RoutableSite;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Routing\SiteRouteResult;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

final class LowerCaseUriTest extends TestCase
{
    /**
     * Captures the request the middleware passes down the stack.
     */
    private ?ServerRequestInterface $handledRequest = null;

    private function handler(): RequestHandlerInterface
    {
        return new class($this->handledRequest) implements RequestHandlerInterface {
            public function __construct(private ?ServerRequestInterface &$captured)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->captured = $request;
                return new Response();
            }
        };
    }

    /**
     * The middleware type-hints the concrete EventDispatcher, so a stub subclass is
     * used rather than a mock to avoid constructing a ListenerProvider container.
     */
    private function eventDispatcher(bool $apply = true): EventDispatcher
    {
        return new class($apply) extends EventDispatcher {
            public function __construct(private readonly bool $apply)
            {
                // Intentionally not calling parent::__construct(): no listener
                // provider is needed, dispatch() is fully overridden below.
            }

            public function dispatch(object $event): object
            {
                if (!$this->apply && $event instanceof BeforeMiddlewareIsAppliedEvent) {
                    $event->doNotApply();
                }
                return $event;
            }
        };
    }

    private function subject(bool $apply = true): LowerCaseUri
    {
        return new LowerCaseUri($this->eventDispatcher($apply));
    }

    private function siteLanguage(array $configuration = []): SiteLanguage
    {
        return new SiteLanguage(0, 'en-US', new \TYPO3\CMS\Core\Http\Uri('/'), $configuration);
    }

    #[Test]
    public function upperCasePathIsLoweredForFurtherProcessing(): void
    {
        $request = new ServerRequest('https://example.com/Lets-Connect');

        $this->subject()->process($request, $this->handler());

        self::assertSame('/lets-connect', $this->handledRequest->getUri()->getPath());
    }

    #[Test]
    public function alreadyLowerCasePathIsPassedThroughUntouched(): void
    {
        $request = new ServerRequest('https://example.com/lets-connect');

        $this->subject()->process($request, $this->handler());

        self::assertSame($request, $this->handledRequest);
    }

    #[Test]
    public function queryStringCaseIsPreserved(): void
    {
        $request = new ServerRequest('https://example.com/Lets-Connect?tx_foo[Bar]=BaZ');

        $this->subject()->process($request, $this->handler());

        // Only the path is lowered; argument names and values survive verbatim
        // (brackets are percent-encoded by Uri itself).
        self::assertSame('/lets-connect', $this->handledRequest->getUri()->getPath());
        self::assertSame('tx_foo%5BBar%5D=BaZ', $this->handledRequest->getUri()->getQuery());
    }

    #[Test]
    public function hostIsNotAffectedByPathLowering(): void
    {
        $request = new ServerRequest('https://example.com/Lets-Connect');

        $this->subject()->process($request, $this->handler());

        self::assertSame('/lets-connect', $this->handledRequest->getUri()->getPath());
        self::assertSame('example.com', $this->handledRequest->getUri()->getHost());
    }

    /**
     * Documents a known limitation rather than desired behaviour.
     *
     * Lowering works on the still percent-encoded path. Encoded octets are kept
     * as they are, so the character they represent is not lowered:
     * "/%C3%9CBER-UNS" becomes "/%C3%9Cber-uns", which decodes back to "/Über-uns"
     * with a still-uppercase umlaut. Pure ASCII paths are unaffected.
     */
    #[Test]
    public function encodedNonAsciiCharactersAreNotActuallyLowered(): void
    {
        $request = new ServerRequest('https://example.com/%C3%9CBER-UNS');

        $this->subject()->process($request, $this->handler());

        $loweredPath = $this->handledRequest->getUri()->getPath();
        self::assertSame('/%C3%9Cber-uns', $loweredPath);
        // The ASCII part is lowered correctly ...
        self::assertStringContainsString('ber-uns', $loweredPath);
        // ... but the umlaut is still uppercase once decoded.
        self::assertSame("/\u{00DC}ber-uns", rawurldecode($loweredPath));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function pathsDifferingOnlyInOctetCaseDataProvider(): array
    {
        return [
            'uppercase octets' => ['/cn/hzc/%E5%AD%A6%E7%94%9F%E4%BA%A4%E6%B5%81/xs'],
            'lowercase octets' => ['/cn/hzc/%e5%ad%a6%e7%94%9f%e4%ba%a4%e6%b5%81/xs'],
            'mixed case octets' => ['/en/nonexistent-%C3%a4'],
        ];
    }

    /**
     * "%E5" and "%e5" are the same URI (RFC 3986, section 2.1): no redirect.
     */
    #[Test]
    #[DataProvider('pathsDifferingOnlyInOctetCaseDataProvider')]
    public function percentEncodedOctetsAreNotLowered(string $path): void
    {
        $site = new RoutableSite('main', 1, [
            'base' => 'https://example.com/',
            'settings' => ['redirectOnUpperCase' => true],
        ]);
        $request = (new ServerRequest('https://example.com' . $path))
            ->withAttribute('site', $site)
            ->withAttribute('language', $this->siteLanguage());

        $response = $this->subject()->process($request, $this->handler());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($request, $this->handledRequest);
    }

    #[Test]
    public function redirectKeepsPercentEncodedOctets(): void
    {
        $site = new RoutableSite('main', 1, [
            'base' => 'https://example.com/',
            'settings' => ['redirectOnUpperCase' => true],
        ]);
        $request = (new ServerRequest('https://example.com/Ueber-%C3%A4'))
            ->withAttribute('site', $site)
            ->withAttribute('language', $this->siteLanguage());

        $response = $this->subject()->process($request, $this->handler());

        self::assertSame(307, $response->getStatusCode());
        self::assertSame('https://example.com/ueber-%C3%A4', $response->getHeaderLine('location'));
    }

    /**
     * A proxy in front of TYPO3 may normalize octets to uppercase (RFC 3986,
     * section 6.2.2.1). Following the redirect must then not redirect again.
     */
    #[Test]
    public function followingRedirectThroughNormalizingProxyDoesNotLoop(): void
    {
        $site = new RoutableSite('main', 1, [
            'base' => 'https://example.com/',
            'settings' => ['redirectOnUpperCase' => true],
        ]);
        $normalizingProxy = static fn (string $uri): string => (string)preg_replace_callback(
            '/%[0-9a-f]{2}/i',
            static fn (array $matches): string => strtoupper($matches[0]),
            $uri
        );
        $request = (new ServerRequest($normalizingProxy('https://example.com/Foo-%c3%a4')))
            ->withAttribute('site', $site)
            ->withAttribute('language', $this->siteLanguage());

        $response = $this->subject()->process($request, $this->handler());
        self::assertSame(307, $response->getStatusCode());

        $followUpRequest = (new ServerRequest($normalizingProxy($response->getHeaderLine('location'))))
            ->withAttribute('site', $site)
            ->withAttribute('language', $this->siteLanguage());
        $followUpResponse = $this->subject()->process($followUpRequest, $this->handler());

        self::assertSame(200, $followUpResponse->getStatusCode());
        self::assertSame($followUpRequest, $this->handledRequest);
    }

    #[Test]
    public function routingTailKeepsPercentEncodedOctets(): void
    {
        $site = new RoutableSite('main', 1, ['base' => 'https://example.com/']);
        $language = $this->siteLanguage();
        $uri = new \TYPO3\CMS\Core\Http\Uri('https://example.com/Foo/%C3%A4Bar');
        $routeResult = new SiteRouteResult($uri, $site, $language, 'Foo/%C3%A4Bar');

        $request = (new ServerRequest($uri))
            ->withAttribute('site', $site)
            ->withAttribute('language', $language)
            ->withAttribute('routing', $routeResult);

        $this->subject()->process($request, $this->handler());

        $result = $this->handledRequest->getAttribute('routing');
        self::assertInstanceOf(SiteRouteResult::class, $result);
        self::assertSame('/foo/%C3%A4bar', $result->getUri()->getPath());
        self::assertSame('foo/%C3%A4bar', $result->getTail());
    }

    #[Test]
    public function middlewareIsSkippedWhenEventListenerPreventsIt(): void
    {
        $request = new ServerRequest('https://example.com/Lets-Connect');

        $this->subject(apply: false)->process($request, $this->handler());

        self::assertSame($request, $this->handledRequest);
        self::assertSame('/Lets-Connect', $this->handledRequest->getUri()->getPath());
    }

    #[Test]
    public function requestIsPassedThroughWhenOriginalUriAlreadyResolves(): void
    {
        $site = new RoutableSite('main', 1, ['base' => 'https://example.com/'], ['/Order/paymentForm']);
        $request = (new ServerRequest('https://example.com/Order/paymentForm'))
            ->withAttribute('site', $site);

        $this->subject()->process($request, $this->handler());

        // Camel-case route enhancers must keep working untouched.
        self::assertSame('/Order/paymentForm', $this->handledRequest->getUri()->getPath());
    }

    #[Test]
    public function routingAttributeIsRebuiltWithLoweredUriAndTail(): void
    {
        $site = new RoutableSite('main', 1, ['base' => 'https://example.com/']);
        $language = $this->siteLanguage();
        $uri = new \TYPO3\CMS\Core\Http\Uri('https://example.com/Lets-Connect/SubPage');
        $routeResult = new SiteRouteResult($uri, $site, $language, 'SubPage');

        $request = (new ServerRequest($uri))
            ->withAttribute('site', $site)
            ->withAttribute('language', $language)
            ->withAttribute('routing', $routeResult);

        $this->subject()->process($request, $this->handler());

        $result = $this->handledRequest->getAttribute('routing');
        self::assertInstanceOf(SiteRouteResult::class, $result);
        self::assertSame('/lets-connect/subpage', $result->getUri()->getPath());
        self::assertSame('subpage', $result->getTail());
        self::assertSame($site, $result->getSite());
        self::assertSame($language, $result->getLanguage());
    }

    #[Test]
    public function redirectIsIssuedWhenEnabledOnSiteLevel(): void
    {
        $site = new RoutableSite('main', 1, [
            'base' => 'https://example.com/',
            'settings' => ['redirectOnUpperCase' => true],
        ]);
        $request = (new ServerRequest('https://example.com/Lets-Connect'))
            ->withAttribute('site', $site)
            ->withAttribute('language', $this->siteLanguage());

        $response = $this->subject()->process($request, $this->handler());

        self::assertSame(307, $response->getStatusCode());
        self::assertSame('https://example.com/lets-connect', $response->getHeaderLine('location'));
        self::assertNull($this->handledRequest, 'Handler must not be called on redirect');
    }

    #[Test]
    public function redirectStatusCodeIsConfigurableOnSiteLevel(): void
    {
        $site = new RoutableSite('main', 1, [
            'base' => 'https://example.com/',
            'settings' => ['redirectOnUpperCase' => true, 'redirectStatusCode' => 303],
        ]);
        $request = (new ServerRequest('https://example.com/Lets-Connect'))
            ->withAttribute('site', $site)
            ->withAttribute('language', $this->siteLanguage());

        $response = $this->subject()->process($request, $this->handler());

        self::assertSame(303, $response->getStatusCode());
    }

    #[Test]
    public function redirectIsIssuedWhenEnabledOnLanguageLevel(): void
    {
        $site = new RoutableSite('main', 1, ['base' => 'https://example.com/']);
        $request = (new ServerRequest('https://example.com/Lets-Connect'))
            ->withAttribute('site', $site)
            ->withAttribute('language', $this->siteLanguage([
                'redirectOnUpperCase' => true,
                'redirectStatusCode' => 308,
            ]));

        $response = $this->subject()->process($request, $this->handler());

        self::assertSame(308, $response->getStatusCode());
    }

    #[Test]
    public function siteSettingsTakePrecedenceOverLanguageSettings(): void
    {
        $site = new RoutableSite('main', 1, [
            'base' => 'https://example.com/',
            'settings' => ['redirectOnUpperCase' => true, 'redirectStatusCode' => 301],
        ]);
        $request = (new ServerRequest('https://example.com/Lets-Connect'))
            ->withAttribute('site', $site)
            ->withAttribute('language', $this->siteLanguage(['redirectStatusCode' => 302]));

        $response = $this->subject()->process($request, $this->handler());

        self::assertSame(301, $response->getStatusCode());
    }

    #[Test]
    public function noRedirectHappensWhenDisabled(): void
    {
        $site = new RoutableSite('main', 1, [
            'base' => 'https://example.com/',
            'settings' => ['redirectOnUpperCase' => false],
        ]);
        $request = (new ServerRequest('https://example.com/Lets-Connect'))
            ->withAttribute('site', $site)
            ->withAttribute('language', $this->siteLanguage());

        $this->subject()->process($request, $this->handler());

        self::assertNotNull($this->handledRequest);
        self::assertSame('/lets-connect', $this->handledRequest->getUri()->getPath());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonRedirectableMethodsDataProvider(): array
    {
        return [
            'POST' => ['POST'],
            'PUT' => ['PUT'],
            'DELETE' => ['DELETE'],
            'PATCH' => ['PATCH'],
        ];
    }

    #[Test]
    #[DataProvider('nonRedirectableMethodsDataProvider')]
    public function redirectIsSkippedForNonGetOrHeadRequests(string $method): void
    {
        $site = new RoutableSite('main', 1, [
            'base' => 'https://example.com/',
            'settings' => ['redirectOnUpperCase' => true],
        ]);
        $request = (new ServerRequest('https://example.com/Lets-Connect', $method))
            ->withAttribute('site', $site)
            ->withAttribute('language', $this->siteLanguage());

        $this->subject()->process($request, $this->handler());

        // The request must be rewritten and forwarded rather than redirected,
        // because a redirect would drop the request body.
        self::assertNotNull($this->handledRequest);
        self::assertSame('/lets-connect', $this->handledRequest->getUri()->getPath());
    }

    #[Test]
    public function headRequestIsRedirected(): void
    {
        $site = new RoutableSite('main', 1, [
            'base' => 'https://example.com/',
            'settings' => ['redirectOnUpperCase' => true],
        ]);
        $request = (new ServerRequest('https://example.com/Lets-Connect', 'HEAD'))
            ->withAttribute('site', $site)
            ->withAttribute('language', $this->siteLanguage());

        $response = $this->subject()->process($request, $this->handler());

        self::assertSame(307, $response->getStatusCode());
    }
}
