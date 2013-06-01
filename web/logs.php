<?php
    $log = (array_key_exists('log', $_REQUEST)) ? $_REQUEST['log'] : null;
    $logs = glob('../storage/logs/*.log');

    if (null === $log) {
        for ($i = 0 ; $i < count($logs) ; $i++) {
            $tab = explode('/', $logs[$i]);
            echo "<a href='logs.php?log=$i'>" . end($tab) . "</a><hr />";
        }
    } else {
        $content = str_replace("\n", "<hr />", file_get_contents($logs[$log]));
        die($content);
    }
