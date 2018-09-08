<?php

namespace danielme85\Server;

class Helpers
{
    /**
     * Format bytes to kb, mb, gb, tb
     *
     * @param  integer $size
     * @param  integer $precision
     * @return integer
     */
    public static function formatBytes($size, $precision = 2)
    {
        if ($size > 0) {
            $size = (int) $size;
            $base = log($size) / log(1024);
            $suffixes = array(' bytes', ' KB', ' MB', ' GB', ' TB');

            return round(pow(1024, $base - floor($base)), $precision) . $suffixes[(int)floor($base)];
        } else {
            return $size;
        }
    }

    /**
     * Check if array is associative
     *
     * @param array $array
     * @return bool
     */
    public static function isArrayAssociative(array $array) : bool
    {
        return (array_values($array) !== $array);
    }

    /**
     * Get size of files in folder
     *
     * @param $path
     * @return array
     */
    public static function directorySizeAndCount($path) : array
    {
        $bytestotal = 0;
        $count = 0;

        $path = realpath($path);
        if($path!==false && $path!='' && file_exists($path)){
            foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)) as $object){
                $count++;
                $bytestotal += $object->getSize();
            }
        }
        return ['size' => self::formatBytes($bytestotal), 'count' => $count];
    }
}