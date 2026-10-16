/**
 * tools-for-your-hobby
 * https://www.tfyh.org
 * Copyright  2023-2025  Martin Glade
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not use this file except
 * in compliance with the License. You may obtain a copy of the License at
 * https://www.apache.org/licenses/LICENSE-2.0
 * Unless required by applicable law or agreed to in writing, software distributed under the License
 * is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
 * or implied. See the License for the specific language governing permissions and limitations under
 * the License.
 */

package org.tfyh.data

import org.tfyh.util.Language
import kotlin.time.Clock

/**
 * A utility to hold datetime data. Caution: this ignores the timezone and leap seconds. It is not
 * meant to compare instances in time or to measure periods precisely.
 */
data class NoZoneDateTime(val dateTimeCleansed: String = ""): Comparable<NoZoneDateTime>
{

    private var parts = if (dateTimeCleansed.isEmpty())
        nowAsString().replace(" ", "-").replace(":", "-").split("-")
    else
        dateTimeCleansed.replace(" ", "-").replace(":", "-").split("-")
    private var epochMins = getEpochMins(dateTimeCleansed)
    private var partialMillis = getMillis(dateTimeCleansed)

    override fun compareTo(other: NoZoneDateTime): Int {
        if ((epochMins == other.epochMins) && (partialMillis == other.partialMillis))
            return 0
        if (microtime() < other.microtime())
            return -1
        return 1
    }

    fun toDateTimeString(language: Language) : String {
        return toDateString(language) + " " + toTimeString()
    }
    private fun toTimeString() : String {
        return (parts.getOrNull(3) ?: "00") + ":" +
                (parts.getOrNull(4) ?: "00") + ":" + (parts.getOrNull(5) ?: "00")
    }
    fun toDateString(language: Language) : String {
        return language.dateTemplate
            .replace("Y", year().toString())
            .replace("m", twoChars(month()))
            .replace("d", twoChars(day()))
    }

    fun year() = try { parts[0].toInt() } catch (_: Exception) { 1970 }
    fun month() = if (parts.size > 1) try { parts[1].toInt() } catch (_: Exception) { 1 } else 1
    fun day() = if (parts.size > 2) try { parts[2].toInt() } catch (_: Exception) { 1 } else 1
    fun hour() = if (parts.size > 3) try { parts[3].toInt() } catch (_: Exception) { 0 } else 0
    fun minute() = if (parts.size > 4) try { parts[4].toInt() } catch (_: Exception) { 0 } else 0
    fun second() = partialMillis / 1000
    fun millis() : Long = epochMins * 60000L + partialMillis
    fun microtime() = epochMins.toDouble() * 60.0 + partialMillis.toDouble() / 1000.0

    companion object {

        private var clientToServerOffsetMins = 0
        private var clientToServerOffsetMillis = 0
        private val inYearMinutes = mapOf(
            0 to 0,
            1 to 44640,
            2 to 84960,
            3 to 129600,
            4 to 172800,
            5 to 217440,
            6 to 260640,
            7 to 305280,
            8 to 349920,
            9 to 393120,
            10 to 437760,
            11 to 480960,
        )
        private val inLeapYearMinutes = mapOf(
            0 to 0,
            1 to 44640,
            2 to 86400,
            3 to 131040,
            4 to 174240,
            5 to 218880,
            6 to 262080,
            7 to 306720,
            8 to 351360,
            9 to 394560,
            10 to 439200,
            11 to 482400
        )

        const val EPOCH_MINS_OF_01_01_0001 = 1035593280

        // count of minutes from the 1. of January of year 1 to 1970-01-01
        // in the extrapolated Gregorian Calendar
        const val MINUTES_PER_DAY = 1440 // 24 * 60
        const val MINUTES_PER_YEAR = 525600 // 365 days

        fun twoChars(i: Int) = if (i < 10) "0$i" else i.toString()

        fun setClientToServerOffset(serverDateTime: String) {
            if (serverDateTime == "") {
                clientToServerOffsetMins = 0
                clientToServerOffsetMillis = 0
                return
            }
            val clientNow = Clock.System.now().toEpochMilliseconds()
            clientToServerOffsetMins = (clientNow / 60000).toInt() - getEpochMins(serverDateTime)
            clientToServerOffsetMillis = (clientNow % 60000).toInt() - getMillis(serverDateTime)
        }

        private fun isLeapYear(year: Int) = (year % 400 == 0) || ((year % 4 == 0) && (year % 100 != 0))

        private fun leapYearsBefore(year: Int) = (year - 1) / 4 - (year - 1) / 100 + (year - 1) / 400

        private fun yearOneMinsAtYearStart(year: Int): Int {
            return (year - 1) * MINUTES_PER_YEAR + leapYearsBefore(year) * MINUTES_PER_DAY
        }

        /**
         * Return the full minutes from the epoch, i.e. the start of Jan, 1st, 1970. for the dateTimeCleansed.
         * Leap seconds are ignored, i.e. for 2026-03-04 17:25:36.233 this will use 29453760 minutes for 56 years,
         * including 14 leap years and 90325 minutes totalling to 29544085 minutes. The 36.233 seconds are ignored.
         * The seconds fraction and all leap seconds are ignored. 32-bit integer values cover +/- 4085 years.
         */
        private fun getEpochMins(dateTimeCleansed: String): Int {
            if (dateTimeCleansed.isEmpty())
                return epochMins()
            val splitDateTime = dateTimeCleansed.replace(" ", "-").replace(":", "-").split("-")
            try {
                val year = splitDateTime[0].toInt()
                val minsFromYearOne = (year - 1) *   // to get the minutes at the beginning of the year use (year - 1)
                        MINUTES_PER_YEAR + leapYearsBefore(year) * MINUTES_PER_DAY
                val minutesFromEpoch = minsFromYearOne - EPOCH_MINS_OF_01_01_0001
                val isLeapYear = isLeapYear(year)
                val mm1 = splitDateTime[1].toInt() - 1
                var minutesFromYearStart: Int =
                    if (isLeapYear) inLeapYearMinutes[mm1] ?: 0 else inYearMinutes[mm1] ?: 0
                minutesFromYearStart += (splitDateTime[2].toInt() - 1) * MINUTES_PER_DAY +
                        (splitDateTime.getOrNull(3)?.toInt() ?: 0) * 60 +
                        (splitDateTime.getOrNull(4)?.toInt() ?: 0)
                return minutesFromEpoch + minutesFromYearStart
            } catch (_: Exception) {
                return epochMins()
            }
        }

        /**
         * Return the milliseconds part of the dateTimeCleansed String, i.e. for 2026-03-04 17:25:36.233
         * this will be 36,233,000.
         */
        private fun getMillis(dateTimeCleansed: String): Int {
            val parts = dateTimeCleansed.split(":")
            if (parts.size < 3)
                return (Clock.System.now().toEpochMilliseconds() % 60000).toInt()
            return (parts[2].toDouble() * 1000).toInt()
        }

        /**
         * Convert a timestamp into the date-time String.
         */
        fun epochToDateTimeString(epochMins: Int, partialMillis: Int = 0, language: Language = Language.CSV): String {

            // calculate the year
            val yearOneMins = epochMins + EPOCH_MINS_OF_01_01_0001
            var yearThis = (yearOneMins / 365.25 / MINUTES_PER_DAY).toInt()
            var y1minsAtYearStart = yearOneMinsAtYearStart(yearThis)
            while (y1minsAtYearStart <= yearOneMins) {
                yearThis++
                y1minsAtYearStart = yearOneMinsAtYearStart(yearThis)
            }
            yearThis--

            // calculate the month within the year
            val epochMinsAtThisYearStart = yearOneMinsAtYearStart(yearThis) - EPOCH_MINS_OF_01_01_0001
            val minutesInThisYear = epochMins - epochMinsAtThisYearStart
            var m = 0 // month 0...11
            val isLeapYearThis = isLeapYear(yearThis)
            if (isLeapYearThis)
                while ((m < 12) && ((inLeapYearMinutes[m] ?: 0) <= minutesInThisYear)) m++
            else
                while ((m < 12) && ((inYearMinutes[m] ?: 0) <= minutesInThisYear)) m++
            m--
            // calculate the day and tine within the month
            val inYearMinutesThisMonth = if (isLeapYearThis) inLeapYearMinutes[m] ?: 0
            else inYearMinutes[m] ?: 0
            val minutesInMonth = minutesInThisYear - inYearMinutesThisMonth
            val d = minutesInMonth / 1440 // = integer division
            val hourInDay = (minutesInMonth - d * 1440) / 60
            val minInHour = minutesInMonth - d * 1440 - hourInDay * 60
            val secondsInMinute = partialMillis / 1000  // = integer division

            return ymdToDateTimeString(
                yearThis, m + 1, d + 1,
                hourInDay, minInHour, secondsInMinute, language
            )
        }

        /**
         * Parse a cleansed date or datetime String into a LocalDateTime.
         * 
         * @param dateTimeCleansed a CSV formatted local date time string, e.g. 2026-07-02 12:12:45 or 2026-07-02
         * @return the parsed LocalDateTime or ParserConstraints.empty(ParserName.DATETIME) for any parsing error.
         */
        fun parse(dateTimeCleansed: String) = NoZoneDateTime(dateTimeCleansed)

        /**
         * get a LocalDateTime object for now
         */
        fun now(): NoZoneDateTime {
            return NoZoneDateTime(nowAsString())
        }

        fun epochMins(): Int {
            val nowClient = Clock.System.now().toEpochMilliseconds()
            return (nowClient / 60000).toInt() - clientToServerOffsetMins
        }

        /**
         * get a String for Now
         */
        fun nowAsString(): String {
            val nowClient = Clock.System.now().toEpochMilliseconds()
            val nowServerMins = (nowClient / 60000).toInt() - clientToServerOffsetMins
            val nowServerMillis = (nowClient % 60000).toInt() - clientToServerOffsetMillis
            return epochToDateTimeString(nowServerMins, nowServerMillis)
        }

        /**
         * Check, whether a dateTime String represents a valid date and time within the years 1 through 3000.
         */
        fun isValid(dateTimeCleansed: String): Boolean {
            try {
                val dt = NoZoneDateTime(dateTimeCleansed)
                val isLeapYear = isLeapYear((dt.parts.getOrNull(0) ?: "0000").toInt())
                val y = (dt.parts[0]).toInt()
                val m = (dt.parts.getOrNull(1) ?: "99").toInt()
                val daysOfMonth =
                    if (m == 2) {
                        if (isLeapYear) 29 else 28
                    } else if ((m == 4) || (m == 6) || (m == 9) || (m == 11)) 30
                    else 31
                val d = (dt.parts.getOrNull(2) ?: "99").toInt()
                val h = (dt.parts.getOrNull(3) ?: "99").toInt()
                val i = (dt.parts.getOrNull(4) ?: "99").toInt()
                val s = (dt.parts.getOrNull(5) ?: "99").toInt()
                return (y >= 1) && (y < 3000) && (m >= 1) && (m <= 12) && (d >= 1) && (d <= daysOfMonth) &&
                        (h >= 0) && (h <= 23) && (i >= 0) && (i <= 59) && (s >= 0) && (s <= 59)
            } catch (_: Exception) {
                return false
            }
        }

        /**
         * format a year, month, and day into a valid local date format.
         */
        fun ymdToDateString(y: Int, m: Int, d: Int, language: Language = Language.CSV): String {
            return language.dateTemplate
                .replace("Y", y.toString())
                .replace("m", twoChars(m))
                .replace("d", twoChars(d))
        }

        /**
         * format a year, month, day, hour, minute (i), second into a valid local date-time format.
         */
        fun ymdToDateTimeString(
            y: Int,
            m: Int,
            d: Int,
            h: Int,
            i: Int,
            s: Int,
            language: Language = Language.CSV
        ): String {
            return ymdToDateString(y, m, d, language) + " " +
                    twoChars(h) + ":" + twoChars(i) + ":" + twoChars(s)
        }

    }
}