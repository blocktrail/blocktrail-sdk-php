<?php

namespace Blocktrail\SDK;

class OutputsNormalizer
{
    private function readArrayFormat() {
    }

    private function readKeyedFormat() {
    }

    public function normalize(array $outputs) {
        if (empty($outputs)) {
            return [];
        }

        $keys = array_keys($outputs);
        if ($keys[count($keys) - 1]) {
        }
    }
}
