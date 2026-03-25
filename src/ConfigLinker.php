<?php

declare(strict_types=1);

namespace Horde\Composer;

use Composer\IO\IOInterface;
use Horde\Composer\IOAdapter\FlowIoInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ConfigLinker
{
    public function __construct(private DirectoryTree $tree, private array $appPackages, private string $mode = 'proxy', private FlowIoInterface|null $io = null)
    {
    }

    /**
     * Symlink contents of var/config
     *
     * We always check the whole tree even though this may happen
     * multiple times in installations with many apps
     *
     * @return void
     */
    public function run(): void
    {
        $tree = $this->tree;

        $configDir = $tree->getVarConfigDir();

        // Abort unless var/config exists and is readable
        if (!is_dir($configDir) || !is_readable($configDir)) {
            return;
        }

        $vendorDir = $tree->getVendorDir();

        // Iterate through subdirs
        foreach ($this->appPackages as $app) {
            [$vendor, $name] = explode('/', $app);

            // Next if no corresponding web/$app/config dir exists
            $appConfigDir = $configDir . '/' . $name;
            if (!is_dir($appConfigDir)) {
                continue;
            }

            $targetDir = $vendorDir . '/' . $app . '/config';
            if (!is_dir($targetDir)) {
                continue;
            }

            // Iterate recursively
            $contentInfo = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appConfigDir));
            foreach ($contentInfo as $contentItem) {
                // Don't symlink dirs
                if ($contentItem instanceof RecursiveDirectoryIterator && $contentItem->isDir()) {
                    continue;
                }

                // Generate missing dirs below targetdir
                $relativeName = $contentInfo->getSubPathname();
                $subPath = $targetDir . '/' . $contentInfo->getSubPath();
                if (!is_dir($subPath)) {
                    mkdir($subPath, 0o770, true);
                }

                // Hooks don't need to be symlinked. Duplicating them can even confuse the autoloader
                if (is_int(strpos($subPath, 'hooks.php'))) {
                    continue;
                }

                $linkName = $targetDir . '/' . $relativeName;
                $sourceName = $appConfigDir . '/' . $relativeName;

                // Do not overwrite existing files or links
                if (file_exists($linkName)) {
                    // Regular file exists, skip
                    continue;
                }

                //TODO: Redesign to use Filesystem
                // Check if the link is broken
                if (is_link($linkName)) {
                    // Remove broken symlinks before recreating
                    if ($this->io) {
                        // Check if verbose output is supported (IOInterface only)
                        $isVerbose = ($this->io instanceof IOInterface) && $this->io->isVerbose();
                        if ($isVerbose) {
                            $message = sprintf(
                                '  <comment>Removing broken symlink:</comment> %s',
                                str_replace($vendorDir . '/', 'vendor/', $linkName),
                            );
                            if ($this->io instanceof IOInterface) {
                                $this->io->write($message);
                            } else {
                                $this->io->writeln($message);
                            }
                        }
                    }
                    unlink($linkName);
                }

                if (in_array($this->mode, ['proxy', 'symlink'])) {
                    symlink($sourceName, $linkName);
                } else {
                    copy($sourceName, $linkName);
                }
            }
        }
    }
}
