<?php
require_once __DIR__ . '/vendor/autoload.php';
$watchService = getenv('WATCH_SERVICE');
$userName = getenv('USER_NAME');
$privateKey = getenv('PRIVATE_KEY');

if (!($watchService && $userName && $privateKey)) {
    die("Invalid configuration, you must provide the following environment variables: WATCH_SERVICE, USER_NAME, PRIVATE_KEY.\n");
}

list($stack, $service) = explode('/', $watchService);

function getPublicIps($service) {
    // Get a public IP for the service:
    $host = file_get_contents("http://rancher-metadata.rancher.internal/2015-12-19/services/$service/containers/0/host_uuid");
    $ip  = file_get_contents("http://rancher-metadata.rancher.internal/2015-12-19/hosts/$host/agent_ip");
    $ip = "37.97.130.105";
    echo "Resolved target service to IP: $ip\n";
    return $ip;
}

$factory = new \SamIT\TransIP\ServiceFactory(
    $userName,
    $privateKey
);

while(true) {
    try {
        $ip = getPublicIps($service);
        $haip = $factory->getHaipService()->getHaips()[0];
        echo "Found: {$haip->getName()} with IP {$haip->getVpsIpv4Address()}\n";

        // Iterate over VPSes.
        foreach ($factory->getVpsService()->getVpses() as $vps) {
            if ($vps->getIpAddress() === $ip) {
                // Do something
                if ($haip->getVpsIpv4Address() !== $ip) {
                    echo "Changing HA-IP to $ip..\n";
                    $factory->getHaipService()->changeHaipVps($haip->getName(), $vps->getName());
                }
                break;
            }
        }
    } catch (\Throwable $t) {
        fwrite(STDERR, $t->getMessage());
        fwrite(STDERR, $t->getTraceAsString());
    }

    sleep(10);
}