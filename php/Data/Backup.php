<?php
/**
 * dilbo - digital logbook for Rowing and Canoeing
 * https://www.dilbo.org
 * Copyright:  2023-2025  Martin Glade
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

use Util\FileHandler;
use Util\I18n;

/**
 * class file for the specific handling of efa backup import and export.
 */
class Backup
{

     /**
     * The directory into which the uploaded zip file is unzipped.
     */
    private string $progressFile;

    /**
     * The directory into which the uploaded zip file is unzipped.
     */
    private string $logFile;

    /**
     * public Constructor.
     */
    public function __construct(String $logFile, String $progressFile)
    {
        $this->progressFile = $progressFile;
        $this->logFile = $logFile;
    }

    /**
     * Add a statement to the progress file.
     * @param string $progressStatement the statement to be added to the progress file.
     * @param bool $append if true, the statement will be appended to the existing file. If false, the statement will
     * be written to a new file.
     * @return void
     */
    public function addProgress(string $progressStatement, bool $append = true): void
    {
        file_put_contents($this->progressFile, $progressStatement, (($append) ? FILE_APPEND : null));
        file_put_contents($this->logFile,
            date("Y-m-d H:i:s") . ": progress statement " . $progressStatement . "\n", FILE_APPEND);
        usleep(10000);
    }

    /**
     * Clear the progress file and get its contents.
     * @param bool $clear if true, the file will be deleted after reading.
     * @return string the contents of the progress file.
     */
    public function getProgress(bool $clear = false): string
    {
        $progress_contents = file_get_contents($this->progressFile);
        if ($clear)
            unlink($this->progressFile);
        return ($progress_contents === false) ? "" : $progress_contents;
    }

    /**
     * Unzip the efa-backup archive into the file system and create a list of files to be imported.
     *
     * @param String $zipPath
     * *            path to the eFa-zip archive
     * @param bool $resetDb
     *             if false, only efa2logbook-files are accepted.
     */
    public function loadZip(string $zipPath, bool $resetDb): void
    {
        $i18n = I18n::getInstance();
        // unzip the archive
        $zipDirPath = mb_substr($zipPath, 0, mb_strrpos($zipPath, "."));
        $fileList = FileHandler::unzip($zipPath, true);
        $this->addProgress($i18n->t("w96wp3|Expanding %1 files from ...", count($fileList)) . "<br><br>");
        // create the import metadata
        $_SESSION["backupImport"] = [];
        $_SESSION["backupImport"]["zipDirPath"] = $zipDirPath;
        $_SESSION["backupImport"]["fileList"] = $fileList;
        $_SESSION["backupImport"]["backupFileName"] = mb_substr($zipPath,
            mb_strrpos($zipPath, DIRECTORY_SEPARATOR) + 1);
        // Convert the data files into a flat list, sort by first extension, then name
        unlink($zipPath);
        if (!is_array($fileList))
            $this->addProgress($fileList . ".<br>");
        else
            $this->addProgress($i18n->t("qqWydo|Unzipping completed.") . "<br>");
        if ($resetDb)
            $this->addProgress($i18n->t("OiGYLb|Resetting database. Plea...") . "<br>");
        file_put_contents($this->logFile, date("Y-m-d H:i:s") . ": unzipping completed.\n", FILE_APPEND);
    }

    /**
     * The backup contains three files: 'config', 'tables', and 'meta'. Two have sections: Config per config file,
     * Tables per table. This function splits the files into the respective sections and copies the configuration
     * files to their target location at "../../Config".
     */
    public function splitFilesAndCopyConfig(): void {
        $fileList = $_SESSION["backupImport"]["fileList"];
        $tablesList = [];
        foreach ($fileList as $fileName) {
            $fileContents = file_get_contents($_SESSION["backupImport"]["zipDirPath"] . "/" . $fileName);
            $isConfig = (strcasecmp($fileName, "config") == 0);
            $isTables = (strcasecmp($fileName, "tables") == 0);
            if ($isConfig || $isTables) {
                $filePartsToken = explode("\n", $fileContents, 2)[0];
                $fileParts = explode($filePartsToken, $fileContents);
                if ($isConfig) {
                    foreach ($fileParts as $filePart) {
                        if (strlen($filePart) > 5) {  // ignore empty parts like the first and last one
                            $filePartSections = explode("\n", trim($filePart), 2);
                            $success = file_put_contents("../../" . $filePartSections[0], $filePartSections[1]);
                            if (!$success)
                                file_put_contents($this->logFile, date("Y-m-d H:i:s") .
                                    ": failed to copy configuration file ../../" . $filePartSections[0] . ".\n", FILE_APPEND);
                        }
                    }
                    file_put_contents($this->logFile, date("Y-m-d H:i:s") .
                        ": copied configuration.\n", FILE_APPEND);
                } else {
                    $tablesCache = $_SESSION["backupImport"]["zipDirPath"] . "/tables";
                    if (!file_exists($tablesCache))
                        mkdir($tablesCache, 0740, true);
                    foreach ($fileParts as $filePart) {
                        if (strlen($filePart) > 5) {  // ignore empty parts like the first and last one
                            $filePartSections = explode("\n", trim($filePart), 2);
                            $success = file_put_contents("$tablesCache/${$filePartSections[0]}", $filePartSections[1]);
                            if ($success)
                                $tablesList[] = $filePartSections[0];
                            else
                                file_put_contents($this->logFile, date("Y-m-d H:i:s") .
                                    ": failed to copy table file $tablesCache/${$filePartSections[0]}.\n", FILE_APPEND);
                        }
                    }
                    file_put_contents($this->logFile, date("Y-m-d H:i:s") .
                        ": copied tables.\n", FILE_APPEND);
                }
            }
            // The meta file is ignored
        }
        $_SESSION["backupImport"]["tablesList"] = $tablesList;
    }

    /**
     * Import a table of those that have been uploaded
     * @param int $tableName the name of the table to be imported
     * @return int the count of rowa imported.
     */
    public function importTable(int $tableName): int {
        $tableCsv = file_get_contents($_SESSION["backupImport"]["zipDirPath"] . "/" . $tableName);
        $importedRowsCount = 0;
        if ($tableCsv === false)
            file_put_contents($this->logFile, date("Y-m-d H:i:s") .
                ": failed to read table file ${$_SESSION["backupImport"]["zipDirPath"]}/$tableName.\n", FILE_APPEND);
        else if (strlen($tableCsv) < 1)
            file_put_contents($this->logFile, date("Y-m-d H:i:s") .
                ": empty table file ${$_SESSION["backupImport"]["zipDirPath"]}/$tableName.\n", FILE_APPEND);
        else {
            $dbc = DatabaseConnector::getInstance();
            $importedRowsCount = $dbc->importCsv($tableName, $tableCsv);
            if ($importedRowsCount > 0)
                file_put_contents($this->logFile, date("Y-m-d H:i:s") .
                    ": imported table $tableName with $importedRowsCount rows.\n",  FILE_APPEND);
            elseif ($importedRowsCount < 0)
                file_put_contents($this->logFile, date("Y-m-d H:i:s") .
                    ": $tableName not imported, not empty.\n",  FILE_APPEND);
            else
                file_put_contents($this->logFile, date("Y-m-d H:i:s") .
                    ": $tableName has no rows to import.\n",  FILE_APPEND);
        }
        return $importedRowsCount;
    }

    /**
     * Create a backup zip archive and store it as zip archive.
     * @param String $metaFileHeader the header to be included in the neta file
     * @return array the list of backup file names
     */
    function create(String $metaFileHeader): array {
        $dbc = DatabaseConnector::getInstance();
        $tableNames = $dbc->tableNames();
        $filePartsToken = Ids::generateUid(24);
        $metaFileContents = $metaFileHeader . "\n";

        $tablesFileContents = $filePartsToken . "\n";
        foreach ($tableNames as $tableName) {
            $tablesFileContents .= $tableName . "\n" . $dbc->exportCsv($tableName) . "\n$filePartsToken\n";
            $tableRows = $dbc->countRecords($tableName);
            $metaFileContents .= "$tableName: $tableRows records.\n";
        }

        $settingsFileContents = $filePartsToken . "\n";
        $settingsDirs = scandir("../../Config");
        if ($settingsDirs !== false) {
            foreach ($settingsDirs as $settingsDir) {
                if (($settingsDir !== ".") && ($settingsDir !== "..")) {
                    $settingsFiles = scandir("../../Config/$settingsDir");
                    foreach ($settingsFiles as $settingsFile) {
                        if (!is_dir("../../Config/$settingsDir/$settingsFile")) {
                            $settingsFileThis = file_get_contents("../../Config/$settingsDir/$settingsFile");
                            if ($settingsFileThis !== false) {
                                $settingsFileContents .= "$settingsDir/$settingsFile\n" . $settingsFileThis . "\n$filePartsToken\n";
                                $settingsBytes = strlen($settingsFileThis);
                                $metaFileContents .= "$settingsDir/$settingsFile: $settingsBytes bytes.\n";
                            }
                        }
                    }
                }
            }
        }
        file_put_contents("tables", $tablesFileContents);
        file_put_contents("config", $settingsFileContents);
        file_put_contents("meta", $metaFileContents);
        return [ "tables", "config", "meta" ];
    }
}
