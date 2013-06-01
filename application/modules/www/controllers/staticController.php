<?php
    namespace Thin;
    class staticController extends Controller
    {
        public function init()
        {

        }

        public function preDispatch()
        {

        }

        public function testAction()
        {

        }

        public function homeAction()
        {
            $this->view->title = 'Accueil';
            $this->view->activeHome = 'active';
        }

        public function servicesAction()
        {
            $this->view->title = 'Services';
            $this->view->activeServices = 'active';
        }

        public function webAction()
        {
            $this->view->title = 'Création de sites internet';
            $this->view->activeServices = 'active';
        }

        public function contentAction()
        {
            $this->view->title = 'Gestion de contenu';
            $this->view->activeServices = 'active';
        }

        public function vitrineAction()
        {
            $this->view->title = 'Sites vitrines';
            $this->view->activeServices = 'active';
        }

        public function refonteAction()
        {
            $this->view->title = 'Refonte de sites internet';
            $this->view->activeServices = 'active';
        }

        public function ecommerceAction()
        {
            $this->view->title = 'Création de sites e-commerce';
            $this->view->activeServices = 'active';
        }

        public function contactAction()
        {
            $this->view->title = 'Contact';
            $this->view->activeContact = 'active';
        }

        public function is404Action()
        {
            $this->view->title = 'Page inconnue';
        }

        public function isErrorAction()
        {
            $this->view->error = \u::get('ThinError');
            $this->view->title = 'Erreur';
        }

        public function postDispatch()
        {

        }
    }
