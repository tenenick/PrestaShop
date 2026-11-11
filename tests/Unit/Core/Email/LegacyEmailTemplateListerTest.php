<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Unit\Core\Email;

use PHPUnit\Framework\TestCase;
use PrestaShop\PrestaShop\Core\Email\LegacyEmailTemplateLister;
use Symfony\Component\Filesystem\Filesystem;

class LegacyEmailTemplateListerTest extends TestCase
{
    private string $rootDir;
    private string $mailsDir;
    private string $modulesDir;
    private string $themesDir;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem();
        $this->rootDir = sys_get_temp_dir() . '/legacy_email_lister_' . uniqid('', true);
        $this->mailsDir = $this->rootDir . '/mails';
        $this->modulesDir = $this->rootDir . '/modules';
        $this->themesDir = $this->rootDir . '/themes';
        $this->filesystem->mkdir([
            $this->mailsDir . '/en',
            $this->modulesDir . '/probemodule/mails/en',
            $this->modulesDir . '/secondmodule/mails/en',
            $this->themesDir . '/active/mails/en',
            $this->themesDir . '/parent/mails/en',
        ]);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->rootDir);
        parent::tearDown();
    }

    public function testActiveThemeTemplatesAreListed(): void
    {
        $this->filesystem->dumpFile($this->themesDir . '/active/mails/en/recogida_pendiente.html', 'content');

        $templates = $this->createLister()->getLegacyTemplates('en');

        $this->assertArrayHasKey('recogida_pendiente', $templates);
        $this->assertTrue($templates['recogida_pendiente']['is_core']);
        $this->assertSame('', $templates['recogida_pendiente']['module']);
        $this->assertSame(
            $this->themesDir . '/active/mails/en/recogida_pendiente.html',
            $templates['recogida_pendiente']['html_path']
        );
        $this->assertNull($templates['recogida_pendiente']['txt_path']);
    }

    public function testActiveThemeHasPriorityOverParentCoreAndModules(): void
    {
        foreach ([
            $this->themesDir . '/active/mails/en/order_conf.html',
            $this->themesDir . '/parent/mails/en/order_conf.html',
            $this->mailsDir . '/en/order_conf.html',
            $this->modulesDir . '/probemodule/mails/en/order_conf.html',
        ] as $path) {
            $this->filesystem->dumpFile($path, 'content');
        }

        $templates = $this->createLister()->getLegacyTemplates('en');

        $this->assertSame(
            $this->themesDir . '/active/mails/en/order_conf.html',
            $templates['order_conf']['html_path']
        );
    }

    public function testActiveThemeDoesNotBorrowMissingFileFromParentTheme(): void
    {
        $this->filesystem->dumpFile($this->themesDir . '/active/mails/en/split.html', 'active html');
        $this->filesystem->dumpFile($this->themesDir . '/parent/mails/en/split.txt', 'parent txt');

        $templates = $this->createLister()->getLegacyTemplates('en');

        $this->assertSame(
            $this->themesDir . '/active/mails/en/split.html',
            $templates['split']['html_path']
        );
        $this->assertNull($templates['split']['txt_path']);
    }

    public function testParentThemeIsUsedWhenActiveThemeDoesNotContainTemplate(): void
    {
        $this->filesystem->dumpFile($this->themesDir . '/parent/mails/en/parent_only.html', 'content');

        $templates = $this->createLister()->getLegacyTemplates('en');

        $this->assertSame(
            $this->themesDir . '/parent/mails/en/parent_only.html',
            $templates['parent_only']['html_path']
        );
    }

    public function testParentThemeHasPriorityOverCoreAndModules(): void
    {
        foreach ([
            $this->themesDir . '/parent/mails/en/order_conf.html',
            $this->mailsDir . '/en/order_conf.html',
            $this->modulesDir . '/probemodule/mails/en/order_conf.html',
        ] as $path) {
            $this->filesystem->dumpFile($path, 'content');
        }

        $templates = $this->createLister()->getLegacyTemplates('en');

        $this->assertSame(
            $this->themesDir . '/parent/mails/en/order_conf.html',
            $templates['order_conf']['html_path']
        );
    }

    public function testCoreTemplateHasPriorityOverModuleTemplate(): void
    {
        $this->filesystem->dumpFile($this->mailsDir . '/en/order_changed.html', 'core');
        $this->filesystem->dumpFile($this->modulesDir . '/probemodule/mails/en/order_changed.html', 'module');

        $templates = $this->createLister()->getLegacyTemplates('en');

        $this->assertTrue($templates['order_changed']['is_core']);
        $this->assertSame('', $templates['order_changed']['module']);
        $this->assertSame($this->mailsDir . '/en/order_changed.html', $templates['order_changed']['html_path']);
    }

    public function testTemplateIsListedWhenEitherHtmlOrTxtFileExists(): void
    {
        $this->filesystem->dumpFile($this->mailsDir . '/en/html_only.html', 'content');
        $this->filesystem->dumpFile($this->mailsDir . '/en/txt_only.txt', 'content');

        $templates = $this->createLister()->getLegacyTemplates('en');

        $this->assertArrayHasKey('html_only', $templates);
        $this->assertArrayHasKey('txt_only', $templates);
        $this->assertNull($templates['html_only']['txt_path']);
        $this->assertNull($templates['txt_only']['html_path']);
    }

    public function testTemplatesAreListedOnceWithBothAvailablePaths(): void
    {
        $this->filesystem->dumpFile($this->mailsDir . '/en/paired.html', 'html');
        $this->filesystem->dumpFile($this->mailsDir . '/en/paired.txt', 'txt');

        $templates = $this->createLister()->getLegacyTemplates('en');

        $this->assertCount(1, $templates);
        $this->assertNotNull($templates['paired']['html_path']);
        $this->assertNotNull($templates['paired']['txt_path']);
    }

    public function testModuleTemplateIsListedWhenEitherFileExists(): void
    {
        $this->filesystem->dumpFile($this->modulesDir . '/probemodule/mails/en/module_alert.txt', 'content');

        $templates = $this->createLister()->getLegacyTemplates('en');

        $this->assertArrayHasKey('module_alert', $templates);
        $this->assertSame('probemodule', $templates['module_alert']['module']);
        $this->assertFalse($templates['module_alert']['is_core']);
        $this->assertNull($templates['module_alert']['html_path']);
    }

    public function testFirstModuleWinsWhenTwoModulesShipTheSameTemplate(): void
    {
        $this->filesystem->dumpFile($this->modulesDir . '/probemodule/mails/en/shared.html', 'first');
        $this->filesystem->dumpFile($this->modulesDir . '/secondmodule/mails/en/shared.html', 'second');

        $templates = $this->createLister()->getLegacyTemplates('en');

        $this->assertContains($templates['shared']['module'], ['probemodule', 'secondmodule']);
        $this->assertSame(
            $this->modulesDir . '/' . $templates['shared']['module'] . '/mails/en/shared.html',
            $templates['shared']['html_path']
        );
    }

    public function testMissingLanguageDirectoryYieldsNoTemplates(): void
    {
        $this->assertSame([], $this->createLister()->getLegacyTemplates('zz'));
    }

    public function testNoThemeDirectoriesCanBeProvided(): void
    {
        $this->filesystem->dumpFile($this->mailsDir . '/en/core_only.html', 'content');

        $templates = (new LegacyEmailTemplateLister($this->mailsDir, $this->modulesDir))->getLegacyTemplates('en');

        $this->assertArrayHasKey('core_only', $templates);
    }

    private function createLister(): LegacyEmailTemplateLister
    {
        return new LegacyEmailTemplateLister(
            $this->mailsDir,
            $this->modulesDir,
            $this->themesDir,
            'active',
            'parent'
        );
    }
}
