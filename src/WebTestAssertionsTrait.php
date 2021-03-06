<?php

/*
 * This file is part of the Panther project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Panther;

use Facebook\WebDriver\WebDriverElement;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestAssertionsTrait as BaseWebTestAssertionsTrait;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\Panther\Client as PantherClient;

/**
 * Tweaks Symfony's WebTestAssertionsTrait to be compatible with Panther.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
trait WebTestAssertionsTrait
{
    use PantherTestCaseTrait;
    use BaseWebTestAssertionsTrait {
        assertPageTitleSame as private baseAssertPageTitleSame;
        assertPageTitleContains as private baseAssertPageTitleContains;
    }

    public static function assertPageTitleSame(string $expectedTitle, string $message = ''): void
    {
        $client = self::getClient();
        if ($client instanceof PantherClient) {
            self::assertSame($expectedTitle, $client->getTitle());

            return;
        }

        self::baseAssertPageTitleSame($expectedTitle, $message);
    }

    public static function assertPageTitleContains(string $expectedTitle, string $message = ''): void
    {
        $client = self::getClient();
        if ($client instanceof PantherClient) {
            if (method_exists(self::class, 'assertStringContainsString')) {
                self::assertStringContainsString($expectedTitle, $client->getTitle());

                return;
            }

            self::assertContains($expectedTitle, $client->getTitle());

            return;
        }

        self::baseAssertPageTitleContains($expectedTitle, $message);
    }

    public static function assertSelectorIsVisible(string $locator): void
    {
        $element = self::findElement($locator);
        self::assertTrue($element->isDisplayed(), 'Failed asserting that element is visible.');
    }

    public static function assertSelectorIsNotVisible(string $locator): void
    {
        $element = self::findElement($locator);
        self::assertFalse($element->isDisplayed(), 'Failed asserting that element is not visible.');
    }

    public static function assertSelectorIsEnabled(string $locator): void
    {
        $element = self::findElement($locator);
        self::assertTrue($element->isEnabled(), 'Failed asserting that element is enabled.');
    }

    public static function assertSelectorIsDisabled(string $locator): void
    {
        $element = self::findElement($locator);
        self::assertFalse($element->isEnabled(), 'Failed asserting that element is disabled.');
    }

    private static function findElement(string $locator): WebDriverElement
    {
        /** @var PantherClient $client */
        $client = self::getClient();
        $by = $client::createWebDriverByFromLocator($locator);

        return $client->findElement($by);
    }

    // Copied from WebTestCase to allow assertions to work with createClient

    /**
     * Creates a KernelBrowser.
     *
     * @param array $options An array of options to pass to the createKernel method
     * @param array $server  An array of server parameters
     *
     * @return AbstractBrowser A browser instance
     */
    protected static function createClient(array $options = [], array $server = [])
    {
        $kernel = static::bootKernel($options);

        try {
            /** @var KernelBrowser $client */
            $client = $kernel->getContainer()->get('test.client');
        } catch (ServiceNotFoundException $e) {
            if (class_exists(KernelBrowser::class)) {
                throw new \LogicException('You cannot create the client used in functional tests if the "framework.test" config is not set to true.');
            }
            throw new \LogicException('You cannot create the client used in functional tests if the BrowserKit component is not available. Try running "composer require symfony/browser-kit"');
        }

        $client->setServerParameters($server);

        return self::getClient($client);
    }
}
