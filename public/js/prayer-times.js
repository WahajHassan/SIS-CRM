/*
 * Prayer times, worked out here.
 *
 * The sun's position for a date and a place is arithmetic, not a service, so it
 * is done in the browser: no key to keep, no account to renew, and the times
 * still appear on a morning when some website is down.
 *
 * The method is the standard one — the sun's declination and the equation of
 * time for the day, then the hour angle at which the sun sits at each prayer's
 * angle below or above the horizon. Which angles those are is the part that
 * differs between conventions, so the convention is a setting rather than a
 * number buried in the sums.
 *
 * Accurate to about a minute, which is the same as the printed timetables.
 * Where a mosque's own timetable differs — and some do, by a few minutes — that
 * one is right and this is a reminder.
 */
(function (global) {
    'use strict';

    /** The conventions people actually use, and what they disagree about. */
    var METHODS = {
        karachi: { name: 'Karachi', fajr: 18, isha: 18 },
        mwl:     { name: 'Muslim World League', fajr: 18, isha: 17 },
        isna:    { name: 'North America (ISNA)', fajr: 15, isha: 15 },
        egypt:   { name: 'Egyptian General Authority', fajr: 19.5, isha: 17.5 },
        makkah:  { name: 'Umm al-Qura, Makkah', fajr: 18.5, isha: null, ishaAfterMaghrib: 90 },
        tehran:  { name: 'Tehran', fajr: 17.7, isha: 14 },
    };

    /** Asr falls where a shadow is this many times the object's own length. */
    var SCHOOLS = { standard: 1, hanafi: 2 };

    var RAD = Math.PI / 180;

    function sin(d) { return Math.sin(d * RAD); }
    function cos(d) { return Math.cos(d * RAD); }
    function tan(d) { return Math.tan(d * RAD); }
    function asin(x) { return Math.asin(x) / RAD; }
    function acos(x) { return Math.acos(x) / RAD; }
    function atan2(y, x) { return Math.atan2(y, x) / RAD; }

    function fix(value, range) {
        value -= range * Math.floor(value / range);
        return value < 0 ? value + range : value;
    }

    /** Days since the start of 2000, which is where the sun's sums begin. */
    function julian(date) {
        var year = date.getFullYear();
        var month = date.getMonth() + 1;
        var day = date.getDate();

        if (month <= 2) { year -= 1; month += 12; }

        var a = Math.floor(year / 100);
        var b = 2 - a + Math.floor(a / 4);

        return Math.floor(365.25 * (year + 4716)) +
               Math.floor(30.6001 * (month + 1)) + day + b - 1524.5;
    }

    /** Where the sun is on a given day: how far north, and how far behind the clock. */
    function sunPosition(jd) {
        var d = jd - 2451545.0;

        var meanAnomaly = fix(357.529 + 0.98560028 * d, 360);
        var meanLongitude = fix(280.459 + 0.98564736 * d, 360);

        var apparentLongitude = fix(
            meanLongitude + 1.915 * sin(meanAnomaly) + 0.020 * sin(2 * meanAnomaly), 360);

        var obliquity = 23.439 - 0.00000036 * d;

        var declination = asin(sin(obliquity) * sin(apparentLongitude));

        var rightAscension = fix(atan2(cos(obliquity) * sin(apparentLongitude), cos(apparentLongitude)), 360) / 15;

        // How far the real sun runs ahead of or behind the clock, in hours.
        var equationOfTime = meanLongitude / 15 - fix(rightAscension, 24);
        if (equationOfTime > 12) equationOfTime -= 24;
        if (equationOfTime < -12) equationOfTime += 24;

        return { declination: declination, equationOfTime: equationOfTime };
    }

    /** How long before or after noon the sun sits at a given angle. */
    function hourAngle(angle, latitude, declination) {
        var top = -sin(angle) - sin(latitude) * sin(declination);
        var bottom = cos(latitude) * cos(declination);
        var ratio = top / bottom;

        // Far enough north or south and the sun never gets there at all.
        if (ratio > 1 || ratio < -1) return null;

        return acos(ratio) / 15;
    }

    function asrAngle(shadows, latitude, declination) {
        return -atan2(1, shadows + tan(Math.abs(latitude - declination)));
    }

    function toClock(hours) {
        if (hours === null || isNaN(hours)) return null;

        var total = Math.round(fix(hours, 24) * 60);
        return { h: Math.floor(total / 60) % 24, m: total % 60 };
    }

    /**
     * The five, plus sunrise, for one day in one place.
     *
     * The offset is the place's own hours from UTC, so a timetable can be read
     * for somewhere other than where the browser is sitting.
     */
    function timesFor(date, latitude, longitude, options) {
        options = options || {};

        var method = METHODS[options.method] || METHODS.karachi;
        var shadows = SCHOOLS[options.school] || SCHOOLS.standard;

        var offset = options.utcOffset;
        if (offset === undefined || offset === null) offset = -date.getTimezoneOffset() / 60;

        var sun = sunPosition(julian(date));

        // Midday where the sun is actually overhead, in that place's clock.
        var noon = 12 - longitude / 15 - sun.equationOfTime + offset;

        var sunriseAngle = 0.833;     // the sun's own width, plus the air bending its light

        function before(angle) {
            var h = hourAngle(angle, latitude, sun.declination);
            return h === null ? null : noon - h;
        }
        function after(angle) {
            var h = hourAngle(angle, latitude, sun.declination);
            return h === null ? null : noon + h;
        }

        var sunrise = before(sunriseAngle);
        var maghrib = after(sunriseAngle);

        var fajr = before(method.fajr);

        var isha;
        if (method.isha === null && method.ishaAfterMaghrib) {
            isha = maghrib === null ? null : maghrib + method.ishaAfterMaghrib / 60;
        } else {
            isha = after(method.isha);
        }

        /*
         * Far enough north and the sun never gets as low as the angle asks for:
         * through a London summer there is no moment when it is eighteen
         * degrees under the horizon, so Fajr and Isha have no answer at all.
         *
         * Timetables there do not print a blank. They divide the night into
         * sevenths and take one from each end, which is what is done here —
         * approximate, and the same approximation everybody else uses.
         */
        if ((fajr === null || isha === null) && sunrise !== null && maghrib !== null) {
            var night = (sunrise + 24) - maghrib;
            var seventh = night / 7;

            if (fajr === null) fajr = sunrise - seventh;
            if (isha === null) isha = maghrib + seventh;
        }

        return {
            fajr: toClock(fajr),
            sunrise: toClock(sunrise),
            dhuhr: toClock(noon + 1 / 60),            // a minute past, as is usual
            asr: toClock(after(asrAngle(shadows, latitude, sun.declination))),
            maghrib: toClock(maghrib),
            isha: toClock(isha),
            method: method.name,
            // True when the night was too short for the angles and the sevenths
            // rule was used instead, so the page can say so.
            estimated: (before(method.fajr) === null) || (method.isha !== null && after(method.isha) === null),
        };
    }

    /** "6:14 AM", in the reader's own language. */
    function spell(clock) {
        if (!clock) return '—';

        var when = new Date();
        when.setHours(clock.h, clock.m, 0, 0);

        return when.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    }

    /** Which one is next, and how long until it. */
    function next(times, now) {
        now = now || new Date();
        var minutesNow = now.getHours() * 60 + now.getMinutes();

        var order = [
            ['Fajr', times.fajr],
            ['Sunrise', times.sunrise],
            ['Dhuhr', times.dhuhr],
            ['Asr', times.asr],
            ['Maghrib', times.maghrib],
            ['Isha', times.isha],
        ];

        for (var i = 0; i < order.length; i++) {
            var clock = order[i][1];
            if (!clock) continue;

            var minutes = clock.h * 60 + clock.m;
            if (minutes > minutesNow) {
                return { name: order[i][0], clock: clock, inMinutes: minutes - minutesNow, tomorrow: false };
            }
        }

        // Past Isha: the next one is tomorrow's Fajr.
        if (!times.fajr) return null;

        var fajrMinutes = times.fajr.h * 60 + times.fajr.m;
        return {
            name: 'Fajr',
            clock: times.fajr,
            inMinutes: (24 * 60 - minutesNow) + fajrMinutes,
            tomorrow: true,
        };
    }

    /** "in 48 minutes", "in 2h 10m", "now". */
    function howLong(minutes) {
        if (minutes <= 0) return 'now';
        if (minutes < 60) return 'in ' + minutes + ' min';

        var hours = Math.floor(minutes / 60);
        var rest = minutes % 60;
        return 'in ' + hours + 'h' + (rest ? ' ' + rest + 'm' : '');
    }

    global.PrayerTimes = {
        METHODS: METHODS,
        SCHOOLS: SCHOOLS,
        timesFor: timesFor,
        spell: spell,
        next: next,
        howLong: howLong,
    };
})(window);
