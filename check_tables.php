<?php
require 'config/db.php';
$out = [];
$res = $conn->query("SHOW TABLES");
while ($row = $res->fetch_array()) {
    $t = $row[0];
    $out[$t] = [];
    $r = $conn->query("DESCRIBE `$t`");
    while ($c = $r->fetch_assoc()) {
        $out[$t][] = $c['Field'] . ' (' . $c['Type'] . ')';
    }
}
file_put_contents('db_dump_check.json', json_encode($out, JSON_PRETTY_PRINT));
