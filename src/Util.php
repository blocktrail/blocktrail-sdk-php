<?php

namespace Blocktrail\SDK;

abstract class Util {
    public static function all(callable $fn, array $arr) {
        $allvalues = array_map($fn, $arr);
        return count(array_unique($allvalues)) === 1 && end($allvalues) === true;
    }

    public static function arrayMapWithIndex(callable $fn, $arr) {
        $result = [];
        $assoc = null;
        foreach ($arr as $idx => $value) {
            list($newidx, $newvalue) = $fn($idx, $value);

            if ($assoc === null) {
                $assoc = $newidx !== null;
            }

            if ($newidx === null && $assoc || $newidx !== null && !$assoc) {
                throw new \Exception("Mix of non assoc and assoc keys");
            }

            if ($assoc) {
                $result[$newidx] = $newvalue;
            } else {
                $result[] = $newvalue;
            }
        }

        return $result;
    }
}
