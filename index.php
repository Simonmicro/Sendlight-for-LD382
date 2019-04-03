<?php
require_once('config/config.inc.php');

if($Config['authEnabled']) {
    //echo $_SERVER['PHP_AUTH_USER'] . $_SERVER['PHP_AUTH_PW'] . ":" . $Config['authUser'] . $Config['authPassword'];
    if (!isset($_SERVER['PHP_AUTH_USER']) || !($_SERVER['PHP_AUTH_USER'] == $Config['authUser'] && $_SERVER['PHP_AUTH_PW'] == $Config['authPassword'])) {
        header('WWW-Authenticate: Basic realm="Sendlight control for LD382"');
        header('HTTP/1.0 401 Unauthorized');
        die('401 Unauthorized');
    }
}

//Try to init/load a (existing) session
if(session_status() == PHP_SESSION_DISABLED) {
    die('Critical error: PHP sessions are deactivated.');
}
session_id(0);
if(!session_start(['cookie_lifetime' => 7200,])) {
    die('Critical error. Cant start session.');
}

//Fill $_SESSION with default values...
if(!isset($_SESSION['initialized']) || !$_SESSION['initialized'] || isset($_GET['resetSession'])) {
    $_SESSION['hexvalueRed'] = 'FF';
    $_SESSION['hexvalueGreen'] = '70';
    $_SESSION['hexvalueBlue'] = '70';
    $_SESSION['hexvalueWW'] = 'FF';
    $_SESSION['staticAction'] = 'fadeColor7';
    $_SESSION['staticActionSpeed'] = 12;
    $_SESSION['staticActionResetColors'] = true;
    $_SESSION['animationSpeed'] = 4;
    $_SESSION['deviceID'] = 0;
    $_SESSION['setToColorFadingSpeed'] = 4;

    $_SESSION['initializedAt'] = date('Y-m-d H:i:s');
    $_SESSION['initializedEnd'] = date('Y-m-d H:i:s', time() + 7200);
    $_SESSION['initialized'] = true;
}

function LD382_2018_runAnimation($fromR, $fromG, $fromB, $fromWW, $toR, $toG, $toB, $toWW, $fps, $duration, $deviceIP, $devicePort) {
    if($fromR != $toR || $fromG != $toG || $fromB != $toB || $fromWW != $toWW) { //Check if we would really need to run this animation...
        $valueRed = hexdec($fromR);
        $valueRed_change = (hexdec($fromR) - hexdec($toR)) / $duration / $fps;
        $valueGreen = hexdec($fromG);
        $valueGreen_change = (hexdec($fromG) - hexdec($toG)) / $duration / $fps;
        $valueBlue = hexdec($fromB);
        $valueBlue_change = (hexdec($fromB) - hexdec($toB)) / $duration / $fps;
        $valueWW = hexdec($fromWW);
        $valueWW_change = (hexdec($fromWW) - hexdec($toWW)) / $duration / $fps;
        for($i = 0; $i <= $duration * $fps; $i++) {
            echo "Doing step " . $i . " of second " . ($i/$fps) . "...<br>";
            LD382_2018_sendCommand($deviceIP, $devicePort, array('31', dechex($valueRed), dechex($valueGreen), dechex($valueBlue), dechex($valueWW), '00', '0f'));

            $valueRed = $valueRed - $valueRed_change;
            $valueGreen = $valueGreen - $valueGreen_change;
            $valueBlue = $valueBlue - $valueBlue_change;
            $valueWW = $valueWW - $valueWW_change;
            sleep(1 / $fps);
        }
    }
}

function LD382_2018_sendCommand ($deviceIP, $devicePort, $dataArray) {
    echo "Sending a command to $deviceIP on port $devicePort...<br>";
    echo "Calculating checksum for the hexData...<br>";
    $hexCheckSum = 0;
    $hexCommandData = '';
    foreach($dataArray as $heyArrayID => $hexValue) {
        //Fix hexNumber to 2
        $hexValue = substr($hexValue, -2);
        if(strlen($hexValue) == 1) {
            $hexValue = "0" . $hexValue;
        }
        //$dataArray[$heyArrayID] $hexValue;
        $hexCheckSum += hexdec($hexValue);
        $hexCommandData .= $hexValue;
    }
    $hexCheckSum = substr(dechex($hexCheckSum), -2);
    if(strlen($hexCheckSum) == 1) {
        $hexCheckSum = "0" . $hexCheckSum;
    }
    $hexCommandData .= $hexCheckSum;
    echo "Calculated checksum is '$hexCheckSum'<br>";

    echo "Creating socket...<br>";
    if(!($sock = socket_create(AF_INET, SOCK_STREAM, getprotobyname("tcp")))) {
        $errorcode = socket_last_error();
        $errormsg = socket_strerror($errorcode);

        die("Couldn't create socket ($errorcode): $errormsg");
    }

    echo "Connecting to server...<br>";
    if(!socket_connect($sock , $deviceIP, $devicePort)) {
        $errorcode = socket_last_error();
        $errormsg = socket_strerror($errorcode);

        die("Could not connect ($errorcode): $errormsg");
    }

    echo "Converting '$hexCommandData' command to binary data...<br>";
    if(strlen($hexCommandData) % 2 == 1) {
        die("Could not convert data: Invalid hex-number");
    }
    $hexCommandData = hex2bin($hexCommandData);

    echo "Sending " . strlen($hexCommandData)*8 . " bytes...<br>";
    if( ! socket_send ( $sock, $hexCommandData, strlen($hexCommandData) , 0)) {
        $errorcode = socket_last_error();
        $errormsg = socket_strerror($errorcode);

        die("Could not send data ($errorcode): $errormsg");
    }
}

if(isset($_GET['switchDevice'])) {
    $_SESSION['deviceID'] = $_GET['switchDevice'];
}

if(isset($_GET['action'])) {
    if($_GET['action'] == 'setToColorFading') {
        $_SESSION['hexvalueRed'] = substr($_GET['hexvalueColor'], -6, 2);
        $_SESSION['hexvalueGreen'] = substr($_GET['hexvalueColor'], -4, 2);
        $_SESSION['hexvalueBlue'] = substr($_GET['hexvalueColor'], -2, 2);
        $_SESSION['hexvalueWW'] = dechex($_GET['hexvalueWW']);
        $_SESSION['setToColorFadingSpeed'] = $_GET['setToColorFadingSpeed'];

        $_SESSION['command'] = array('31', $_SESSION['hexvalueRed'], $_SESSION['hexvalueGreen'], $_SESSION['hexvalueBlue'], $_SESSION['hexvalueWW'], '00', '0f');
    }
    if($_GET['action'] == 'setColorBySelect') {
        $_SESSION['hexvalueRed'] = substr($_GET['hexvalueColor'], -6, 2);
        $_SESSION['hexvalueGreen'] = substr($_GET['hexvalueColor'], -4, 2);
        $_SESSION['hexvalueBlue'] = substr($_GET['hexvalueColor'], -2, 2);
        $_SESSION['hexvalueWW'] = dechex($_GET['hexvalueWW']);

        $_SESSION['command'] = array('31', $_SESSION['hexvalueRed'], $_SESSION['hexvalueGreen'], $_SESSION['hexvalueBlue'], $_SESSION['hexvalueWW'], '00', '0f');
    }
    if($_GET['action'] == 'runStaticAction') {
        $_SESSION['staticAction'] = $_GET['staticAction'];
        $_SESSION['staticActionSpeed'] = dechex($_GET['staticActionSpeed']);
        if(isset($_GET['staticActionResetColors'])) {
            $_SESSION['staticActionResetColors'] = true;
        } else {
            $_SESSION['staticActionResetColors'] = false;
        }

        if($_SESSION['staticAction'] == 'fadeColor7') {
            $_SESSION['command'] = array('61', '25', $_SESSION['staticActionSpeed'], '0f');
        }
        if($_SESSION['staticAction'] == 'fadeColorRed') {
            $_SESSION['command'] = array('61', '26', $_SESSION['staticActionSpeed'], '0f');
        }
        if($_SESSION['staticAction'] == 'fadeColorGreen') {
            $_SESSION['command'] = array('61', '27', $_SESSION['staticActionSpeed'], '0f');
        }
        if($_SESSION['staticAction'] == 'fadeColorBlue') {
            $_SESSION['command'] = array('61', '28', $_SESSION['staticActionSpeed'], '0f');
        }
        if($_SESSION['staticAction'] == 'fadeColorWhite') {
            $_SESSION['command'] = array('61', '2c', $_SESSION['staticActionSpeed'], '0f');
        }
        if($_SESSION['staticAction'] == 'fadeColorRedGreen') {
            $_SESSION['command'] = array('61', '2d', $_SESSION['staticActionSpeed'], '0f');
        }
        if($_SESSION['staticAction'] == 'fadeColorRedBlue') {
            $_SESSION['command'] = array('61', '2e', $_SESSION['staticActionSpeed'], '0f');
        }
        if($_SESSION['staticAction'] == 'fadeColorGreenBlue') {
            $_SESSION['command'] = array('61', '2f', $_SESSION['staticActionSpeed'], '0f');
        }
        if($_SESSION['staticAction'] == 'strobeColor7') {
            $_SESSION['command'] = array('61', '30', $_SESSION['staticActionSpeed'], '0f');
        }
        if($_SESSION['staticAction'] == 'strobeColorRed') {
            $_SESSION['command'] = array('61', '31', $_SESSION['staticActionSpeed'], '0f');
        }
        if($_SESSION['staticAction'] == 'strobeColorGreen') {
            $_SESSION['command'] = array('61', '32', $_SESSION['staticActionSpeed'], '0f');
        }
        if($_SESSION['staticAction'] == 'strobeColorBlue') {
            $_SESSION['command'] = array('61', '33', $_SESSION['staticActionSpeed'], '0f');
        }
        if($_SESSION['staticAction'] == 'strobeColorWhite') {
            $_SESSION['command'] = array('61', '37', $_SESSION['staticActionSpeed'], '0f');
        }
        if($_SESSION['staticAction'] == 'jumpingColor7') {
            $_SESSION['command'] = array('61', '38', $_SESSION['staticActionSpeed'], '0f');
        }
    }
    if($_GET['action'] == 'runAnimation') {
        $_SESSION['animationSpeed'] = $_GET['animationSpeed'];
    }
}
?>
<!DOCTYPE html>
<html>
    <head>
        <title>Sendlight control by Simon Beginn</title>
        <style>
            div.dashboard {
                margin-top: 0.5em;
                display: flex;
                flex-wrap: wrap;
            }
            div.dashboard_element {
                flex-grow: 1;
                padding: 1em;
                margin: 0.5em;
                box-shadow: 0.5em 0.5em 0.6em rgba(0, 0, 0, 0.2);
                border-top: 0.3em solid <?php echo "#" . $_SESSION['hexvalueRed'] . $_SESSION['hexvalueGreen'] . $_SESSION['hexvalueBlue']; ?>;
                border-left: 0.1em solid rgba(0, 0, 0, 0.5);
                border-right: 0.1em solid rgba(0, 0, 0, 0.5);
                border-bottom: 0.1em solid rgba(0, 0, 0, 0.5);
                border-radius: 0.2em;
            }
            div.headline_menu {
                display: flex;
                flex-wrap: wrap;
            }
            div.headline_menu_item {
                flex-grow: 1;
                padding: 0.5em;
                margin-left: 0.5em;
                margin-right: 0.5em;
                box-shadow: 0em 0em 1em rgba(0, 0, 0, 0.2);
                border-radius: 1em;
            }
            button {
                margin: 0.5em;
                padding: 0.5em;
                width: 100%;
            }
            div.loadingAnimation {
                transition: 500ms;
                position: absolute;
                top: -100%;
                left: 0;
                right: 0;
                transform: translateY(-50%);
            }
        </style>
        <script>
            function copyText() {
                var copyText = document.getElementById("copyText");
                copyText.select();
                document.execCommand("copy");

                var tooltip = document.getElementById("copyButton");
                tooltip.innerHTML = "Text copied!";
            }
            function loadingAnimationShow() {
                document.getElementById("loadingAnimation").style.top = '50%';
            }
            function loadingAnimationHide() {
                //document.getElementById("loadingAnimation").style.top = '-100%';
            }
        </script>
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, minimum-scale=1, user-scalable=no, minimal-ui">
        <?php if(isset($_GET['action']) && $Config['reloadAfterCommand']) {?>
            <meta http-equiv="refresh" content="<?php echo $Config['reloadAfterTimer']; ?>; url=./">
        <?php } ?>
    </head>
    <body>
        <div class='loadingAnimation' onclick='aloadingAnimationHide()' id='loadingAnimation'>
            <center style='background-color: rgba(0, 0, 0, 0.6);'><img src="img/loading.svg"></img></center>
        </div>
        <div class="headline_menu">
            <div class="headline_menu_item"><center><a href="<?php if(count($ConfigDevices) > 1) { echo './?switchDeviceMenu'; } else { echo './'; } ?>"><?php echo $ConfigDevices[$_SESSION['deviceID']]['DeviceName']; ?></a></center></div>
            <div class="headline_menu_item"><center><a onclick="loadingAnimationShow()" href="?deviceID=<?php echo $_SESSION['deviceID']; ?>&action=powerOff">Power OFF</a></center></div>
            <div class="headline_menu_item"><center><a onclick="loadingAnimationShow()" href="?deviceID=<?php echo $_SESSION['deviceID']; ?>&action=powerOn">Power ON</a></center></div>
        </div>
        <div class="dashboard">
            <?php if(isset($_GET['switchDeviceMenu'])) {
                foreach($ConfigDevices as $deviceID => $menuEntry) {
                    ?>
                        <div class="dashboard_element">
                            <h3><?php echo $menuEntry['DeviceName']; ?></h3>
                            IP: <?php echo $ConfigDevices[$_SESSION['deviceID']]['DeviceIP']; ?><br>
                            Port: <?php echo $ConfigDevices[$_SESSION['deviceID']]['DevicePort']; ?><br>
                            <form onsubmit="loadingAnimationShow()" action="">
                                <input type="hidden" name="switchDevice" value="<?php echo $deviceID; ?>">
                                <button type="submit">Switch</button>
                            </form>
                        </div>
                    <?php
                    }
                } else { ?>
                <div class="dashboard_element">
                    <h3>Animations</h3>
                    <form onsubmit="loadingAnimationShow()" action="">
                        <input type="hidden" name="deviceID" value="<?php echo $_SESSION['deviceID']; ?>">
                        <input type="hidden" name="action" value="runAnimation">
                        <input type="radio" name="animation" id="fadeOff" value="fadeOff" required><label for="fadeOff">Fade to OFF</label><br>
                        <input type="radio" name="animation" id="fadeOn" value="fadeOn" required><label for="fadeOn">Fade to ON</label><br>
                        <input type="range" name="animationSpeed" value=<?php echo hexdec($_SESSION['animationSpeed']); ?> min=1 max=32 id="animationSpeed"><label for="animationSpeed">Speed</label><br>
                        <button type="submit">Send</button>
                    </form>
                </div>
                <div class="dashboard_element">
                    <h3>Custom color</h3>
                    <form onsubmit="loadingAnimationShow()" action="">
                        <input type="hidden" name="deviceID" value="<?php echo $_SESSION['deviceID']; ?>">
                        <input type="hidden" name="action" value="setColorBySelect">
                        <input type="color" name="hexvalueColor" id="hexvalueColor1" value="<?php echo "#" . $_SESSION['hexvalueRed'] . $_SESSION['hexvalueGreen'] . $_SESSION['hexvalueBlue']; ?>"><label for="hexvalueColor1">RGB Color</label><br>
                        <input type="range" name="hexvalueWW" id="hexvalueWW1" value=<?php echo hexdec($_SESSION['hexvalueWW']); ?> min=0 max=255><label for="hexvalueWW1">Warmwhite</label><br>
                        <button type="submit">Send</button>
                    </form>
                </div>
                <div class="dashboard_element">
                    <h3>Fade to custom color</h3>
                    <form onsubmit="loadingAnimationShow()" action="">
                        <input type="hidden" name="deviceID" value="<?php echo $_SESSION['deviceID']; ?>">
                        <input type="hidden" name="action" value="setToColorFading">
                        <input type="hidden" name="oldHexvalueColor" value="<?php echo "#" . $_SESSION['hexvalueRed'] . $_SESSION['hexvalueGreen'] . $_SESSION['hexvalueBlue']; ?>">
                        <input type="hidden" name="oldHexvalueWW" value=<?php echo hexdec($_SESSION['hexvalueWW']); ?> min=0 max=255>
                        <input type="color" name="hexvalueColor" id="hexvalueColor2" value="<?php echo "#" . $_SESSION['hexvalueRed'] . $_SESSION['hexvalueGreen'] . $_SESSION['hexvalueBlue']; ?>"><label for="hexvalueColor2">RGB Color</label><br>
                        <input type="range" name="hexvalueWW" id="hexvalueWW2" value=<?php echo hexdec($_SESSION['hexvalueWW']); ?> min=0 max=255><label for="hexvalueWW2">Warmwhite</label><br>
                        <input type="range" name="setToColorFadingSpeed" id="setToColorFadingSpeed1" value=<?php echo $_SESSION['setToColorFadingSpeed']; ?> min=1 max=32><label for="setToColorFadingSpeed1">Speed</label><br>
                        <button type="submit">Send</button>
                    </form>
                </div>
                <?php if(count($StarredColors) >= 1) { ?>
                    <div class="dashboard_element">
                        <h3>Starred colors</h3>
                        <?php foreach($StarredColors as $colorData) { ?>
                            <form onsubmit="loadingAnimationShow()" action="">
                                <input type="hidden" name="deviceID" value="<?php echo $_SESSION['deviceID']; ?>">
                                <input type="hidden" name="action" value="setColorBySelect">
                                <input type="hidden" name="hexvalueColor" value="<?php echo $colorData['ColorRGB']; ?>">
                                <input type="hidden" name="hexvalueWW" value="<?php echo $colorData['ColorWW']; ?>" min=0 max=255>
                                <button type="submit"><?php echo $colorData['Nickname']; ?></button>
                            </form>
                        <?php } ?>
                    </div>
                    <div class="dashboard_element">
                        <h3>Fade to starred color</h3>
                        <?php foreach($StarredColors as $colorData) { ?>
                            <form onsubmit="loadingAnimationShow()" action="">
                                <input type="hidden" name="deviceID" value="<?php echo $_SESSION['deviceID']; ?>">
                                <input type="hidden" name="action" value="setToColorFading">
                                <input type="hidden" name="oldHexvalueColor" value="<?php echo "#" . $_SESSION['hexvalueRed'] . $_SESSION['hexvalueGreen'] . $_SESSION['hexvalueBlue']; ?>">
                                <input type="hidden" name="oldHexvalueWW" value=<?php echo hexdec($_SESSION['hexvalueWW']); ?> min=0 max=255>
                                <input type="hidden" name="hexvalueColor" value="<?php echo $colorData['ColorRGB']; ?>">
                                <input type="hidden" name="hexvalueWW" value="<?php echo $colorData['ColorWW']; ?>" min=0 max=255>
                                <input type="range" name="setToColorFadingSpeed" value=<?php echo $_SESSION['setToColorFadingSpeed']; ?> min=1 max=32>Speed<br>
                                <button type="submit"><?php echo $colorData['Nickname']; ?></button>
                            </form>
                        <?php } ?>
                    </div>
                <?php } ?>
                <div class="dashboard_element">
                    <h3>Static commands</h3>
                    <form onsubmit="loadingAnimationShow()" action="">
                        <input type="hidden" name="deviceID" value="<?php echo $_SESSION['deviceID']; ?>">
                        <input type="hidden" name="action" value="runStaticAction">
                        <input type="radio" name="staticAction"<?php if($_SESSION['staticAction'] == 'fadeColor7') { echo ' checked="checked"'; } ?> id="fadeColor7" value="fadeColor7"><label for="fadeColor7">7 color fade</label><br>
                        <input type="radio" name="staticAction"<?php if($_SESSION['staticAction'] == 'fadeColorRed') { echo ' checked="checked"'; } ?> id="fadeColorRed" value="fadeColorRed"><label for="fadeColorRed">Red color fade</label><br>
                        <input type="radio" name="staticAction"<?php if($_SESSION['staticAction'] == 'fadeColorGreen') { echo ' checked="checked"'; } ?> id="fadeColorGreen" value="fadeColorGreen"><label for="fadeColorGreen">Green color fade</label><br>
                        <input type="radio" name="staticAction"<?php if($_SESSION['staticAction'] == 'fadeColorBlue') { echo ' checked="checked"'; } ?> id="fadeColorBlue" value="fadeColorBlue"><label for="fadeColorBlue">Blue color fade</label><br>
                        <input type="radio" name="staticAction"<?php if($_SESSION['staticAction'] == 'fadeColorWhite') { echo ' checked="checked"'; } ?> id="fadeColorWhite" value="fadeColorWhite"><label for="fadeColorWhite">White color fade</label><br>
                        <input type="radio" name="staticAction"<?php if($_SESSION['staticAction'] == 'fadeColorRedGreen') { echo ' checked="checked"'; } ?> id="fadeColorRedGreen" value="fadeColorRedGreen"><label for="fadeColorRedGreen">Red/Green color fade</label><br>
                        <input type="radio" name="staticAction"<?php if($_SESSION['staticAction'] == 'fadeColorRedBlue') { echo ' checked="checked"'; } ?> id="fadeColorRedBlue" value="fadeColorRedBlue"><label for="fadeColorRedBlue">Red/Blue color fade</label><br>
                        <input type="radio" name="staticAction"<?php if($_SESSION['staticAction'] == 'fadeColorGreenBlue') { echo ' checked="checked"'; } ?> id="fadeColorGreenBlue" value="fadeColorGreenBlue"><label for="fadeColorGreenBlue">Green/Blue color fade</label><br>

                        <input type="radio" name="staticAction"<?php if($_SESSION['staticAction'] == 'strobeColor7') { echo ' checked="checked"'; } ?> id="strobeColor7" value="strobeColor7"><label for="strobeColor7">7 color strobe</label><br>
                        <input type="radio" name="staticAction"<?php if($_SESSION['staticAction'] == 'strobeColorRed') { echo ' checked="checked"'; } ?> id="strobeColorRed" value="strobeColorRed"><label for="strobeColorRed">Red color strobe</label><br>
                        <input type="radio" name="staticAction"<?php if($_SESSION['staticAction'] == 'strobeColorGreen') { echo ' checked="checked"'; } ?> id="strobeColorGreen" value="strobeColorGreen"><label for="strobeColorGreen">Green color strobe</label><br>
                        <input type="radio" name="staticAction"<?php if($_SESSION['staticAction'] == 'strobeColorBlue') { echo ' checked="checked"'; } ?> id="strobeColorBlue" value="strobeColorBlue"><label for="strobeColorBlue">Blue color strobe</label><br>
                        <input type="radio" name="staticAction"<?php if($_SESSION['staticAction'] == 'strobeColorWhite') { echo ' checked="checked"'; } ?> id="strobeColorWhite" value="strobeColorWhite"><label for="strobeColorWhite">White color strobe</label><br>

                        <input type="radio" name="staticAction"<?php if($_SESSION['staticAction'] == 'jumpingColor7') { echo ' checked="checked"'; } ?> id="jumpingColor7" value="jumpingColor7"><label for="jumpingColor7">7 color jumping</label><br>

                        <input type="checkbox" name="staticActionResetColors"<?php if($_SESSION['staticActionResetColors']) { echo ' checked="checked"'; } ?> id="staticActionResetColors"><label for="staticActionResetColors">Reset WW</label><br>
                        <input type="range" name="staticActionSpeed" value=<?php echo hexdec($_SESSION['staticActionSpeed']); ?> min=1 max=32 id="staticActionSpeed"><label for="staticActionSpeed">Speed</label><br> <!-- MAX HAS TO BE 32!!! -->
                        <button type="submit">Send</button>
                    </form>
                </div>
                <div class="dashboard_element">
                    <h3>Session information</h3>
                    <p>This session started at <?php echo $_SESSION['initializedAt']; ?> and will destroyed at <?php echo $_SESSION['initializedEnd']; ?>. Press this button to destroy your current session now - this would reset e.g. color settings, speed rates or selected animations.</p>
                    <?php if(isset($_GET['resetSession'])) { echo "Session was resetted."; } ?>
                    <form onsubmit="loadingAnimationShow()" action="">
                        <input type="hidden" name="resetSession">
                        <button type="submit">Restart session</button>
                    </form>
                </div>
                <?php if(isset($_GET['action'])) {?>
                    <div class="dashboard_element">
                        <h3>Linux command</h3>
                        Use this command on a linux shell to perform exactly this again.<br>
                        <input style="width:75%;" type="text" value='curl -s "<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['PHP_AUTH_USER']; ?>:[INSERT PASSWORD HERE]@<?php echo $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>" > /dev/null' id="copyText">
                        <button style="width:20%;" onclick="copyText()" id="copyButton">Copy to clipboard</button>  
                    </div>
                    <div class="dashboard_element">
                        <h3>Protocol</h3>
                        <?php
                            $animationSteps = 25; //25 FPS ;)
                            if($_GET['action'] == 'powerOff') {
                                LD382_2018_sendCommand($ConfigDevices[$_GET['deviceID']]['DeviceIP'], $ConfigDevices[$_GET['deviceID']]['DevicePort'], array('71', '24', '0f'));
                            }
                            if($_GET['action'] == 'powerOn') {
                                LD382_2018_sendCommand($ConfigDevices[$_GET['deviceID']]['DeviceIP'], $ConfigDevices[$_GET['deviceID']]['DevicePort'], array('71', '23', '0f'));
                            }
                            if($_GET['action'] == 'setColorBySelect') {
                                LD382_2018_sendCommand($ConfigDevices[$_GET['deviceID']]['DeviceIP'], $ConfigDevices[$_GET['deviceID']]['DevicePort'], $_SESSION['command']);
                            }
                            if($_GET['action'] == 'setToColorFading') {
                                LD382_2018_runAnimation(substr($_GET['oldHexvalueColor'], -6, 2), substr($_GET['oldHexvalueColor'], -4, 2), substr($_GET['oldHexvalueColor'], -2, 2), dechex($_GET['oldHexvalueWW']), $_SESSION['hexvalueRed'], $_SESSION['hexvalueGreen'], $_SESSION['hexvalueBlue'], $_SESSION['hexvalueWW'], $animationSteps, $_GET['setToColorFadingSpeed'], $ConfigDevices[$_GET['deviceID']]['DeviceIP'], $ConfigDevices[$_GET['deviceID']]['DevicePort']);
                            }
                            if($_GET['action'] == 'runStaticAction') {
                                if($_SESSION['staticActionResetColors']) {
                                    LD382_2018_sendCommand($ConfigDevices[$_GET['deviceID']]['DeviceIP'], $ConfigDevices[$_GET['deviceID']]['DevicePort'], array('31', '00', '00', '00', '00', '00', '0f'));
                                }
                                LD382_2018_sendCommand($ConfigDevices[$_GET['deviceID']]['DeviceIP'], $ConfigDevices[$_GET['deviceID']]['DevicePort'], $_SESSION['command']);
                            }
                            if($_GET['action'] == 'runAnimation') {
                                echo "Starting animation with a duration of " . $_GET['animationSpeed'] . " and $animationSteps steps...<br>";
                                if($_GET['animation'] == 'fadeOff') {
                                    LD382_2018_runAnimation($_SESSION['hexvalueRed'], $_SESSION['hexvalueGreen'], $_SESSION['hexvalueBlue'], $_SESSION['hexvalueWW'], '00', '00', '00', '00', $animationSteps, $_GET['animationSpeed'], $ConfigDevices[$_GET['deviceID']]['DeviceIP'], $ConfigDevices[$_GET['deviceID']]['DevicePort']);
                                    LD382_2018_sendCommand($ConfigDevices[$_GET['deviceID']]['DeviceIP'], $ConfigDevices[$_GET['deviceID']]['DevicePort'], array('71', '24', '0f'));
                                    LD382_2018_sendCommand($ConfigDevices[$_GET['deviceID']]['DeviceIP'], $ConfigDevices[$_GET['deviceID']]['DevicePort'], $_SESSION['command']);
                                }
                                if($_GET['animation'] == 'fadeOn') {
                                    LD382_2018_sendCommand($ConfigDevices[$_GET['deviceID']]['DeviceIP'], $ConfigDevices[$_GET['deviceID']]['DevicePort'], array('31', '00', '00', '00', '00', '00', '0f'));
                                    LD382_2018_sendCommand($ConfigDevices[$_GET['deviceID']]['DeviceIP'], $ConfigDevices[$_GET['deviceID']]['DevicePort'], array('71', '23', '0f'));
                                    LD382_2018_runAnimation('00', '00', '00', '00', $_SESSION['hexvalueRed'], $_SESSION['hexvalueGreen'], $_SESSION['hexvalueBlue'], $_SESSION['hexvalueWW'], $animationSteps, $_GET['animationSpeed'], $ConfigDevices[$_GET['deviceID']]['DeviceIP'], $ConfigDevices[$_GET['deviceID']]['DevicePort']);
                                }
                                echo "Finished animation.<br>";
                            }
                        ?>
                    <div>
                <?php } ?>
            <?php } ?>
        </div>
    </body>
</html>
