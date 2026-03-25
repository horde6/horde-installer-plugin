<?php

declare(strict_types=1);

namespace Horde\Composer;

use DirectoryIterator;

class PresetHandler
{
    public function __construct(private DirectoryTree $tree, private Filesystem $filesystem, private array $appPackages)
    {
    }

    public function handle(): void
    {
        $tree = $this->tree;
        $presetDir = $tree->getPresetDir();

        // If a deployment has a preset dir copy files from preset
        if (!is_dir($presetDir)) {
            return;
        }

        $configDir = $tree->getVarConfigDir();

        foreach ($this->appPackages as $app) {
            [$vendor, $name] = explode('/', $app);

            $presetAppDir = $presetDir . '/' . $name;
            if (!is_dir($presetAppDir)) {
               continue;
            }

            $configAppDir = $configDir . '/' . $name;

            // ensure the corresponding configAppDir exists
            $this->filesystem->ensureDirectoryExists($configAppDir);

            // Create an iterator for the presetAppDir
            $appDirIterator = new DirectoryIterator($presetAppDir);
            foreach ($appDirIterator as $configFile) {
                if (!$configFile->isFile()) {
                    continue;
                }
                $targetFileName = $configAppDir . '/' . $configFile->getFilename();
                // Ensure not to overwrite anything
                if (file_exists($targetFileName)) {
                    continue;
                }
                copy($configFile->getPathname(), $targetFileName);
            }
        }
    }
}
