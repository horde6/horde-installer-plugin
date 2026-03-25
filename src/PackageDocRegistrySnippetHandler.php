<?php

declare(strict_types=1);

namespace Horde\Composer;

use DirectoryIterator;

/**
 * Look for registry snippets in all app's doc/registry.d folder
 *
 * An installed app may be present in the default registry or it may provide
 * a snippet in its doc/registry.d folder. Otherwise the admin must place the
 * snippet into the var/config/horde/registry.d folder himself.
 *
 * A snippet should never override an existing file
 */
class PackageDocRegistrySnippetHandler
{
    /**
     * Constructor
     *
     * @param DirectoryTree $tree
     * @param Filesystem $filesystem
     */
    public function __construct(private DirectoryTree $tree, private Filesystem $filesystem)
    {
    }

    /**
     * Scan all packages for a registry snippet
     *
     * Copy snippets to the horde base app's registry snippet dir
     *
     * @return void
     */
    public function handle(): void
    {
        $configRegistryDir = $this->tree->getVarConfigDir() . '/horde/registry.d';

        $this->filesystem->ensureDirectoryExists($configRegistryDir);
        foreach ($this->tree->getVendors() as $vendor) {
            $vendorDir = $this->tree->getVendorSpecificDir($vendor);
            foreach ($this->tree->getPackagesByVendor($vendor) as $package) {
                // TODO: Check for a .yml file to ensure it is a valid package
                $sourceDir = $this->tree->getDependencyDir($vendor, $package) . '/doc/registry.d';
                if (!is_dir($sourceDir) || !is_readable($sourceDir)) {
                    continue;
                }
                $files = new DirectoryIterator($sourceDir);
                foreach ($files as $entry) {
                    if ($files->isFile()) {
                        copy($files->getPathName(), $configRegistryDir . '/' . $entry);
                    }
                }
            }
        }
    }
}
