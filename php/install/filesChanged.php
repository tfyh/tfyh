<!DOCTYPE html>
<html lang='en'>
<head>
    <title>tools for your hobby web space profiler</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<?php
$modifiedResult = "<tr><th>path</th><th>permissions</th><th>modified</th><th>accessed</th><th>inodeChange</th><th>size</tr>\n";
$accessedResult = "<tr><th>path</th><th>permissions</th><th>modified</th><th>accessed</th><th>inodeChange</th><th>size</tr>\n";
$scannedFiles = 0;
$modifiedFiles = 0;
$accessedFiles = 0;
$now = time();
$checkPeriodAccessDays = (isset($_GET["accessed"])) ? intval($_GET["accessed"]) : 3;
$checkPeriodModifiedDays = (isset($_GET["modified"])) ? intval($_GET["modified"]) : 10;
$checkPeriodAccess = $checkPeriodAccessDays * 86400; // 3 days
$checkPeriodModified = $checkPeriodModifiedDays * 86400; // 10 days
scanDirectory("../..");
echo "<h3>Webspace access and modification review</h3>\n";
echo "<h4>System profile</h4>\n<p>";
echo "PHP_SELF: " . $_SERVER['PHP_SELF'];
echo "<br>SERVER_NAME: " . $_SERVER['SERVER_NAME'];
echo "<br>HTTP_HOST: " . $_SERVER['HTTP_HOST'];
echo "<br>HTTP_USER_AGENT: " . $_SERVER['HTTP_USER_AGENT'];
echo "<br>SCRIPT_NAME: " . $_SERVER['SCRIPT_NAME'] . "</p>\n";
echo "<p>PHP-Version: " . phpversion() . "</p>\n";
$scanRoot = $_SERVER['SCRIPT_NAME'];
$scanRoot = mb_substr($scanRoot, 0, mb_strrpos($scanRoot, '/'));
$scanRoot = mb_substr($scanRoot, 0, mb_strrpos($scanRoot, '/'));
$scanRoot = "/" . mb_substr($scanRoot, 0, mb_strrpos($scanRoot, '/'));
$webSpaceDir = getcwd();
$webSpaceDir = mb_substr($webSpaceDir, 0, mb_strrpos($webSpaceDir, '/'));
$webSpaceDir = mb_substr($webSpaceDir, 0, mb_strrpos($webSpaceDir, '/'));
echo "<h4>Scan summary</h4>\n<p>";
echo "<p>Scan root within web space: $scanRoot\n";
echo "<br>web space directory at host: $webSpaceDir</p>\n";

echo "<p>depending on your web server settings the access-time may provide a file creation information rather than a last access timestamp. In such cases your list of further accesses will be empty.</p>\n";
echo "<p>Scanned $scannedFiles files within the web space<br>";
echo " - Modifications in last $checkPeriodModifiedDays days for $modifiedFiles files<br>";
echo " - Further accesses in last $checkPeriodAccessDays days for $accessedFiles files<br>";
echo "<h3>Scan details</h3>\n";
echo "<h4>Files modified in the last $checkPeriodModifiedDays days</h4>\n";
echo "<table style='font-family: \"Lucida Console\", \"Courier New\", monospace; border-spacing: 0.5em;'>\n$modifiedResult\n</table>";
echo "<h4>Further accesses in the last $checkPeriodAccessDays days</h4>\n";
echo "<table style='font-family: \"Lucida Console\", \"Courier New\", monospace; border-spacing: 0.5em;'>\n$accessedResult\n</table>";

/**
 * Set the access rights for all directories at the top level and one level below and put or remove a .htaccess file
 * accordingly. Directories '.' and '..' will not be touched. A directory may be accessed if its file name starts
 * with a lower case letter. It must be forbidden if the first letter is the upper case.
 * @param String $dir the directory to check.
 * @return void
 */
function scanDirectory(String $dir): void
{
    global $now, $checkPeriodAccess, $checkPeriodModified, $scannedFiles,
           $modifiedFiles, $accessedFiles, $modifiedResult, $accessedResult;
    $dirFiles = scandir($dir);
    foreach ($dirFiles as $dirFile) {
        $isDotDir = ($dirFile == "." || $dirFile == "..");
        if (is_dir("$dir/$dirFile") && !$isDotDir)
        {
            scanDirectory("$dir/$dirFile", $accessedResult, $modifiedResult);
        } else if (!$isDotDir) {
            $fName = "$dir/$dirFile";
            $scannedFiles++;
            $accessed = fileatime($fName);
            $modified = filemtime($fName);
            if (($modified !== false) || ($accessed !== false)) {
                $wasModified = (($now - $modified) < $checkPeriodModified);
                $wasAccessed = !$wasModified && (($now - $accessed) < $checkPeriodAccess);
                if ($wasModified || $wasAccessed) {
                    $permissions = fileperms($fName);
                    $pString = toStringPermissions($permissions);
                    $mString = toStringTime($modified);
                    $aString = toStringTime($accessed);
                    $inodeChange = filectime($fName);
                    $iString = toStringTime($inodeChange);
                    $size = filesize($fName);
                    $sString = toString($size);
                    $result = "<tr><td>" . encodeCsvEntry(substr($fName, 6)) . "</td><td>" . $pString . "</td><td>".
                        $mString . "</td><td>".  $aString . "</td><td>".  $iString . "</td><td>". $sString . "</td></tr>\n";
                    if ($wasAccessed) {
                        $accessedFiles++;
                        $accessedResult .= $result;
                    } else {
                        $modifiedFiles++;
                        $modifiedResult .= $result;
                    }
                }
            }
        }
    }
}

function toString($val) : String { if ($val === false) return "-"; else return "$val"; }
function toStringTime($val) : String { if ($val === false) return "-"; else return date("Y-m-d H:i:s", $val); }

function toStringPermissions(int $perms): string
{
    $info = match ($perms & 0xF000) {
        0xC000 => 's', 0xA000 => 'l', 0x8000 => 'r', 0x6000 => 'b', 0x4000 => 'd', 0x2000 => 'c', 0x1000 => 'p', default => 'u',
    };
    // owner
    $info .= (($perms & 0x0100) ? 'r' : '-');
    $info .= (($perms & 0x0080) ? 'w' : '-');
    $info .= (($perms & 0x0040) ? (($perms & 0x0800) ? 's' : 'x') : (($perms & 0x0800) ? 'S' : '-'));
    // group
    $info .= (($perms & 0x0020) ? 'r' : '-');
    $info .= (($perms & 0x0010) ? 'w' : '-');
    $info .= (($perms & 0x0008) ? (($perms & 0x0400) ? 's' : 'x') : (($perms & 0x0400) ? 'S' : '-'));
    // other
    $info .= (($perms & 0x0004) ? 'r' : '-');
    $info .= (($perms & 0x0002) ? 'w' : '-');
    $info .= (($perms & 0x0001) ? (($perms & 0x0200) ? 't' : 'x') : (($perms & 0x0200) ? 'T' : '-'));
    return $info;
}

/**
 * Encode a csv entry. Uses ";" as a separator and double quotes (")
 * @param String|null $entry the entry to be encoded.
 * @return string the encoded entry.
 */
function encodeCsvEntry (String|null $entry): string
{
    if (is_null($entry) || strlen($entry) == 0)
        return "";
    if (str_contains($entry, "\n") || str_contains($entry, ";") ||
        str_contains($entry, "\""))
        return "\"" . str_replace("\"", "\"\"", $entry) . "\"";
    return $entry;
}


