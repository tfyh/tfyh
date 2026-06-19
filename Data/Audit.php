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

namespace tfyh\data;

use tfyh\control\Users;
include_once "../_Control/Users.php";

/**
 * Audit the application for security and housekeeping purposes
 */
class Audit
{

    private string $auditLog;

    private string $auditWarnings;

    /**
     * Public Constructor. Constructing the Audit class will rn all standard audit tasks
     */
    public function __construct() {}

    /**
     * Scan the top level path and set the forbidden directories to be those which are not
     * configured to be forbidden.
     * @param bool $getPublicInstead If true, the public directories will be returned instead of the forbidden ones.
     * @return array of forbidden directories.
     */
    private function get_forbidden_dirs(bool $getPublicInstead = false): array
    {
        $forbiddenDirs = [];
        $publicDirs = [];
        $topLevelDirs = scandir("..");
        foreach ($topLevelDirs as $topLevelDir) {
            // skip "." and ".", and all unix hidden directories such as ".idea" ore ".git-ignore"
            if (!str_starts_with($topLevelDir, ".")) {
                $isForbidden = (strcmp(strtolower($topLevelDir), $topLevelDir) != 0);
                if ($isForbidden)
                    $forbiddenDirs[] = $topLevelDir;
                else
                    $publicDirs[] = $topLevelDir;
            }
        }
        return ($getPublicInstead) ? $publicDirs : $forbiddenDirs;
    }

    /**
     * Scan the top level path and get the directories which are not forbidden except "." and "..".
     */
    private function get_public_dirs(): array
    {
        return $this->get_forbidden_dirs(true);
    }

    /**
     * Set the access rights for all top level directories and put or remove a .htaccess file
     * accordingly. Directories '.' and '..' will not be touched.
     */
    public function set_dirs_access_rights(): void
    {
        $forbiddenDirs = $this->get_forbidden_dirs();
        $publicDirs = $this->get_forbidden_dirs(true);
        foreach ($forbiddenDirs as $topLevelDir) {
            chmod("../$topLevelDir", 0700);
            file_put_contents("../$topLevelDir/.htaccess", "deny for all");
        }
        foreach ($publicDirs as $topLevelDir) {
            chmod("../$topLevelDir", 0755);
            $contained = false;
            if (is_dir("../$topLevelDir"))
                $contained = scandir("../$topLevelDir");
            if ($contained !== false)
                foreach ($contained as $file)
                    chmod("../$topLevelDir/$file", 0755);
            if (file_exists("../$topLevelDir/.htaccess"))
                unlink("../$topLevelDir/.htaccess");
        }
    }

    /**
     * Check the access rights for all top level directories and write the result to the audit log
     * and audit warnings. Directories '.' and '..' will not be checked.
     */
    private function check_dirs_access_rights(): int
    {
        $corrections_needed = 0;
        $forbidden_dirs = $this->get_forbidden_dirs();
        $this->auditLog .= "Forbidden directories access check\n";
        foreach ($forbidden_dirs as $forbidden_dir) {
            if ((strcmp($forbidden_dir, ".") != 0) && (strcmp($forbidden_dir, "..") != 0)) {
                $forbidden_dir = trim($forbidden_dir); // line breaks in settings_tfyh may cause
                // blank
                // insertion
                $is_valid_dir = (strlen($forbidden_dir) > 0) && file_exists("../" . $forbidden_dir);
                $is_unprotected_dir = (fileperms("../" . $forbidden_dir) != 0700);
                if ($is_valid_dir && $is_unprotected_dir) {
                    $this->auditLog .= "    file permissions for " . $forbidden_dir . ": " .
                        self::permissions_string(fileperms("../" . $forbidden_dir)) . ".\n";
                    $corrections_needed++;
                }
                $htaccess_filename = "../" . $forbidden_dir . "/.htaccess";
                if ($is_valid_dir && !file_exists($htaccess_filename)) {
                    $corrections_needed++;
                    $this->auditWarnings .= "    " .
                        "Missing .htaccess file." . "\n";
                }
            }
        }
        // Open access to publicly available directories
        $this->auditLog .= "Publicly available directories\n";
        $public_dirs = $this->get_public_dirs();
        foreach ($public_dirs as $public_dir) {
            if ((strcmp($public_dir, ".") != 0) && (strcmp($public_dir, "..") != 0)) {
                if ((fileperms("../" . $public_dir) % 0755) != 0) {
                    $this->auditLog .= "    file permissions for " . $public_dir .
                        ": " . self::permissions_string(fileperms("../" . $public_dir)) . ".\n";
                    $corrections_needed++;
                }
                $htaccess_filename = "../" . $public_dir . "/.htaccess";
                if (file_exists($htaccess_filename)) {
                    $corrections_needed++;
                    $this->auditWarnings .= "    Extra .htaccess file removed.\n";
                }
            }
        }
        return $corrections_needed;
    }

    /**
     * Execute the full audit and log the result to "../Log/audit.log"
     */
    public function run_audit(): void
    {
        $config = Config::getInstance();
        // Header
        $actual_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") .
            "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $this->auditLog = date("Y-m-d H:i:s") . ": Starting audit '" .
            $config->appName . "' at '" . $actual_link . "', version '" . $config->appVersion . "'\n";

        // Check web server directory access settings
        $this->auditLog .= "Starting audit at: " . date("Y-m-d H:i:s") . "\n";
        $this->auditWarnings = "";

        // Check access to forbidden directories
        $this->auditLog .= "Directory access right check ...\n";
        $corrections_needed = $this->check_dirs_access_rights();
        if ($corrections_needed > 0)
            $this->set_dirs_access_rights();

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

        file_put_contents("../Log/app_audit.log", $this->auditLog);
        if (strlen($this->auditWarnings) > 0)
            file_put_contents("../Log/audit.warnings", $this->auditWarnings);
        elseif (file_exists("../Log/audit.warnings"))
            unlink("../Log/audit.warnings");
    }

    /**
     * Provide a readable String for the file permissions, see:
     * https://www.php.net/manual/de/function.fileperms.php
     *
     * @param int $perms permissions as integer
     * @return string permissions as string
     */
    public static function permissions_string(int $perms): string
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
