<?php
    $routes = array();
    $routes['collection'] = array();

    $home = new Route();
    $home->setPath('/');
    $home->setModule('www');
    $home->setController('static');
    $home->setAction('home');
    array_push($routes['collection'], $home);

    $services = new Route();
    $services->setPath('/services');
    $services->setModule('www');
    $services->setController('static');
    $services->setAction('services');
    array_push($routes['collection'], $services);

    $servicesWeb = new Route();
    $servicesWeb->setPath('/services/web');
    $servicesWeb->setModule('www');
    $servicesWeb->setController('static');
    $servicesWeb->setAction('web');
    array_push($routes['collection'], $servicesWeb);

    $servicesCms = new Route();
    $servicesCms->setPath('/services/content');
    $servicesCms->setModule('www');
    $servicesCms->setController('static');
    $servicesCms->setAction('content');
    array_push($routes['collection'], $servicesCms);

    $servicesVitrine = new Route();
    $servicesVitrine->setPath('/services/vitrine');
    $servicesVitrine->setModule('www');
    $servicesVitrine->setController('static');
    $servicesVitrine->setAction('vitrine');
    array_push($routes['collection'], $servicesVitrine);

    $servicesRefonte = new Route();
    $servicesRefonte->setPath('/services/refonte');
    $servicesRefonte->setModule('www');
    $servicesRefonte->setController('static');
    $servicesRefonte->setAction('refonte');
    array_push($routes['collection'], $servicesRefonte);

    $servicesEcommerce = new Route();
    $servicesEcommerce->setPath('/services/ecommerce');
    $servicesEcommerce->setModule('www');
    $servicesEcommerce->setController('static');
    $servicesEcommerce->setAction('ecommerce');
    array_push($routes['collection'], $servicesEcommerce);

    $contact = new Route();
    $contact->setPath('/contact');
    $contact->setModule('www');
    $contact->setController('static');
    $contact->setAction('contact');
    array_push($routes['collection'], $contact);

    $test = new Route();
    $test->setPath('test/[a-zA-Z\s]/(.*)');
    $test->setModule('www');
    $test->setController('home');
    $test->setAction('test');
    $test->setParam1('id');
    $test->setParam2('key');
    array_push($routes['collection'], $test);

    return $routes;
