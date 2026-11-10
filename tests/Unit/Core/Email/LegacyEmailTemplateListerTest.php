<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Unit\Core\Email;

use PHPUnit\Framework\TestCase;
use PrestaShop\PrestaShop\Core\Email\LegacyEmailTemplateLister;

class LegacyEmailTemplateListerTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir() . '/prestashop-email-templates-' . uniqid('', true);
        mkdir($this->temporaryDirectory, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->temporaryDirectory);
    }

    public function testItListsTemplatesFromTheActiveTheme(): void
    {
        $mailsDir = $this->temporaryDirectory . '/mails';
        $modulesDir = $this->temporaryDirectory . '/modules';
        $themeDir = $this->temporaryDirectory . '/themes/custom';

        $this->createTemplate($mailsDir . '/es', 'shared_template');
        $this->createTemplate($themeDir . '/mails/es', 'shared_template');
        $this->createTemplate($themeDir . '/mails/es', 'theme_template');
        $this->createTemplate($themeDir . '/mails/es', 'html_only', false);

        $lister = new LegacyEmailTemplateLister($mailsDir, $modulesDir, $themeDir);
        $templates = $lister->getLegacyTemplates('es');

        self::assertArrayHasKey('theme_template', $templates);
        self::assertSame(
            $themeDir . '/mails/es/shared_template.html',
            $templates['shared_template']['html_path']
        );
        self::assertFalse($templates['theme_template']['is_core']);
        self::assertSame(
            $themeDir . '/mails/es/theme_template.txt',
            $templates['theme_template']['txt_path']
        );
        self::assertArrayNotHasKey('html_only', $templates);
    }

    public function testItKeepsListingCoreAndModuleTemplates(): void
    {
        $mailsDir = $this->temporaryDirectory . '/mails';
        $modulesDir = $this->temporaryDirectory . '/modules';
        $themeDir = $this->temporaryDirectory . '/themes/custom';

        $this->createTemplate($mailsDir . '/es', 'core_template');
        $this->createTemplate($modulesDir . '/samplemodule/mails/es', 'module_template');

        $lister = new LegacyEmailTemplateLister($mailsDir, $modulesDir, $themeDir);
        $templates = $lister->getLegacyTemplates('es');

        self::assertTrue($templates['core_template']['is_core']);
        self::assertSame('samplemodule', $templates['module_template']['module']);
    }

    private function createTemplate(string $directory, string $name, bool $withText = true): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($directory . '/' . $name . '.html', '<p>Template</p>');

        if ($withText) {
            file_put_contents($directory . '/' . $name . '.txt', 'Template');
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($directory);
    }
}
