<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use craft\helpers\FileHelper;

class FilePathHelper
{
    public const MAX_SEGMENT_LENGTH = 255;

    /**
     * Checks the complete path and each component in bytes before filesystem calls.
     */
    public static function isWithinLengthLimits(string $path): bool
    {
        $path = FileHelper::absolutePath($path);
        if (strlen($path) >= PHP_MAXPATHLEN) {
            return false;
        }

        foreach (explode(DIRECTORY_SEPARATOR, $path) as $segment) {
            if (strlen($segment) > self::MAX_SEGMENT_LENGTH) {
                return false;
            }
        }

        return true;
    }
}
