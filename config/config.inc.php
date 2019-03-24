<?php
/*
 * Please don't use any important password! It will still in clear text after running the webinterface...
*/
$Config = [
    'authEnabled' => true, //Some CGI-configurations will fail with HTTP-Auth, so maybe disable it...
    'authUser' => 'USER',
    'authPassword' => 'PASSWORD',
    'reloadAfterCommand' => true,
    'reloadAfterTimer' => 40,
];

$ConfigDevices = [
    '0' => [ //Make sure to increment only by ONE
        'DeviceName' => 'Desk lamp 1',
        'DeviceIP' => '192.168.0.42', //Domains or hostnames are accepted too
        'DevicePort' => 5577, //Default port, will maybe change
    ],
    '1' => [
        'DeviceName' => 'Desk lamp 2',
        'DeviceIP' => '192.168.0.43',
        'DevicePort' => 5577,
    ],
];

$StarredColors = [
    '0' => [ //Make sure to increment only by ONE
        'Nickname' => 'Max light', //Try to make it short
        'ColorRGB' => '#FF7070', //Plain old HTML code... Maybe copy it out of the selector from the interface
        'ColorWW' => '255', //0...255 For the value of the of the WW-pin - DON'T SET INVALID VALUES!
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
