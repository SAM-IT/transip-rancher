<?php
require_once __DIR__ . '/vendor/autoload.php';
$watchService = getenv('WATCH_SERVICE');
$userName = getenv('USER_NAME');
$privateKey = getenv('PRIVATE_KEY');
$interval = min(intval(getenv('INTERVAL')), 5);
$debug = strcasecmp(getenv('DEBUG'), 'true') === 0;

if (!($watchService && $userName && $privateKey)) {
    die("Invalid configuration, you must provide the following environment variables: WATCH_SERVICE, USER_NAME, PRIVATE_KEY.\n");
}

list($stack, $service) = explode('/', $watchService);

function log_message($msg, $force = false) {
    global $debug;

    if ($debug || $force) {
        if (is_array($msg)) {
            print_r($msg);
        } else {
            echo $msg . "\n";
        }
    }
}

function getPublicIps($service) {
    // Get a public IP for the service:
    $containers = explode("\n", file_get_contents("http://rancher-metadata.rancher.internal/2015-12-19/services/$service/containers"));
    // Iterate over containers:
    $hosts = [];
    foreach($containers as $i => $name) {
        if (!empty($name)) {
            $hosts[] = file_get_contents("http://rancher-metadata.rancher.internal/2015-12-19/services/$service/containers/$i/host_uuid");
        }

    }

    // Iterate over hosts:
    $ips = [];
    foreach($hosts as $uuid) {
        $ips[] = file_get_contents("http://rancher-metadata.rancher.internal/2015-12-19/hosts/$uuid/agent_ip");
    }
    $ips = array_unique($ips);
    log_message("Resolved target service to the following IPs:");
    log_message($ips);
    return $ips;
}

$factory = new \SamIT\TransIP\ServiceFactory(
    $userName,
    $privateKey,
    'readwrite'
);

try {
    $ips = getPublicIps($service);
    $haip = $factory->getHaipService()->getHaips()[0];
    log_message("Found: {$haip->getName()} with IP {$haip->getVpsIpv4Address()}");

    // Check if the HA-IP is already one of the public IPs for the service.
    if (!in_array($haip->getVpsIpv4Address(), $ips, true)) {

        // Iterate over VPSes.
        foreach ($factory->getVpsService()->getVpses() as $vps) {
            if (in_array($vps->getIpAddress(), $ips, true)) {
                log_message("Changing HA-IP to {$vps->getIpAddress()}..", true);
                $factory->getHaipService()->changeHaipVps($haip->getName(), $vps->getName());
                break;
            }
        }
    }
} catch (\Throwable $t) {
    fwrite(STDERR, $t->getMessage() . "\n");
    fwrite(STDERR, $t->getTraceAsString());
}