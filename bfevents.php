<?php

/**
 * Script to extract all event the BF source code and find any changes.
 * Primarily used to help keep the main https://epee.me/calendar up to date.
 *
 * @version v1.0.0
 * @author  Dan Kew <dan@epee.me>
 * @license http://opensource.org/licenses/gpl-3.0 GNU General Public License, version 3 (GPLv3)
 */

setlocale(LC_ALL, "en_US.UTF-8");

// Include SQL routines.
require "bfdb.php";

// Grab the events page from the britishfencing web page.
$BF = file_get_contents('https://www.britishfencing.com/results-rankings/find-event-3/');

// Find the start of the events list in the source code.
$BF = trim(stristr($BF, 'var eventData = ['), "'var eventData = ['");

// Find the end of the events list.
$BF = explode("\n", stristr($BF, '];', true));

// At this stage we are only interested in competitions from 2019 onwards.
$datefrom = strtotime('2019-01-01');

// Connect to db and extract previous calendar content.
$db = new dbBF();
$s  = $db -> prepare('SELECT eventName FROM cal_bfdb');
$db -> execute($s);
$excal = $db -> getAllResults($s);

if (count($excal) === 0) {
    // Update db with latest calendar, no previous calendar exists.
    $cu = $db -> prepare('INSERT INTO cal_bfdb (eventName) VALUES (?)');

    for ($i = 1; $i <= count($BF); $i++) {
        $en = json_decode(rtrim(trim($BF[$i]), ", "), true);
        if (strtotime($en['date']) >= $datefrom) {
            $db -> bind($cu, 1, $en['title']);
            $db -> execute($cu);
        }
    }
} else {
    // Previous calendar exists, so now compare this with data just grabbed and identify differences.
    $newValues = [];
    for ($i = 1; $i <= count($BF); $i++) {
        $en = json_decode(rtrim(trim($BF[$i]), ", "), true);
        if (strtotime($en['date']) >= $datefrom) {
            array_push($newValues, $en['title']);
        }
    }

    $changes = array_diff($newValues, $excal);

    if (count($changes) > 0) {
        // Now empty out the table with the old data and add in the new data.
        $ddb = $db -> prepare('DELETE FROM cal_bfdb');
        $db -> execute($ddb);
        $cu = $db -> prepare('INSERT INTO cal_bfdb (eventName) VALUES (?)');
        for ($i = 0; $i < count($newValues); $i++) {
            $db -> bind($cu, 1, $newValues[$i]);
            $db -> execute($cu);
        }

        // Finally email the changes to me.
        mail('iamdek.global@gmail.com', 'BF Calendar Changes', print_r($changes, true));
    }
}//end if
