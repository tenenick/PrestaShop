<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PrestaShop\\PrestaShop\\Core\\Email;

use Symfony\\Component\\Finder\\Finder;
use Symfony\\Component\\Finder\\SplFileInfo;

/**
 * Scans and lists legacy email templates from the core, active theme and modules.
 */
class LegacyEmailTemplateLister
{
    private string $mailsDir;
    private string $modulesDir;
    private ?string $themeDir;

    public function __construct(
        string $mailsDir = _PS_MAIL_DIR_,
        string $modulesDir = _PS_MODULE_DIR_,
        ?string $themeDir = null
    ) {
        $this->mailsDir = $mailsDir;
        $this->modulesDir = $modulesDir;
        $this->themeDir = $themeDir ?? (defined('_PS_THEME_DIR_') ? _PS_THEME_DIR_ : null);
    }

    /**
     * @return array ['template_name' => ['module' => '', 'is_core' => true, ...], ...]
     */
    public function getLegacyTemplates(string $isoCode): array
    {
        $templates = [];

        $corePath = $this->mailsDir . '/' . $isoCode . '/';
        if (is_dir($corePath)) {
            $templates = array_merge($templates, $this->scanDirectory($corePath, '', true));
        }

        $templates = array_merge($templates, $this->scanModuleTemplates($isoCode));

        // Theme templates have priority over core and module templates when sending legacy emails.
        if (null !== $this->themeDir) {
            $themePath = rtrim($this->themeDir, '/\\\\') . '/mails/' . $isoCode . '/';
            $templates = array_merge($templates, $this->scanDirectory($themePath, '', false));
        }

        return $templates;
    }

    private function scanDirectory(string $path, string $moduleName, bool $isCore = false): array
    {
        $templates = [];

        if (!is_dir($path)) {
            return $templates;
        }

        $finder = new Finder();
        $finder->files()->in($path)->depth(0)->name('*.html');

        /** @var SplFileInfo $file */
        foreach ($finder as $file) {
            $templateName = $file->getBasename('.html');

            if ($templateName[0] === '.') {
                continue;
            }

            $txtFile = $path . $templateName . '.txt';

            if (file_exists($txtFile)) {
                $templates[$templateName] = [
                    'module' => $moduleName,
                    'is_core' => $isCore,
                    'html_path' => $file->getPathname(),
                    'txt_path' => $txtFile,
                ];
            }
        }

        return $templates;
    }

    private function scanModuleTemplates(string $isoCode): array
    {
        $templates = [];

        if (!is_dir($this->modulesDir)) {
            return $templates;
        }

        $moduleFinder = new Finder();
        $moduleFinder->directories()->in($this->modulesDir)->depth(0);

        /** @var SplFileInfo $moduleDir */
        foreach ($moduleFinder as $moduleDir) {
            $moduleName = $moduleDir->getFilename();
            $modulePath = $moduleDir->getPathname() . '/mails/' . $isoCode . '/';

            if (is_dir($modulePath)) {
                $moduleTemplates = $this->scanDirectory($modulePath, $moduleName, false);
                $templates = array_merge($templates, $moduleTemplates);
            }
        }

        return $templates;
    }
}
