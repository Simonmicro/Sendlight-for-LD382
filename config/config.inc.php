<?php
/*
 * Please don't use any important password! It will still in clear text after running the webinterface...
*/
$Config = [
    'authEnabled' => true,
    'authUser' => 'USER',
    'authPassword' => 'PASSWORD',
    'reloadAfterCommand' => true,
    'reloadAfterTimer' => 40,
];

$ConfigDevices = [
    '0' => [
        'DeviceName' => 'Desk lamp',
        'DeviceIP' => '192.168.0.12',
        'DevicePort' => 5577,
    ],
];

$StarredColors = [
    '0' => [
        'Nickname' => 'Max light',
        'ColorRGB' => '#FF7070',
        'ColorWW' => '255',
    ],
    '1' => [
        'Nickname' => 'Good for work',
        'ColorRGB' => '#FF742C',
        'ColorWW' => '120',
    ],
    '2' => [
        'Nickname' => 'Bedtime',
        'ColorRGB' => '#FF3800',
        'ColorWW' => '00',
    ],
];

?>
