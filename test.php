<?php

$keys = [];
$now = time();

$minutes = 60;

for ($time = $now - $minutes * 60; $time <= $now; $time += 60) {
    $keys[] = 'prefix-' . date('dHi', $time);
}

echo json_encode($keys);
