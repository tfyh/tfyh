<?php

namespace tfyh\util;

use JetBrains\PhpStorm\NoReturn;
use ZipArchive;

use tfyh\control\Runner;

/**
 * FileHandler is a static class providing file handling functions.
 */
class FileHandler
{
    /**
     * Parses a file system tree and returns all relative path names of files recursively.
     *
     * @param array $filePaths Array to collect file paths.
     * @param String $branchRootDir Root directory of the file system branch.
     * @param String $parentDir Parent directory in the current recursion level.
     * @return array Array of collected file paths.
     */
    private static function listFilesOfTree (array $filePaths, String $branchRootDir, String $parentDir): array
    {
        $handle = opendir($branchRootDir . $parentDir);
        if ($handle !== false) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != "..") {
                    // add relative path
                    $filePaths[] = $parentDir . $entry;
                    // drill down if this is a directory
                    if (is_dir($branchRootDir . $parentDir . $entry)) {
                        $filePaths = self::listFilesOfTree($filePaths, $branchRootDir,
                            $parentDir . $entry . "/");
                    }
                }
            }
            closedir($handle);
            return $filePaths;
        }
        return [];
    }

    /**
     * Parse a file system branch and return all relative path names of files
     */
    public static function listFilesOfBranch (String $branchRootDir): array
    {
        $filePaths = [];
        return self::listFilesOfTree($filePaths, $branchRootDir, "");
    }

    /**
     * Recursively remove all files of a directory including all subdirectories
     * See
     * https://stackoverflow.com/questions/3338123/how-do-i-recursively-delete-a-directory-and-its-entire-contents-files-sub-dir
     * @param String $dir the directory to be removed
     * @param bool $echo whether to echo the progress of the removal.
     * @return bool true, if the removal was successful.
     */
    public static function rrmdir (String $dir, bool $echo = false): bool
    {
        $success = false;
        if (is_dir($dir)) {
            $success = true;
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . DIRECTORY_SEPARATOR . $object) && ! is_link($dir . "/" . $object)) {
                        if ($echo)
                            echo "drill down into " . $dir . DIRECTORY_SEPARATOR . $object . "<br>";
                        $success &= self::rrmdir($dir . DIRECTORY_SEPARATOR . $object);
                    } else {
                        if ($echo)
                            echo "unlinking " . $dir . DIRECTORY_SEPARATOR . $object . "<br>";
                        $success &= unlink($dir . DIRECTORY_SEPARATOR . $object);
                    }
                    if (! $success && $echo)
                        echo "failed.<br>";
                }
            }
            $success &= rmdir($dir);
            if (! $success && $echo)
                echo "failed to remove $dir.<br>";
        }
        return $success;
    }

    /**
     * Unzip an archive. See https://www.php.net/manual/de/ref.zip.php
     * @param String $zipPath path to the zip archive
     * @param bool $rmdir whether to remove the target directory before unzipping.
     * @return array|string the list of files in the archive or an error message.
     */
    public static function unzip (String $zipPath, bool $rmdir = false): array|string
    {
        $i18n = I18n::getInstance();
        $dirPath = substr($zipPath, 0, strrpos($zipPath, "."));
        if (! file_exists($zipPath))
            return $i18n->t("XFt9AM|#Error: Zip path °%1° do...", $zipPath);

        if (file_exists($dirPath)) {
            if ($rmdir) {
                $removed = self::rrmdir($dirPath);
                if (! $removed)
                    return $i18n->t("5qjixH|#Error: Target directory...", $dirPath);
            } else
                return $i18n->t("5qjixH|#Error: Target directory...", $dirPath);
        }
        mkdir($dirPath);

        $zip = new ZipArchive();
        $resource = $zip->open($zipPath);
        if (! $resource || is_numeric($resource))
            return $i18n->t("6qoA8U|#Error while opening the...", $zipPath);

        $zip->extractTo($dirPath);
        $fileList = [];
        for ($i = 0; $i < $zip->numFiles; $i++)
            $fileList[] = $zip->getNameIndex($i);
        $zip->close();
        return $fileList;
    }

    /**
     * Store a set of files into a given archive.
     * @param array $srcFilePaths the paths of the files to be zipped.
     * @param String $zipFilepath the path of the zip archive.
     * @return void
     */
    public static function zipFiles (array $srcFilePaths, String $zipFilepath): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipFilepath, ZipArchive::CREATE) !== true) {
            file_put_contents($zipFilepath, "");
        }
        foreach ($srcFilePaths as $src_filepath)
            if (! is_dir($src_filepath))
                if (file_exists($src_filepath))
                    $zip->addFile($src_filepath);
        $zip->close();
    }

    /**
     * Return a file to the user. Uses the "header" function, i.e. must be called before any other output is
     * generated by the calling page. The file is kept, its content type iis decoded. On failure
     * "application/x-binary" is used.
     *
     * @param String $filepath
     *            path to file which shall be returned.
     */
    #[NoReturn] public static function returnFileToUser (String $filepath): void
    {
        // return file.
        $filename = (str_contains($filepath, "/")) ? substr($filepath, strrpos($filepath, "/") + 1) : $filepath;
        $mime_content_type = mime_content_type($filepath);
        if ($mime_content_type === false)
            $mime_content_type = "application/x-binary";
        if (file_exists($filepath)) {
            header($_SERVER["SERVER_PROTOCOL"] . " 200 OK");
            header("Cache-Control: public"); // needed for Internet Explorer
            header("Content-Type: " . $mime_content_type);
            header("Content-Transfer-Encoding: Binary");
            header("Content-Length:" . filesize($filepath));
            header("Content-Disposition: attachment; filename=" . $filename);
            readfile($filepath);
            // unlink($filepath); That results in an execution error. Remove the file in housekeeping.
            Runner::getInstance()->endScript(false);
        } else {
            die(I18n::getInstance()->t("8XihSu|Error: File °%1° not fou...", $filepath));
        }
    }

    /**
     * Return a file to the user. The file will afterward not be deleted (unlinked). Uses the "header"
     * function, i.e. must be called before any other output is generated by the calling page.
     * @param String $filepath path to file which shall be returned.
     * @return void
     */
    #[NoReturn] private static function returnZipFile (String $filepath): void
    {
        // return zip.
        if (file_exists($filepath)) {
            header($_SERVER["SERVER_PROTOCOL"] . " 200 OK");
            header("Cache-Control: public"); // needed for Internet Explorer
            header("Content-Type: application/zip");
            header("Content-Transfer-Encoding: Binary");
            header("Content-Length:" . filesize($filepath));
            header("Content-Disposition: attachment; filename=" . $filepath);
            readfile($filepath);
            // unlink($filepath); That results in an execution error. Remove the file in housekeeping.
            exit(); // really exit. No test case left over.
        } else {
            die(I18n::getInstance()->t("6HrzgB|Error: File °%1° not fou...", $filepath));
        }
    }

    /**
     * Return it files in a compressed archive to the user. Uses the "header" function, i.e. must be called
     * before any other output is generated by the calling page.
     * @param array $srcFilePaths the paths of the files to be zipped.
     * @param String $zipFilename the name of the zip archive.
     * @param bool $removeFiles whether to remove the files after returning them.
     * @return void
     */
    #[NoReturn] public static function returnFilesAsZip (array $srcFilePaths, String $zipFilename, bool $removeFiles): void
    {
        self::zipFiles($srcFilePaths, $zipFilename);
        if ($removeFiles) {
            foreach ($srcFilePaths as $src_filepath) {
                unlink($src_filepath);
            }
        }
        self::returnZipFile($zipFilename);
    }

    /**
     * Zip a csv-String and return it as a file to the user. Uses the "header" function, i.e. must be called
     * before any other output is generated by the calling page.
     */
    #[NoReturn] public static function returnStringAsZip (String $string, String $fName): void
    {
        $zipFilename = self::zip($string, $fName);
        self::returnZipFile($zipFilename);
    }

    /**
     * Store a String into the given filepath and create a zip archive at the $filepath ".zip".
     * @param String $stringToZip the String to zip
     * @param String $filename 1. the name of the file to be stored. 2. the name of the zip archive.
     * @return string the path to the zip archive.
     */
    public static function zip (String $stringToZip, String $filename): string
    {
        $zip = new ZipArchive();
        $zipFilename = $filename . ".zip";

        if ($zip->open($zipFilename, ZipArchive::CREATE) !== TRUE) {
            exit("cannot open <$zipFilename>\n"); // no i18n required
        }
        if ($zip->addFromString($filename, $stringToZip) !== true)
            exit("cannot write zip <$zipFilename>\n"); // no i18n required
        $zip->close();
        return $zipFilename;
    }

    /**
     * Generates an HTML table representation of the contents of a given directory.
     *
     * @param string $dir The directory path whose contents need to be displayed.
     * @param int $level_of_top The maximum levels above the current directory to allow navigation, with a default of 1.
     * @return string Returns an HTML string representing the directory's contents, including files and subdirectories.
     */
    public static function getDirContents (String $dir, int $level_of_top = 1): string
    {
        $i18n = I18n::getInstance();
        $result = "<table>";
        $result .= "<tr class=flist><td>&nbsp;</td><td>" . $dir . "</td><td>" . $i18n->t("F7t6Jn|Action") .
            "</td></tr>";
        $items = 0;
        $cdir = scandir($dir);
        if ($cdir)
            foreach ($cdir as $value) {
                if (! in_array($value, array(".",".."
                ))) {
                    if (is_dir($dir . DIRECTORY_SEPARATOR . $value)) {
                        $result .= "<tr class=flist><td><img alt='folder' src='../../tfyh/resources/drive_folder-20px.png' title='" .
                            $i18n->t("hX0dDX|Directory") . "' /></td>" . "<td><a href='?cdir=" . $dir . "/" .
                            $value . "'>" . $value . "</a>&nbsp;&nbsp;&nbsp;&nbsp;</td>" .
                            "<td><a href='?xdir=" . $dir . "/" . $value .
                            "'><img alt='delete' src='../../tfyh/resources/delete_file-20px.png' title='" .
                            $i18n->t("MyjroH|delete directory, if emp...") . "' /></a>" . "</td></tr>\n";
                    } else {
                        $result .= "<tr class=flist><td><img alt='file' src='../../tfyh/resources/drive_file-20px.png' title='" .
                            $i18n->t("wdLerX|File") . "' /></td>" . "<td>" . $value .
                            "&nbsp;&nbsp;&nbsp;&nbsp;</td><td><a href='?dfile=" . $dir . "/" . $value .
                            "'><img alt='download' src='../../tfyh/resources/download_file-20px.png' title='" .
                            $i18n->t("cmjKme|Download file") . "' /></a>" . "<a href='?xfile=" . $dir . "/" .
                            $value . "'><img alt='delete' src='../../tfyh/resources/delete_file-20px.png' title='" .
                            $i18n->t("c32XmM|Delete file") . "' /></a>" . "</td></tr>\n";
                    }
                    $items ++;
                }
            }
        if ($items == 0)
            $result .= "<tr class=flist><td>" . $i18n->t("HpsAcF|(empty)") . "</td><td>" .
                $i18n->t("26WMwL|no content found.") . "</td></tr>";
        $parentDir = (strrpos($dir, "/") > 0) ? substr($dir, 0, strrpos($dir, "/")) : $dir;
        // the topmost offered parent directory is the "uploads" folder to ensure
        // entry into the application files hierarchy is not possible.
        if (count(explode("/", $parentDir)) > $level_of_top)
            $result .= "<tr class=flist><td><img alt='file' src='../../tfyh/resources/drive_file-20px.png' title='" .
                $i18n->t("CBxZVW|One level higher") . "' /></td><td><a href='?cdir=" . $parentDir . "'>" .
                $parentDir . "</a></td></tr>";
        $result .= "</table>";

        return $result;
    }

}