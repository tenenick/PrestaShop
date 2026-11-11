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
 * Scans and lists legacy email templates from the active theme, parent theme, core and modules.
 */
class LegacyEmailTemplateLister
{
    private string $mailsDir;
    private string $modulesDir;
    private ?string $themesDir;
    private ?string $themeName;
    private ?string $parentThemeName;

    public function __construct(
        string $mailsDir = _PS_MAIL_DIR_,
        string $modulesDir = _PS_MODULE_DIR_,
        ?string $themesDir = null,
        ?string $themeName = null,
        ?string $parentThemeName = null
    ) {
        $this->mailsDir = $mailsDir;
        $this->modulesDir = $modulesDir;
        $this->themesDir = $themesDir;
        $this->themeName = $themeName;
        $this->parentThemeName = $parentThemeName;
    }

    /**
     * @return array ['template_name' => ['module' => '', 'is_core' => true, ...], ...]
     */
    public function getLegacyTemplates(string $isoCode): array
    {
        $templates = [];

        // Keep the same precedence as Mail::getTemplateBasePath(): active theme, parent theme,
        // core mails and finally modules. The first matching directory wins for each template.
        if ($this->themesDir !== null && $this->themeName !== null && $this->themeName !== '') {
            $activeThemePath = $this->getThemeMailsPath($this->themeName, $isoCode);
            $templates += $this->scanDirectory($activeThemePath, '', true);
        }

        if ($this->themesDir !== null && $this->parentThemeName !== null && $this->parentThemeName !== '') {
            $parentThemePath = $this->getThemeMailsPath($this->parentThemeName, $isoCode);
            $templates += $this->scanDirectory($parentThemePath, '', true);
        }

        $corePath = rtrim($this->mailsDir, '/') . '/' . $isoCode . '/';
        $templates += $this->scanDirectory($corePath, '', true);
        $templates += $this->scanModuleTemplates($isoCode);

        return $templates;
    }

    private function getThemeMailsPath(string $themeName, string $isoCode): string
    {
        return rtrim((string) $this->themesDir, '/') . '/' . $themeName . '/mails/' . $isoCode . '/';
    }

    private function scanDirectory(string $path, string $moduleName, bool $isCore = false): array
    {
        $templates = [];

        if (!is_dir($path)) {
            return $templates;
        }

        $finder = new Finder();
        $finder->files()->in($path)->depth(0)->name(['*.html', '*.txt']);

        /** @var SplFileInfo $file */
        foreach ($finder as $file) {
            $templateName = $file->getBasename('.' . $file->getExtension());

            if ($templateName === '' || $templateName[0] === '.') {
                continue;
            }

            if (!isset($templates[$templateName])) {
                $templates[$templateName] = [
                    'module' => $moduleName,
                    'is_core' => $isCore,
                    'html_path' => null,
                    'txt_path' => null,
                ];
            }

            $extension = $file->getExtension();
            $templates[$templateName][$extension . '_path'] = $file->getPathname();
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
                // The first module containing a template wins, just as in Mail::getTemplateBasePath().
                $templates += $this->scanDirectory($modulePath, $moduleName, false);
            }
        }

        return $templates;
    }
}
