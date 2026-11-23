<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Regression: the connector installer page broke with "Class Piwik\Plugins\FeinwerkCampaignScore\View not found" (the
 * controller used View without importing it; PHP only notices when the line runs). Every short class name a file uses
 * with new, :: or instanceof must be imported, declared in the file, or live in the file's own namespace.
 */
class ClassImportsTest extends TestCase
{
    public function test_every_class_used_is_imported(): void
    {
        $files = ['Controller.php', 'Menu.php', 'API.php', 'FeinwerkCampaignScore.php'];
        foreach (['ConnectorInstaller', 'Promo', 'Ui', 'Widgets', 'Reports'] as $dir) {
            foreach (glob(dirname(__DIR__, 2) . '/' . $dir . '/*.php') ?: [] as $path) {
                $files[] = $dir . '/' . basename($path);
            }
        }
        self::assertGreaterThan(20, count($files));
        foreach ($files as $file) {
            $this->assertImports($file);
        }
    }

    private function assertImports(string $file): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/' . $file);
        $code = (string) preg_replace(['~/\*.*?\*/~s', '~//[^\n]*~', "~'(?:[^'\\\\]|\\\\.)*'~s", '~"(?:[^"\\\\]|\\\\.)*"~s'], '', $source);
        preg_match('/^namespace\s+([^;]+);/m', $source, $ns);
        $namespace = $ns[1] ?? '';

        $known = ['self' => true, 'static' => true, 'parent' => true];
        preg_match_all('/^use\s+([^;]+);/m', $source, $uses);
        foreach ($uses[1] as $use) {
            $alias = preg_match('/\s+as\s+(\w+)$/', $use, $m) ? $m[1] : substr(strrchr('\\' . $use, '\\'), 1);
            $known[$alias] = true;
        }
        preg_match_all('/\b(?:class|interface|trait|enum)\s+(\w+)/', $code, $declared);
        foreach ($declared[1] as $name) {
            $known[$name] = true;
        }

        preg_match_all('/(?<![\\\\\w$])new\s+([A-Z]\w*)|(?<![\\\\\w$>:])([A-Z]\w*)::|instanceof\s+([A-Z]\w*)/', $code, $used);
        $names = array_unique(array_filter(array_merge($used[1], $used[2], $used[3])));
        $missing = [];
        foreach ($names as $name) {
            if (isset($known[$name])) {
                continue;
            }
            $sameNamespace = dirname(__DIR__, 2) . str_replace(['Piwik\\Plugins\\FeinwerkCampaignScore', '\\'], ['', '/'], $namespace) . '/' . $name . '.php';
            if (!is_file($sameNamespace)) {
                $missing[] = $name;
            }
        }
        self::assertSame([], $missing, $file . ' uses classes it does not import');
    }
}
