<?php

/**
 * Factor out the common workflow from the installer plugin and the reconfigure command
 * This simplifies code reuse
 */

declare(strict_types=1);

namespace Horde\Composer;

class ExistingFilesRemover
{
    public function __construct(private DirectoryTree $tree, private Filesystem $filesystem, private array $hordeApps)
    {
    }

    /**
     * Remove existing files
     */
    public function run(): void
    {
        $tree = $this->tree;
        $filesystem = $this->filesystem;

        $hordeApps = $this->hordeApps;

        $configDir = $tree->getVarConfigDir();

        $webDir = $tree->getWebReadableRootDir();

        foreach ($hordeApps as $app) {
            [$vendorName, $appName] = explode('/', $app);
            $filesystem->remove($configDir . '/' . $appName . '/horde.local.php');
            if ($app == 'horde') {
                // remove horde registry file
                $filesystem->remove($configDir . '/horde/registry.d/00-horde.php');
                $filesystem->remove($configDir . '/horde/registry.d/01-location-' . $appName . '.php');
            } else {
                // remove app registry file
                $filesystem->remove($configDir . '/horde/registry.d/02-location-' . $appName . '.php');
            }

            // remove webdir items
            $filesystem->remove($webDir . '/' . $appName);
            $filesystem->remove($webDir . '/js/' . $appName);
            $filesystem->remove($webDir . '/themes/' . $appName);

            // remove vendor dir items
            $appDir = $tree->getVendorPackageDir($vendorName, $appName);
            $appConfigDir = $appDir . '/config';
            $filesystem->remove($appConfigDir . '/conf.php');
            $filesystem->remove($appConfigDir . '/horde.local.php');
            $filesystem->remove($appConfigDir . '/hooks.php');
            $filesystem->remove($appConfigDir . '/backends.local.php');
            $filesystem->remove($appConfigDir . '/prefs.local.php');
            $filesystem->remove($appConfigDir . '/routes.local.php');
        }
    }
}
