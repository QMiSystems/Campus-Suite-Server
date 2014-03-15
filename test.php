<?php
function isValidTimeStamp($timestamp) {
    return ((string) (int) $timestamp === $timestamp) 
        && ($timestamp <= PHP_INT_MAX)
        && ($timestamp >= ~PHP_INT_MAX);
}

if(isValidTimeStamp("1366688800")) {
	echo("Passed");
}

?>