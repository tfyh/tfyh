<?php


namespace tfyh\util;

use tfyh\control\Users;
include_once "../_Control/Users.php";

use tfyh\data\DatabaseConnector;
use tfyh\data\Formatter;
use tfyh\data\ParserName;
include_once "../_Data/DatabaseConnector.php";
include_once "../_Data/Formatter.php";
include_once "../_Data/ParserName.php";

/**
 * Container to hold the audit class. Shall be run by the cron jobs.
 */
class AppStatistics
{

    /**
     * The pivoted array of timestamps
     */
    public array $timestampsPivot;

    /**
     * last timestamp er client
     */
    public array $timestampsLast;

    /**
     * last timestamp er client
     */
    public array $timestampsCount;

    private String $strBuilder;

    /**
     * empty Constructor.
     */
    public function __construct ()
    {}

    /**
     * Create a html readable summary of the application status to send it per mail to admins.
     */
    public function createAppStatusSummary (): string
    {
        $dbc = DatabaseConnector::getInstance();
        $i18n = I18n::getInstance();
        // check table sizes
        $html = "<h4>" . $i18n->t("bJ44LM|Tables and records") . "</h4>\n";
        $html .= "<table><tr><th>" . $i18n->t("WWOePq|Table") . "</th><th>" . $i18n->t("MzZXhV|Count of records") .
                 "</th></tr>\n";
        $table_names = $dbc->tableNames();
        $total_record_count = 0;
        foreach ($table_names as $tn) {
            $record_count = $dbc->countRecords($tn);
            $html .= "<tr><td>" . $tn . "</td><td>" . $record_count . "</td></tr>\n";
            $total_record_count += $record_count;
        }
        $html .= "<tr><td>" . $i18n->t("wNrHCN|Total") . "</td><td>" . $total_record_count . "</td></tr></table>\n";
        
        // Check users and access rights
        $html .= Users::getInstance()->getAllAccesses();
        
        // Check accesses logged.
        $days_to_log = 14;
        $html .= "<h4>" . $i18n->t("UU8ZrV|Accesses last %1 days", strval($days_to_log)) . "</h4>\n";
        file_put_contents("../Log/server_statistics.csv",
                $this->pivotTimestamps(86400, $days_to_log));
        $html .= "<table><tr><th>" . $i18n->t("zOZlkQ|User number") . "</th><th>" . $i18n->t("aCa6RR|User name") .
                 "</th><th>" . $i18n->t("8N2N5h|Count of accesses") . "</th></tr>\n";
        $timestamps_count_all = 0;
        foreach ($this->timestampsCount as $clientID => $timestamps_count) {
            $user = (intval($clientID) === - 1) ? $i18n->t("8FHeg0|anonymous") : ((intval($clientID) === 0) ? $i18n->t(
                    "phvRfA|undefined") : "User");
            $html .= "<tr><td>" . $clientID . "</td><td>" . $user . "</td><td>" . $timestamps_count .
                     "</td></tr>\n";
            $timestamps_count_all += $timestamps_count;
        }
        $html .= "<tr><td>" . $i18n->t("nDBbCc|Total") . "</td><td></td><td>" . $timestamps_count_all .
                 "</td></tr></table>\n";
        
        return $html;
    }

    /**
     * Pivot the timestamps according to the pivoting period.
     */
    public function pivotTimestamps (int $period, int $count): string
    {
        $timestampsFile = file_get_contents("../Log/sys_timestamps.log");
        $timestampsFileWithoutHeader = explode("\n", $timestampsFile, 2)[1];
        $timestampsPreviousFile = file_get_contents("../Log/sys_timestamps.log.previous");
        $timestampsAll = $timestampsPreviousFile . "\n" . $timestampsFileWithoutHeader;
        $timestampsLines = explode("\n", $timestampsAll);
        $timestampsPivot = [];
        $this->timestampsLast = [];
        $this->timestampsCount = [];
        // end the monitoring interval at the next full hour.
        $periodsEndAt = strtotime(date("Y-m-d H") . ":00:00") + 3600;
        // and start it according to the period length and count requested.
        $periodsStartAt = $periodsEndAt - $count * $period;
        // Read timestamps file
        for ($l = 1; $l < count($timestampsLines); $l ++) {
            // skip first line (header)
            $tsParts = explode(";", trim($timestampsLines[$l]));
            if (count($tsParts) >= 4) {
                $tsTime = intval($tsParts[0]);
                $tsPeriodIndex = intval(($tsTime - $periodsStartAt) / $period);
                if (($tsPeriodIndex >= 0) && ($tsPeriodIndex < $count)) {
                    $tsUser = intval($tsParts[1]);
                    if (! isset($timestampsPivot[$tsUser]))
                        $timestampsPivot[$tsUser] = [];
                    if (! isset($this->timestampsLast[$tsUser]))
                        $this->timestampsLast[$tsUser] = 0;
                    if (! isset($this->timestampsCount[$tsUser]))
                        $this->timestampsCount[$tsUser] = 0;
                    $tsPeriodStart = $periodsStartAt + $tsPeriodIndex * $period;
                    // an api container may contain more than one transaction.
                    $tsAccesses = explode(",", $tsParts[2]);
                    // use the average duration per transaction within the container for monitoring
                    $tsDuration = $tsParts[3] / count($tsAccesses);
                    // pivot numbers
                    foreach ($tsAccesses as $tsAccess) {
                        $this->timestampsCount[$tsUser] ++;
                        if ($tsTime > $this->timestampsLast[$tsUser])
                            $this->timestampsLast[$tsUser] = $tsTime;
                        $tsAccessGroup = explode("/", $tsAccess)[0];
                        $tsAccessType = explode("/", $tsAccess)[1];
                        // initialize pivot table structure
                        if (! isset($timestampsPivot[$tsUser][$tsAccessGroup])) {
                            $timestampsPivot[$tsUser][$tsAccessGroup] = [];
                        }
                        if (! isset($timestampsPivot[$tsUser][$tsAccessGroup][$tsAccessType])) {
                            $timestampsPivot[$tsUser][$tsAccessGroup][$tsAccessType] = [];
                            for ($i = 0; $i < $count; $i ++) {
                                $period_index = $periodsStartAt + $period * $i;
                                $timestampsPivot[$tsUser][$tsAccessGroup][$tsAccessType][$period_index] = [];
                                $timestampsPivot[$tsUser][$tsAccessGroup][$tsAccessType][$period_index]["sum"] = 0.0;
                                $timestampsPivot[$tsUser][$tsAccessGroup][$tsAccessType][$period_index]["max"] = 0.0;
                                $timestampsPivot[$tsUser][$tsAccessGroup][$tsAccessType][$period_index]["count"] = 0;
                            }
                        }
                        // add timestamp
                        $timestampsPivot[$tsUser][$tsAccessGroup][$tsAccessType][$tsPeriodStart]["sum"] += $tsDuration;
                        if ($tsDuration >
                                 $timestampsPivot[$tsUser][$tsAccessGroup][$tsAccessType][$tsPeriodStart]["max"])
                            $timestampsPivot[$tsUser][$tsAccessGroup][$tsAccessType][$tsPeriodStart]["max"] = $tsDuration;
                        $timestampsPivot[$tsUser][$tsAccessGroup][$tsAccessType][$tsPeriodStart]["count"] ++;
                    }
                }
            }
        }
        $this->timestampsPivot = $timestampsPivot;
        
        // format pivot
        $pivotLinear = "Group;Type;Period;Count;Sum (ms);Average (ms)\n";
        foreach ($timestampsPivot as $pivotUser)
            foreach ($pivotUser as $tsAccessGroup => $pivotAccessGroup)
                foreach ($pivotAccessGroup as $tsAccessType => $pivotAccessType)
                    foreach ($pivotAccessType as $tsAccessPeriod => $pivotAccessPeriod) {
                        $pivotLinear .= $tsAccessGroup . ";" . $tsAccessType . ";" .
                                 date("Y-m-d H:i:s", $tsAccessPeriod) . ";" . $pivotAccessPeriod["count"] .
                                 ";" . intval($pivotAccessPeriod["sum"] * 1000) . ";";
                        if ($pivotAccessPeriod["count"] > 0)
                            $pivotLinear .= substr(
                                strval(intval(1000 * $pivotAccessPeriod["sum"] / $pivotAccessPeriod["count"])),
                                0, 6);
                        else
                            $pivotLinear .= "0";
                        $pivotLinear .= "\n";
                    }

        return $pivotLinear;
    }

    public function pivotUserTimestampsHtml (int $user_id): string
    {
        $i18n = I18n::getInstance();
        $indent = ["access_type" => "<tr><td></td>","period_start" => "<tr><td></td><td></td>"
        ];
        $timestampsHtmlTable = "<table><th>" . $i18n->t("5yN29k|Type") . "</th><th>" . $i18n->t("kgufgC|Function") .
                 "</th><th>" . $i18n->t("eCGctK|Date") . "</th><th>" . $i18n->t("Jl8rPJ|Number") . "</th><th>" .
                 $i18n->t("xf8yxL|Average") . "</th><th>" . $i18n->t("7e8XGU|Maximum") . "</th></tr>\n";
        $tsPivot = $this->timestampsPivot[$user_id];
        foreach ($tsPivot as $tsAccessGroup => $tsAccessGroupPivot) {
            $timestampsHtmlTable .= "<tr><td>$tsAccessGroup</td>";
            $firstLineGroup = true;
            foreach ($tsAccessGroupPivot as $tsAccessType => $tsAccessTypePivot) {
                $timestampsHtmlTable .= (($firstLineGroup) ? "<td>$tsAccessType</td>" : $indent["access_type"] .
                         "<td>$tsAccessType</td>");
                $firstLineGroup = false;
                $firstLineType = true;
                foreach ($tsAccessTypePivot as $tsPeriodStart => $tsAccessKpis) {
                    if ($tsAccessKpis["count"] > 0) {
                        $value = Formatter::format($tsPeriodStart, ParserName::DATE);
                        $timestampsHtmlTable .= (($firstLineType) ? "<td>$value</td>" : $indent["period_start"] .
                                 "<td>$value</td>");
                        $firstLineType = false;
                        for ($i = 0; $i < 3; $i ++) {
                            if ($i == 0)
                                $value = intval(100 * $tsAccessKpis["count"]) / 100;
                            elseif ($i == 1)
                                $value = intval(100 * $tsAccessKpis["sum"] / $tsAccessKpis["count"]) / 100;
                            elseif ($i == 2)
                                $value = ($tsAccessKpis["max"] == 0) ? "-" : intval(
                                        100 * $tsAccessKpis["max"]) / 100;
                            $timestampsHtmlTable .= "<td>$value</td>";
                        }
                        $timestampsHtmlTable .= "</tr>\n";
                    }
                }
            }
        }
        return $timestampsHtmlTable . "</table>";
    }

    /**
     * Recursive html display of an array using a pivot table type.
     */
    public function displayArrayAsTable (array $a, int $level = 0): string
    {
        if ($level == 0)
            $this->strBuilder = "<table>";
        $i = 0;
        foreach ($a as $key => $value) {
            $prefix = "<tr><td>";
            $prefix .= str_repeat("</td><td>", $level);
            $this->strBuilder .= ($i == 0) ? (($level == 0) ? "<tr><td>" : "</td><td>") : $prefix;
            if (is_array($value)) {
                $this->strBuilder .= $key . "\n";
                $this->displayArrayAsTable($value, $level + 1);
            } elseif (is_object($value))
                $this->strBuilder .= "$key : [object]";
            else
                $this->strBuilder .= "$key : $value";
            $this->strBuilder .= "</td></tr>\n";
            $i ++;
        }
        if ($level == 0)
            $this->strBuilder .= "</table>\n";
        return $this->strBuilder;
    }
}
