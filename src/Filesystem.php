<?php

declare(strict_types=1);

namespace Horde\Composer;

use Composer\Util\Filesystem as ComposerFilesystem;

class Filesystem extends ComposerFilesystem
{
    public function relativeSymlink($source, $target) {
        if (is_link($target)) {
            unlink($target);
        }

        return parent::relativeSymlink($source, $target);
    }
}
