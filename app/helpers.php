<?php
namespace rosasurfer\xtrade;

use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\IllegalArgumentException;
use rosasurfer\exception\UnimplementedFeatureException;
use rosasurfer\exception\RuntimeException;


/**
 * Timezones and timezone ids
 *
 * @see  definition in MT4Expander::defines.h
 */
const TIMEZONE_ALPARI              = 'Alpari';              // until 03/2012 "Europe/Berlin", since 04/2012 "Europe/Kiev"
const TIMEZONE_AMERICA_NEW_YORK    = 'America/New_York';
const TIMEZONE_EUROPE_BERLIN       = 'Europe/Berlin';
const TIMEZONE_EUROPE_KIEV         = 'Europe/Kiev';
const TIMEZONE_EUROPE_LONDON       = 'Europe/London';
const TIMEZONE_EUROPE_MINSK        = 'Europe/Minsk';
const TIMEZONE_FXT                 = 'FXT';                 // "Europe/Kiev"   with DST transitions of "America/New_York"
const TIMEZONE_FXT_MINUS_0200      = 'FXT-0200';            // "Europe/London" with DST transitions of "America/New_York"
const TIMEZONE_GLOBALPRIME         = 'GlobalPrime';         // "FXT" with one-time transition error of "Europe/Kiev" on 24.10.2015
const TIMEZONE_GMT                 = 'GMT';

const TIMEZONE_ID_ALPARI           =  1;
const TIMEZONE_ID_AMERICA_NEW_YORK =  2;
const TIMEZONE_ID_EUROPE_BERLIN    =  3;
const TIMEZONE_ID_EUROPE_KIEV      =  4;
const TIMEZONE_ID_EUROPE_LONDON    =  5;
const TIMEZONE_ID_EUROPE_MINSK     =  6;
const TIMEZONE_ID_FXT              =  7;
const TIMEZONE_ID_FXT_MINUS_0200   =  8;
const TIMEZONE_ID_GLOBALPRIME      =  9;
const TIMEZONE_ID_GMT              = 10;


// Timeframe identifier
const PERIOD_M1  =      1;                   // 1 minute
const PERIOD_M5  =      5;                   // 5 minutes
const PERIOD_M15 =     15;                   // 15 minutes
const PERIOD_M30 =     30;                   // 30 minutes
const PERIOD_H1  =     60;                   // 1 hour
const PERIOD_H4  =    240;                   // 4 hours
const PERIOD_D1  =   1440;                   // daily
const PERIOD_W1  =  10080;                   // weekly
const PERIOD_MN1 =  43200;                   // monthly
const PERIOD_Q1  = 129600;                   // a quarter (3 months)


// Operation types
const OP_BUY       = 0;                       //    MT4: long position
const OP_SELL      = 1;                       //         short position
const OP_BUYLIMIT  = 2;                       //         buy limit order
const OP_SELLLIMIT = 3;                       //         sell limit order
const OP_BUYSTOP   = 4;                       //         stop buy order
const OP_SELLSTOP  = 5;                       //         stop sell order
const OP_BALANCE   = 6;                       //         account credit or withdrawal transaction
const OP_CREDIT    = 7;                       //         credit facility, no transaction
const OP_TRANSFER  = 8;                       // custom: balance update by client (deposit or withdrawal)
const OP_VENDOR    = 9;                       //         balance update by criminal (dividends, swap, manual etc.)


// Strategy Tester bar models
const BARMODEL_EVERYTICK     = 0;
const BARMODEL_CONTROLPOINTS = 1;
const BARMODEL_BAROPEN       = 2;


// Strategy Tester trade directions
const TRADEDIRECTION_LONG  = 0;
const TRADEDIRECTION_SHORT = 1;
const TRADEDIRECTION_BOTH  = 2;


// Struct sizes
const DUKASCOPY_BAR_SIZE  = 24;
const DUKASCOPY_TICK_SIZE = 20;


/**
 * Return the FXT based timestamp of the specified time (seconds since 1970-01-01 00:00 FXT).
 *
 * @param  int    $timestamp - time (default: current time)
 * @param  string $timezone  - timestamp base, including FXT (default: GMT)
 *
 * @return int - FXT based timestamp
 */
function fxtTime($timestamp=null, $timezone='GMT') {
    if (!is_string($timezone))    throw new IllegalTypeException('Illegal type of parameter $timezone: '.getType($timezone));
    if ($timestamp === null) {
        $timestamp = time();
        $timezone  = 'GMT';
    }
    else if (!is_int($timestamp)) throw new IllegalTypeException('Illegal type of parameter $timestamp: '.getType($timestamp));
    $timezone = strToUpper($timezone);

    if ($timezone == 'FXT')
        return $timestamp;                           // with FXT input and result are equal

    $gmtTime = null;

    if ($timezone=='GMT' || $timezone=='UTC') {
        $gmtTime = $timestamp;
    }
    else {
        // convert $timestamp to GMT timestamp
        $oldTimezone = date_default_timezone_get();
        try {
            date_default_timezone_set($timezone);

            $offsetA = iDate('Z', $timestamp);
            $gmtTime = $timestamp + $offsetA;

            $offsetB = iDate('Z', $gmtTime);          // double check if DST change is exactly between $timestamp and $gmtTime
            if ($offsetA != $offsetB) { /* TODO */ }
        }
        finally {
            date_default_timezone_set($oldTimezone);
        }
    }

    // convert $gmtTime to FXT timestamp
    $oldTimezone = date_default_timezone_get();
    try {
        date_default_timezone_set('America/New_York');

        $estOffset = iDate('Z', $gmtTime);
        $fxtTime   = $gmtTime + $estOffset + 7*HOURS;

        return $fxtTime;
    }
    finally {
        date_default_timezone_set($oldTimezone);
    }
}


/**
 * Whether or not a time is on a FXT based Forex trading day.
 *
 * @param  int    $timestamp
 * @param  string $timezone  - timestamp base, including FXT
 *                             (default: GMT)
 * @return bool
 */
function isFxtTradingDay($timestamp, $timezone='GMT') {
    if (!is_int($timestamp))   throw new IllegalTypeException('Illegal type of parameter $timestamp: '.getType($timestamp));
    if (!is_string($timezone)) throw new IllegalTypeException('Illegal type of parameter $timezone: '.getType($timezone));

    return (!isFxtWeekend($timestamp, $timezone) && !isFxtHoliday($timestamp, $timezone));
}


/**
 * Whether or not a time is on a FXT based Forex weekend (Saturday or Sunday).
 *
 * @param  int    $timestamp
 * @param  string $timezone  - timestamp base, including FXT
 *                             (default: GMT)
 * @return bool
 */
function isFxtWeekend($timestamp, $timezone='GMT') {
    if (!is_int($timestamp))   throw new IllegalTypeException('Illegal type of parameter $timestamp: '.getType($timestamp));
    if (!is_string($timezone)) throw new IllegalTypeException('Illegal type of parameter $timezone: '.getType($timezone));

    // convert $timestamp to FXT timestamp
    if (strToUpper($timezone) != 'FXT')
        $timestamp = fxtTime($timestamp, $timezone);

    // check $timestamp as GMT timestamp
    $dow = (int) gmDate('w', $timestamp);
    return ($dow==SATURDAY || $dow==SUNDAY);
}


/**
 * Whether or not a time is on a FXT based Forex holiday.
 *
 * @param  int    $timestamp
 * @param  string $timezone  - timestamp base, including FXT
 *                             (default: GMT)
 * @return bool
 */
function isFxtHoliday($timestamp, $timezone='GMT') {
    if (!is_int($timestamp))   throw new IllegalTypeException('Illegal type of parameter $timestamp: '.getType($timestamp));
    if (!is_string($timezone)) throw new IllegalTypeException('Illegal type of parameter $timezone: '.getType($timezone));

    // convert $timestamp to FXT timestamp
    if (strToUpper($timezone) != 'FXT')
        $timestamp = fxtTime($timestamp, $timezone);

    // check $timestamp as GMT timestamp
    $m   = (int) gmDate('n', $timestamp);     // month
    $dom = (int) gmDate('j', $timestamp);     // day of month

    if ($dom==1 && $m==1)                     // 1. January
        return true;
    if ($dom==25 && $m==12)                   // 25. December
        return true;
    return false;
}


/**
 * Return a nicely formatted time range description.
 *
 * @param  string $startTime
 * @param  string $endTime
 *
 * @return string
 */
function prettyTimeRange($startTime, $endTime) {
    $startDate = new \DateTime($startTime);
    $endDate   = new \DateTime($endTime);

    $startTimestamp = $startDate->getTimestamp();
    $endTimestamp   = $endDate->getTimestamp();

    $range = null;

    if (iDate('Y', $startTimestamp) == iDate('Y', $endTimestamp)) {
        if (iDate('m', $startTimestamp) == iDate('m', $endTimestamp)) {
            if (iDate('d', $startTimestamp) == iDate('d', $endTimestamp)) {
                 $range = $startDate->format('d.m.Y H:i').'-'.$endDate->format('H:i');
            }
            else $range = $startDate->format('d.').'-'.$endDate->format('d.m.Y');
        }
        else $range = $startDate->format('d.m.').'-'.$endDate->format('d.m.Y');
    }
    else $range = $startDate->format('d.m.Y').'-'.$endDate->format('d.m.Y');

    return $range;
}


/**
 * User-land implementation of PECL::stats_standard_deviation()
 *
 * @param  array $values
 * @param  bool  $sample [optional] - whether or not the values represent a sample and not the total population
 *                                    (default: no)
 * @return float - standard deviation
 */
function stats_standard_deviation(array $values, $sample = false) {
    if (function_exists('stats_standard_deviation')) {
        $result = \stats_standard_deviation($values, $sample);
        if (!is_float($result)) throw new RuntimeException('stats_standard_deviation returned an error: '.$result.' ('.getType($result).')');
        return $result;
    }

    $n = sizeof($values);
    if (           $n==0) throw new IllegalArgumentException('Illegal number of values (zero)');
    if ($sample && $n==1) throw new IllegalArgumentException('Illegal number of values (one)');

    $avg   = array_sum($values) / $n;           // arythmetic mean
    $sqSum = 0;

    foreach ($values as $value) {
        $diff   = $value - $avg;
        $sqSum += $diff * $diff;
    };
    if ($sample) $n--;

    return sqrt($sqSum / $n);
}


/**
 * Calculate the Sharpe ratio of the given returns.
 *
 * @param  array $returns
 * @param  bool  $compound [optional] - whether or not the returns are compounding returns
 *                                      (default: no)
 * @param  bool  $sample   [optional] - whether or not the returns represent only a sample of the total population
 *                                      (default: no)
 * @return float - non-normalized Sharpe ratio
 */
function stats_sharpe_ratio(array $returns, $compound=false, $sample=false) {
    $n = sizeof($returns);
    if (           $n==0) throw new IllegalArgumentException('Illegal number of returns (zero)');
    if ($sample && $n==1) throw new IllegalArgumentException('Illegal number of returns (one)');
    if ($compound)        throw new UnimplementedFeatureException('Processing of compounding returns not yet implemented');

    $avgReturn = array_sum($returns) / $n;      // arythmetic mean
    return $avgReturn / stats_standard_deviation($returns, $sample);
}


/**
 * Calculate the Sortino ratio of the given returns.
 *
 * @param  array $returns
 * @param  bool  $compound [optional] - whether or not the returns are compounding returns
 *                                      (default: no)
 * @param  bool  $sample   [optional] - whether or not the returns represent only a sample of the total population
 *                                      (default: no)
 * @return float - non-normalized Sortino ratio
 */
function stats_sortino_ratio(array $returns, $compound=false, $sample=false) {
    $n = sizeof($returns);
    if (           $n==0) throw new IllegalArgumentException('Illegal number of returns (zero)');
    if ($sample && $n==1) throw new IllegalArgumentException('Illegal number of returns (one)');
    if ($compound)        throw new UnimplementedFeatureException('Processing of compounding returns not yet implemented');

    $avgReturn = array_sum($returns) / $n;      // arythmetic mean

    foreach ($returns as $i => $return) {
        if ($return > 0)
            $returns[$i] = 0;
    }
    return $avgReturn / stats_standard_deviation($returns, $sample);
}
