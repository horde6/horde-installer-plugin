<?php

declare(strict_types=1);

namespace Horde\Composer;

class HordeLocalFileWriter
{
    /**
     * Undocumented function
     *
     * @param Filesystem $filesystem
     * @param string $baseDir
     * @param string[] $apps
     * @param string $mode
     */
    public function __construct(
       private DirectoryTree $tree,
       private Filesystem $filesystem,
       private array $apps,
       private string $mode = 'proxy'
    )
    {
    }

    public function run(): void
    {
        $tree = $this->tree;
        $configDir = $tree->getVarConfigDir();
        $vendorDir = $tree->getVendorDir();
        $webDir = $tree->getWebReadableRootDir();

        $hordeWebDir = $webDir . '/horde';

        $hordeBaseDir = $hordeWebDir;
        if ($this->mode === 'proxy') {
            foreach ($this->apps as $app) {
                [$vendor, $name] = explode('/', $app, 2);
                if ($name === 'horde') {
                    $hordeBaseDir = $tree->getVendorPackageDir($vendor, $name);
                    break;
                }
            }
        }

        $autoloadExtraFilePath = $configDir . '/autoload-extra.php';
        $autoloadExtra = file_exists($autoloadExtraFilePath) ? "\nrequire_once('$autoloadExtraFilePath');\n" : '';

        foreach ($this->apps as $app) {
            [$vendor, $name] = explode('/', $app, 2);

            $appConfigDir = $configDir . '/' . $name;
            $this->filesystem->ensureDirectoryExists($appConfigDir);

            $path = $appConfigDir . '/horde.local.php';

            $hordeLocalFileContent = "<?php\n"
                . self::define('HORDE_BASE', $hordeBaseDir)
                . self::define('HORDE_CONFIG_BASE', $configDir);

            // special case horde/horde needs to require the composer autoloader
            if ($name == 'horde') {
                $hordeLocalFileContent .= $this->_legacyWorkaround($this->filesystem->normalizePath($vendorDir));
                $hordeLocalFileContent .= 'require_once(\'' . $vendorDir . '/autoload.php\');' . "\n";
            }

            $templates = strtoupper($name) . '_TEMPLATES';
            $templatesPath = $tree->getVendorPackageDir($vendor, $name) . DIRECTORY_SEPARATOR . 'templates';
            $hordeLocalFileContent .= "\n" . self::define($templates, $templatesPath);

            $hordeLocalFileContent .= $autoloadExtra;

            $this->filesystem->filePutContentsIfModified($path, $hordeLocalFileContent);
        }
    }

    private static function define($const, $value) {
        return "if (!defined('$const')) define('$const', '$value');\n";
    }

    /**
     * Legacy support
     *
     * Work around case inconsistencies
     * hard requires etc until they are resolved in code
     *
     * @param string $path Path to vendor dir
     * @return string
     */
    protected function _legacyWorkaround(string $path): string
    {
        return sprintf(
            "\nini_set('include_path', '%s/horde/autoloader/lib%s%s/horde/form/lib/%s' . ini_get('include_path'));
//require_once('%s/horde/core/lib/Horde/Core/Nosql.php');
",
            $path,
            PATH_SEPARATOR,
            $path,
            PATH_SEPARATOR,
            $path
        );
    }
}
