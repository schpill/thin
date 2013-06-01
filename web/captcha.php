<?php
    $key = (array_key_exists('key', $_REQUEST)) ? $_REQUEST['key'] : null;
    if (null !== $key) {
        $image = realpath('../storage/cache/' . $key . '.jpg');
        if (file_exists($image)) {
            header("Content-type: image/jpeg");
            $content = file_get_contents($image);
            die($content);
        }
    }
    die('error');
