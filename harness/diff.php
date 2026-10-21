<?php
$a = json_decode(file_get_contents($argv[1]), true); $b = json_decode(file_get_contents($argv[2]), true);
$same = 0;
foreach (array_unique(array_merge(array_keys($a), array_keys($b))) as $k) {
  $x = $a[$k] ?? '<missing>'; $y = $b[$k] ?? '<missing>';
  if ($x === $y) { $same++; continue; }
  echo "=== $k\n";
  $fx = json_encode($x, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); $fy = json_encode($y, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  echo " native: ", mb_strimwidth($fx, 0, 700, '…'), "\n lib:    ", mb_strimwidth($fy, 0, 700, '…'), "\n";
}
echo "\nidentical: $same / ", count($a), "\n";
