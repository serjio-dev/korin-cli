<?php

declare(strict_types=1);

namespace Korin\Gedcom\Cli;

/** @internal paths given on the command line */
final class Files
{
    /**
     * The path korin/gedcom reads for a file: gedcom.ged of a .gdz archive is read through zip://,
     * decompressed as it is read.
     */
    public static function readable(string $path): string
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new UsageException("Cannot read {$path}");
        }
        if (!self::isArchive($path)) {
            return $path;
        }
        if (!class_exists(\ZipArchive::class)) {
            throw new UsageException("Reading {$path} needs ext-zip");
        }
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::RDONLY) !== true) {
            throw new UsageException("{$path} is not a zip archive");
        }
        $found = $zip->locateName('gedcom.ged') !== false;
        $zip->close();
        if (!$found) {
            throw new UsageException("{$path} has no gedcom.ged");
        }

        return 'zip://' . realpath($path) . '#gedcom.ged';
    }

    public static function isArchive(string $path): bool
    {
        return strcasecmp(pathinfo($path, PATHINFO_EXTENSION), 'gdz') === 0;
    }
}
