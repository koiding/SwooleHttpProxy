<?php
include "Proxy.php";

$conf = [
    'local_ip' => '0.0.0.0',
    'local_port' => 9988,
];

$proxy = new Proxy($conf['local_ip'], $conf['local_port']);

