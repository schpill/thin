<?php
    $log = (array_key_exists('log', $_REQUEST)) ? $_REQUEST['log'] : null;
    $logs = glob('../storage/logs/*.log');

    if (null === $log) {
        for ($i = 0 ; $i < count($logs) ; $i++) {
            $tab = explode('/', $logs[$i]);
            echo "<a href='logs.php?log=$i'>" . end($tab) . "</a><hr />";
        }
    } else {
        $content = '<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="content-type" content="text/html;charset=utf-8" />
        <link href="//netdna.bootstrapcdn.com/twitter-bootstrap/2.3.2/css/bootstrap-combined.min.css" rel="stylesheet" />
        <link href="//netdna.bootstrapcdn.com/font-awesome/3.2.1/css/font-awesome.min.css" rel="stylesheet" />
        <script src="//cdnjs.cloudflare.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
        <script src="//netdna.bootstrapcdn.com/twitter-bootstrap/2.3.2/js/bootstrap.min.js"></script>
        <link href="http://fonts.googleapis.com/css?family=Open+Sans+Condensed:300,300italic,700" rel="stylesheet" type="text/css" />
        <style>* {font-family: "Open Sans Condensed"; font-size: 110%;} .successLi {color: green; font-size: 75%;}</style>
        <title>Logs</title>
        </head>
        <body>
        <div class="container-fluid"><div class="row-fluid"><div class="span12"><h1>LOGS</h1></div></div><div class="row-fluid"><div class="span12"><ul class="unstyled"><li>';
        $content .= str_replace("\n", "</li><li>", file_get_contents($logs[$log]));
        $content = substr($content, 0, -4) . '</ul></div></div></div>';
        $content = str_replace('<li>', '<li><i class="icon-check successLi"></i> ', $content);
        $content = str_replace(' => ', ' <span class="label label-info" style="font-size: 75%;">', $content);
        $content = str_replace('</li>', '</span></li>', $content);
        die($content);
    }
