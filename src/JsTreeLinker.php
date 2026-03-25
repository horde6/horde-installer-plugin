<?php

declare(strict_types=1);

namespace Horde\Composer;

use ErrorException;

class JsTreeLinker
{
    /**
     * Constructor
     *
     * @param Filesystem $filesystem
     * @param DirectoryTree $tree
     * @param string[] $apps
     * @param string[] $libs
     * @param string $mode
     */
    public function __construct(
        private DirectoryTree $tree,
        private Filesystem $filesystem,
        private array $apps = [],
        private array $libs = [],
        private string $mode = 'proxy'
    ) {
    }
    /**
     * Build the web/js/ symlink tree
     *
     * Dependencies of type horde-application or horde-library can have a
     * js dir which needs to be exposed web-readable
     *
     * Traditionally, horde/js contains both the js from horde base package
     * and from libraries while apps have their JS in horde/$app/js
     *
     * In Composer based setup, we build our own symlink structure and
     * tweak registry to the new locations
     *
     * We always build the whole tree even though this may happen
     * multiple times in installations with many apps
     *
     * @return void
     */
    public function run(): void
    {
        $tree = $this->tree;
        $webDir = $this->tree->getWebReadableRootDir();
        $jsDir = $webDir . '/js';

        $this->filesystem->ensureDirectoryExists($jsDir);

        // app javascript dirs are exposed under js/$app
        foreach ($this->apps as $app) {
            [$vendor, $name] =  explode('/', $app, 2);
            $appPath = $tree->getVendorPackageDir($vendor, $name);

            $jsSourcePath = $appPath . '/js';
            if (!$this->filesystem->isReadable($jsSourcePath)) {
                continue;
            }

            $targetDir = $jsDir . '/' . $name;
            $this->linkDir($jsSourcePath, $targetDir);
        }

        // Library javascript dirs are exposed under js/horde/
        $targetDir = $jsDir . '/horde';
        foreach ($this->libs as $lib) {
            [$vendor, $name] =  explode('/', $lib, 2);
            $libraryPath = $tree->getVendorPackageDir($vendor, $name);

            $jsSourcePath = $libraryPath . '/js';
            if (!$this->filesystem->isReadable($jsSourcePath)) {
                continue;
            }

            $this->linkDir($jsSourcePath, $targetDir);
        }
    }

    // Link all files and subdirs from source dir to target dir
    public function linkDir(string $sourceDir, string $targetDir): void
    {
        $this->filesystem->ensureDirectoryExists($targetDir);
        try {
            $sourceDirHandle = opendir($sourceDir);
            if ($sourceDirHandle === false) {
                return;
            }
        } catch (ErrorException $errorException) {
            return;
        }

        $link = in_array($this->mode, ['symlink', 'proxy']);
        while (false !== ($sourceItem = readdir($sourceDirHandle))) {
            if ($sourceItem == '.' || $sourceItem == '..') {
                continue;
            }

            $sourceFile = $sourceDir . '/' . $sourceItem;
            $targetFile = $targetDir . '/' . $sourceItem;

            if ($link) {
                $this->filesystem->relativeSymlink($sourceFile, $targetFile);
            } elseif (is_file($sourceFile)) {
                copy($sourceFile, $targetFile);
            } elseif (is_dir($sourceFile)) {
                $this->linkDir($sourceFile, $targetFile);
            }
        }
        closedir($sourceDirHandle);
    }
}
