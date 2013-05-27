<?php
    $routes = array();
    $routes['collection'] = array();

    $home = new Route();
    $home->setPath('/');
    $home->setModule('www');
    $home->setController('home');
    $home->setAction('index');
    array_push($routes['collection'], $home);

    $test = new Route();
    $test->setPath('test/[a-zA-Z\s]/(.*)');
    $test->setModule('www');
    $test->setController('home');
    $test->setAction('test');
    $test->setParam1('id');
    $test->setParam2('key');
    array_push($routes['collection'], $test);

    return $routes;
