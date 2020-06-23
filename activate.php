<?php

/****************************************************************************************
 * LiveZilla activate.php
 *
 * Improper changes to this file may cause critical errors.
 ***************************************************************************************/

define("IN_LIVEZILLA", true);

if (!defined("LIVEZILLA_PATH"))
    define("LIVEZILLA_PATH", "./");

require(LIVEZILLA_PATH . "_definitions/definitions.inc.php");
require(LIVEZILLA_PATH . "_lib/functions.global.inc.php");
require(LIVEZILLA_PATH . "_lib/functions.index.inc.php");
require(LIVEZILLA_PATH . "_definitions/definitions.dynamic.inc.php");
require(LIVEZILLA_PATH . "_definitions/definitions.protocol.inc.php");

@set_error_handler("handleError");

if (!file_exists(LIVEZILLA_PATH . "_definitions/actl"))
    exit("NO HASH FILE");

$hashblock = file_get_contents(LIVEZILLA_PATH . "_definitions/actl");
$html = file_get_contents(LIVEZILLA_PATH . "templates/activate.tpl");
$html_resp = "";
$html_error = "block";
$html_success = "none";
$console = array();
$writeAccess = "";
$serverVersion = intval(substr(VERSION, 0, 1));
$ger = strtolower(substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2))==="de";

$html = str_replace("<!--l_actkey-->",($ger ? "Lizenzschlüssel aktivieren" : "Activate Key"),$html);
$html = str_replace("<!--l_actkeylong-->", ($ger ? "LiveZilla Lizenzschlüssel aktivieren (Offline-Aktivierung)" : "Activate LiveZilla License Key (offline activation)"), $html);
$html = str_replace("<!--l_enterkey-->", ($ger ? "Bitte Lizenzschlüssel eingeben" : "Please enter your LiveZilla license key"), $html);

if ($serverVersion < 6)
    require(LIVEZILLA_PATH . "_lib/objects.global.inc.php");

if ($serverVersion <= 5)
    $writeAccess = getFolderPermissions();

echo $writeAccess;

if (!empty($_REQUEST["serial"])) {
    $serial = strtoupper(trim($_REQUEST["serial"]));

    $keyObject = MatchKey($serial);

    if ($keyObject !== null) {
        if (
            ((class_exists("Server") && method_exists("Server", "InitDataProvider") && Server::InitDataProvider()) // 8.x, 7.x, 6.x
                || (function_exists("initDataProvider") && initDataProvider()) // 5.x
                || (function_exists("setDataProvider") && setDataProvider())) // 4.x
            && empty($writeAccess) // 5.x, 4.x, 3.x
        ) {
            if (!KeyExists($keyObject["Serial"])) {
                // version 6.x, 7.x, 8.x
                WriteLicense($keyObject);
                //EndTrial();
                $html_error = "none";
                $html_success = "block";

                if (class_exists("CacheManager"))
                    CacheManager::Flush();

                $html_resp .= $ger ? "Die Lizenz wurde aktiviert, vielen Dank." : "License activated, thank you.";

                $console[] = serialize($keyObject);
            } else
                $html_resp .= $ger ? "Der Lizenzschlüssel exitiert bereits" : "License key is already existing.";
        } else {
            $html_resp .= $ger ? "Ein Fehler ist aufgetreten, bitte überprüfen Sie die <a href=\"./index.php\">Startseite</a> für weitere Details." : "An error occoured, please check your <a href=\"./index.php\">server page</a> for details.";
        }
    } else
        $html_resp .= $ger ? "Ungültiger Lizenzschlüssel, bitte versuchen Sie es erneut." : "Invalid license key, please try again.";
}

// debug, TBR
// $html_console = "";
// foreach ($console as $line)
//     $html_console .= "console.log('" . $line . "');";

$html = str_replace("<!--res-->", (!empty($html_resp) ? 'block' : 'none'), $html);
$html = str_replace("<!--res_error-->", $html_error, $html);
$html = str_replace("<!--res_success-->", $html_success, $html);
$html = str_replace("<!--response-->", $html_resp, $html);
$html = str_replace("<!--console-->", ""/* $html_console */, $html);

exit($html);

function IsTrial()
{
    global $_CONFIG;
    if (isset($_CONFIG["gl_licl"]) && isset($_CONFIG["gl_licl"][0])) {
        if (strpos(base64_decode(base64_decode($_CONFIG["gl_licl"][0])), "VFJJQUw=") !== false) {
            return true;
        }
    }
}

function EndTrial()
{
    /*
    global $_CONFIG;
    if(IsTrial())
    {
        unset($_CONFIG["gl_licl"][0]);
        DBExecute("DELETE FROM `" . DB_PREFIX . DATABASE_CONFIG . "` WHERE `key`='gl_pr_ngl' OR `key`='gl_licl_0' LIMIT 2;");
        echo "end trial";
    }
    */
}

function WriteLicense($_keyObject)
{
    global $_CONFIG, $serverVersion, $console;

    $key = "";
    $opsamount = 0;

    $existingKey = GetConfig("gl_crc3");
    $serverId = GetConfig("gl_lzid");

    if ($existingKey)
        $console[] = "Old key: " . serialize(base64_decode($existingKey));

    if ($existingKey)
        $existingKey = explode(",", base64_decode($existingKey));

    if ($existingKey && count($existingKey) > 4 && $existingKey[5] > -2)
        $opsamount = intval($existingKey[5]);

    if (!($existingKey && count($existingKey) > 4))
        $existingKey = [time(), "-2", "-2", "-2", "-2", "1", "0"];

    $key = $existingKey[0] . ",";
    $key .= (($_keyObject["Type"] == "1") ? '1' : $existingKey[1]) . ',';
    $key .= (($_keyObject["Type"] == "2") ? '1' : $existingKey[2]) . ',';
    $key .= (($_keyObject["Type"] == "3") ? '1' : $existingKey[3]) . ',';
    $key .= (($_keyObject["Type"] == "4") ? '1' : $existingKey[4]) . ',';

    if ($_keyObject["Amount"] == -1 || $opsamount == -1)
        $key .=  '-1,';
    else
        $key .= (($_keyObject["Type"] == "5") ? ($opsamount + intval($_keyObject["Amount"])) : $opsamount) . ',';

    // compiled hash
    $console[] = $serverId;
    $console[] = $_keyObject["Type"];

    $key .= GetServerHash($serverId, $_keyObject["Type"]);

    $console[] = serialize($key);

    $count = isset($_CONFIG["gl_licl"]) ? count($_CONFIG["gl_licl"]) : 0;

    if(IsTrial())
    {
        //echo "ISTRIAL";
        $count = 0;
    } 

    //echo "<br>COUNT " . $count;

    $oak = GetOptionActivationKey($_keyObject["Amount"], $serverVersion/*$_keyObject["Major"]*/, $serverId, $_keyObject["Serial"], $_keyObject["Type"]);

    $console[] = $oak;

    if ($serverVersion > 5) {
        // DB licensing: 8.x,7.x,6.x
        if ($_keyObject["Type"] == "5") {
            $lico = [base64_encode($oak), base64_encode($_keyObject["Serial"])];
            $lico = base64_encode(serialize($lico));
            DBExecute("REPLACE INTO `" . DB_PREFIX . DATABASE_CONFIG . "` (`key`, `value`) VALUES ('gl_licl_" . $count . "','" . DBEscape($lico) . "');");
        } else {
            DBExecute("REPLACE INTO `" . DB_PREFIX . DATABASE_CONFIG . "` (`key`, `value`) VALUES ('gl_pr_" . strtolower(GetOptionName($_keyObject["Type"])) . "','" . DBEscape($oak) . "')");
        }


        DBExecute("REPLACE INTO `" . DB_PREFIX . DATABASE_CONFIG . "` (`key`, `value`) VALUES ('gl_crc3','" . DBEscape(base64_encode($key)) . "')");
        DBExecute("REPLACE INTO `" . DB_PREFIX . DATABASE_CONFIG . "` (`key`, `value`) VALUES ('gl_lcut','" . DBEscape(time()) . "')");
    } else {
        // File licensing 5.x,4.x,3.x

        // create backup
        $numb = 0;
        while (true) {
            $cfile = LIVEZILLA_PATH . "_config/config.inc.php";
            $bufile = LIVEZILLA_PATH . "_config/config.inc.php.actbackup" . $numb . ".php";
            if (!file_exists($bufile)) {
                copy($cfile, $bufile);
                break;
            }
            $numb++;
        }

        $cfiledata = file_get_contents($cfile);

        $addvars = "// OFFLINE ACTIVATOR";
        $spacer = $serverVersion > 3 ? "_" : "";

        if ($_keyObject["Type"] == "5") {
            $lico = [base64_encode($oak), base64_encode($_keyObject["Serial"])];
            $lico = base64_encode(serialize($lico));

            $addvars .= "\n\$" . $spacer . "CONFIG[\"gl_licl\"][" . intval($count) . "] = \"" . base64_encode($lico) . "\";";
        } else {
            $addvars .= "\n\$" . $spacer . "CONFIG[\"gl_pr_" . strtolower(GetOptionName($_keyObject["Type"])) . "\"] = \"" . base64_encode($oak) . "\";";
        }

        $addvars .= "\n\$" . $spacer . "CONFIG[\"gl_crc3\"] = \"" . base64_encode(base64_encode($key)) . "\";";

        $cfiledata = str_replace("?>", $addvars . "\n\n?>", $cfiledata);

        createFile($cfile, $cfiledata, true);
    }
}

function GetConfig($_key)
{
    global $CONFIG;
    if (class_exists("Server") && isset(Server::$Configuration->File)) {
        return Server::$Configuration->File[$_key];
    } else {
        return $CONFIG[$_key];
    }
}

function DBEscape($_data)
{
    if (class_exists("DBManager") && method_exists("DBManager", "RealEscape"))
        return DBManager::RealEscape($_data);
    else {
        throw new Exception("No escape function found");
    }
}

function DBExecute($_query)
{
    if (class_exists("DBManager") && method_exists("DBManager", "Execute"))
        DBManager::Execute(true, $_query); // 8.x, 7.x, 6.x
    else
        queryDB(true, $_query); // 5.x
}

function KeyExists($_serial)
{
    global $_CONFIG;
    foreach ($_CONFIG["gl_licl"] as $k => $v) {
        $a = unserialize(base64_decode((base64_decode($v))));
        if (base64_decode($a[1]) == $_serial)
            return true;
    }
    return false;
}

//OC below

function GetOptionActivationKey($_amount, $_major, $_serverId, $_serial, $_typeKey)
{
    $_typeName = GetOptionName($_typeKey);
    if ($_typeName == "OPR")
        return md5(base64_encode($_serverId . ":-:" . $_typeName . ":-:" . $_amount . ":-:" . $_serial . ":-:" . $_major));
    else
        return md5(base64_encode($_serverId . ":-:" . $_typeName));
}

function MatchKey($_serial = "")
{
    global $serverVersion;
    $result = null;
    $hashblock = file_get_contents(LIVEZILLA_PATH . "_definitions/actl");
    $majors = [3, 4, 5, 6, 7, 8, 100];
    $types = [1, 2, 3, 4, 5];
    $amounts = [-1, 1, 2, 3, 5, 10];

    foreach ($majors as $major)
        foreach ($types as $type)
            foreach ($amounts as $amount) {
                $hash = hash("sha384", $major . ";" . $amount . ";" . $_serial . ";" . $type);

                if (strpos($hashblock, $hash) !== false) 
                {

                    if ($type == "5" && $major < $serverVersion) 
                    {
                        $result = null;
                    } 
                    else
                        $result = ["Major" => intval($major), "Type" => $type, "Amount" => $amount, "Serial" => $_serial];

                    break 3;
                }
            }

    return $result;
}

function GetServerHash($_serverId, $_type)
{
    return md5(base64_encode($_serverId . ":-:" . GetOptionName($_type)));
}

function GetOptionName($_key)
{
    $names = [1 => "CSP", 2 => "NGL", 3 => "NBL", 4 => "STR", 5 => "OPR"];
    return $names[$_key];
}

?>