<?php
    $file = (array_key_exists('file', $_REQUEST)) ? $_REQUEST['file'] : null;
    $name = (array_key_exists('name', $_REQUEST)) ? $_REQUEST['name'] : null;
    $type = (array_key_exists('type', $_REQUEST)) ? $_REQUEST['type'] : null;

    if (null !== $file && null !== $name && null !== $type) {
        $dwn = '../storage/cache/' . $file . '.' . $type;
        if (file_exists($dwn)) {
            $content = file_get_contents($dwn);
            header("Content-type: application/$type");
            header("Content-Length: " . strlen($content));
            header("Content-Disposition: attachement; filename=\"$name.$type\"");
            echo $content;
            @unlink($dwn);
            exit;
        } else {
            die('NOK1');
        }
    } else {
        die('NOK2');
    }
