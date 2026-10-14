<?php

namespace App\Support;

use Composer\InstalledVersions;
use LogicException;
use OpenSpout\Reader\Wrapper\XMLReader;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Reader\XLSX\RowIterator;
use ReflectionNamedType;
use ReflectionProperty;
use Throwable;

final class XlsxReaderCleanup
{
    private readonly ReflectionProperty $xmlReader;

    public function __construct()
    {
        // OpenSpout 4.32 close() omits the sheet XML stream, which survives early
        // returns through RowIterator/XMLProcessor callback cycles. There is no
        // public iterator close API in v4. Keep this private dependency here;
        // upgrades must pass the immediate-unlink regressions without forced GC.
        $version = InstalledVersions::getVersion('openspout/openspout');
        if ($version === null || ! str_starts_with($version, '4.')) {
            throw new LogicException('Review XLSX resource cleanup for this OpenSpout version.');
        }
        $property = new ReflectionProperty(RowIterator::class, 'xmlReader');
        $type = $property->getType();
        if (! $type instanceof ReflectionNamedType || $type->getName() !== XMLReader::class) {
            throw new LogicException('The OpenSpout XLSX iterator cleanup contract has changed.');
        }
        $this->xmlReader = $property;
    }

    public function close(?Reader $reader, ?RowIterator $rows): void
    {
        try {
            if ($rows !== null) {
                $xmlReader = $this->xmlReader->getValue($rows);
                if (! $xmlReader instanceof XMLReader) {
                    throw new LogicException('The OpenSpout XLSX iterator has no XML reader.');
                }
                $xmlReader->close();
            }
        } finally {
            try {
                $reader?->close();
            } catch (Throwable) {
                // Preserve the primary import error if archive/cache cleanup fails.
            }
        }
    }
}
