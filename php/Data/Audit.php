<?php
/**
 * tools-for-your-hobby
 * https://www.tfyh.org
 * Copyright  2023-2025  Martin Glade
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not use this file except
 * in compliance with the License. You may obtain a copy of the License at
 * http://www.apache.org/licenses/LICENSE-2.0
 * Unless required by applicable law or agreed to in writing, software distributed under the License
 * is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
 * or implied. See the License for the specific language governing permissions and limitations under
 * the License.
 */

namespace Data;

use Control\Users;

/**
 * Audit the application for security and housekeeping purposes
 */
class Audit
{

    private string $auditLog;

    /**
     * Public Constructor. Constructing the Audit class will rn all standard audit tasks
     */
    public function __construct() {}

    /**
     * Set the access rights for all directories at the top level and one level below and put or remove a .htaccess file
     * accordingly. Directories '.' and '..' will not be touched. A directory may be accessed if its file name starts
     * with a lower case letter. It must be forbidden if the first letter is the upper case.
     * @param String $dir the directory to check.
     * @param int $level the level of recursion.
     * @param bool $reportStatus if true, the status of the audit is reported to the audit log.
     * @return void
     */
    public function setDirectoriesAccessRights(String $dir, int $level, bool $reportStatus): void
    {
        if ($level > 1)
            return;
        $dirFiles = scandir($dir);
        foreach ($dirFiles as $dirFile) {
            if ((is_dir("$dir/$dirFile"))
                && (strcmp($dirFile, ".") != 0)
                && (strcmp($dirFile, "..") != 0))
            {
                $firstLetter = substr($dirFile, 0, 1);
                $forbid = strcmp($firstLetter, strtoupper($firstLetter) == 0);
                $isProtected = (fileperms("$dir/$dirFile") == 0700) || file_exists("$dir/$dirFile/.htaccess");
                // Change of permission required
                if (($forbid && !$isProtected) || (!$forbid && $isProtected)) {
                    if ($reportStatus)
                        $this->auditLog .= "    file permissions for $dir/$dirFile: " .
                            self::permissionsString(fileperms("../$dir/$dirFile")) . ".\n";
                    $permissions = ($forbid) ? 0700 : 0755;
                    chmod("$dir/$dirFile", $permissions);
                    if ($forbid)
                        file_put_contents("$dir/$dirFile/.htaccess", "Require all denied");
                    else if (file_exists("$dir/$dirFile/.htaccess"))
                        unlink("$dir/$dirFile/.htaccess");
                }
                $this->setDirectoriesAccessRights("$dir/$dirFile", $level + 1, $reportStatus);
            }
        }
   }

    /**
     * Execute the full audit and log the result to "../../var/Log/audit.log"
     */
    public function runAudit(): void
    {
        $config = Config::getInstance();
        // Header
        $actual_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") .
            "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $this->auditLog = date("Y-m-d H:i:s") . ": Starting audit '" .
            $config->appName . "' at '" . $actual_link . "', version '" . $config->appVersion . "'\n";

        // Check web server directory access settings
        $this->auditLog .= "Starting audit at: " . date("Y-m-d H:i:s") . "\n";
        $auditWarnings = "";

        // Check and adjust access to forbidden directories
        $this->auditLog .= "Directory access right check ...\n";
        $this->setDirectoriesAccessRights("../..", 0, true);

        // reflect settings for support cases
        $this->auditLog .= "Framework configuration ...\n";
        $frameworkItem = $config->getItem(".framework");
        foreach ($frameworkItem->getChildren() as $child) {
            $this->auditLog .= $child->name() . " (". $child->valueStr() . ")\n";
            foreach ($child->getChildren() as $grandChild)
                $this->auditLog .= $grandChild->name() . " = ". $grandChild->valueStr() . "\n";
        }

        // check table sizes
        $dbc = DatabaseConnector::getInstance();
        $this->auditLog .= "Table configuration check\n";
        $tableNames = $dbc->tableNames();
        $tableRecordCountList = "";
        $totalRecordsCount = 0;
        $totalTablesCount = 0;
        foreach ($tableNames as $tn) {
            $recordCount = $dbc->countRecords($tn);
            $columns = $dbc->columnNames($tn);
            $columnsCount = count($columns);
            $totalRecordsCount += $recordCount;
            $totalTablesCount++;
            $history = "";
            $tableRecordCountList .= "    " . $tn . " [" . $recordCount . "*" . $columnsCount .
                $history . "], \n";
        }
        $this->auditLog .= $tableRecordCountList;
        $this->auditLog .= "In total $totalRecordsCount records in $totalTablesCount tables\n";

        // Check users and access rights
        $users = Users::getInstance();
        $this->auditLog .= "Users and access rights.\n";
        $this->auditLog .= str_replace("Count of", "    Count of",
            $users->getAllAccesses(true));

        // Finish
        $this->auditLog .= "Audit completed.\n";

        file_put_contents("../../var/Log/app_audit.log", $this->auditLog);
        if (strlen($auditWarnings) > 0)
            file_put_contents("../../var/Log/audit.warnings", $auditWarnings);
        elseif (file_exists("../../var/Log/audit.warnings"))
            unlink("../../var/Log/audit.warnings");
    }

    /**
     * Provide a readable String for the file permissions, see:
     * https://www.php.net/manual/de/function.fileperms.php
     *
     * @param int $perms permissions as integer
     * @return string permissions as string
     */
    public static function permissionsString(int $perms): string
    {
        $info = match ($perms & 0xF000) {
            0xC000 => 's',
            0xA000 => 'l',
            0x8000 => 'r',
            0x6000 => 'b',
            0x4000 => 'd',
            0x2000 => 'c',
            0x1000 => 'p',
            default => 'u',
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
}
