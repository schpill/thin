<?php
    /**
     * ACL class
     * @author      Gerald Plusquellec
     */
    namespace Thin;
    class Acl
    {
        protected $_datas = array();

        public function __construct(array $config)
        {
            $this->_datas['roleModel']          = em($config['roles']['entity'], $config['roles']['table']);
            $this->_datas['userModel']          = em($config['users']['entity'], $config['users']['table']);
            $this->_datas['canRoles']           = array();
            $this->_datas['cannotRoles']        = array();
            $this->_datas['canUsers']           = array();
            $this->_datas['cannotUsers']        = array();
            $this->_datas['noRight']            = $config['noRight'];
            $this->_datas['config']             = $config;
        }

        public function canByRole($role)
        {
            $class = get_parent_class($this->_datas['roleModel']);
            if (!$role instanceof $class) {
                throw new Exception('The role is not in correct format.');
            }
            if (!Arrays::in($role->getId(), $this->_datas['canRoles'])) {
                $this->_datas['canRoles'][] = $role->getId();
            }
            return $this;
        }

        public function cannotByRole($role)
        {
            $class = get_parent_class($this->_datas['roleModel']);
            if (!$role instanceof $class) {
                throw new Exception('The role is not in correct format.');
            }
            if (!Arrays::in($role->getId(), $this->_datas['cannotRoles'])) {
                $this->_datas['cannotRoles'][] = $role->getId();
            }
            return $this;
        }

        public function canByUser()
        {
            $user = Utils::get('ThinUser');
            if (!Arrays::in($user->getId(), $this->_datas['canUsers'])) {
                $this->_datas['canUsers'][] = $user->getId();
            }
            return $this;
        }

        public function cannotByUser()
        {
            $user = Utils::get('ThinUser');
            if (!Arrays::in($user->getId(), $this->_datas['cannotUsers'])) {
                $this->_datas['cannotUsers'][] = $user->getId();
            }
            return $this;
        }

        public function checkAccessModule()
        {
            $user           = Utils::get('ThinUser');
            if (null !== $user) {
                $aclRules   = Utils::get('ThinConfigAcl');
                $module     = Utils::get('ThinModuleName');
                $controller = Utils::get('ThinControllerName');
                $action     = Utils::get('ThinActionName');
                $module     = Utils::get('ThinModuleName');
                $userRoles  = em($this->_datas['config']['usersroles']['entity'], $this->_datas['config']['usersroles']['table'])->fetch()->findByAccountId($user->getId());
                $aclRoles   = $this->_datas['config']['acl']['roles'];

                /* on regarde s'il y a une restriction d acces au module, on prenant garde de pouvoir afficher les pages statiques no-right et is-404 */

                if (ake($module, $aclRules) && 'no-right' != $action && 'is-404' != $action) {
                    if (ake('cannotByRole', $aclRules[$module])) {
                        $access = false;
                        foreach ($aclRoles as $aclRole) {
                            foreach ($userRoles as $userRole) {
                                $role  = $this->_datas['roleModel']->find($userRole->getRoleId())->getRoleName();
                                if (!Arrays::in($role, $aclRules[$module]['cannotByRole']) && in_array($role, $aclRoles)) {
                                    $access = true;
                                }
                            }
                        }
                        if (false === $access) {
                            Utils::go($this->_datas['noRight']);
                            exit;
                        }
                    }
                }
            }
        }

        public function check()
        {
            $user       = Utils::get('FTVUser');
            $aclRoles   = $this->_datas['config']['acl']['roles'];
            $adminRole  = $this->_datas['roleModel']->findByRoleName($this->_datas['config']['role']['admin']);
            $userRoles  = em($this->_datas['config']['usersroles']['entity'], $this->_datas['config']['usersroles']['table'])->findByAccountId($user->getId());
            if (count($userRoles) == 1) {
                $userRoles = array($userRoles);
            }

            // check if role is allowed in application
            $continue = false;
            foreach ($userRoles as $uRole) {
                $roleName = em($this->_datas['config']['roles']['entity'], $this->_datas['config']['roles']['table'])->find($uRole->getRoleId())->getRoleName();
                $continue = Arrays::in($roleName, $aclRoles);
                if (true === $continue) {
                    break;
                }
            }

            if (false === $continue) {
                Utils::go($this->_datas['noRight']);
                exit;
            }


            // check by user cannot
            if (count($this->_datas['cannotUsers'])) {
                if (Arrays::in($user->getId(), $this->_datas['cannotUsers'])) {
                    Utils::go($this->_datas['noRight']);
                    exit;
                }
            }

            // check by role cannot
            if (count($this->_datas['cannotRoles'])) {
                foreach ($this->_datas['cannotRoles'] as $idRole) {
                    foreach ($userRoles as $uRole) {
                        $uRoleId = $uRole->getRoleId();
                        if ($idRole == $uRoleId) {
                            Utils::go($this->_datas['noRight']);
                            exit;
                        }
                    }
                }
            }

            // check by user can
            if (count($this->_datas['canUsers'])) {
                if (Arrays::in($user->getId(), $this->_datas['canUsers'])) {
                    return $this;
                }
            }

            // check by role can
            if (count($this->_datas['canRoles'])) {
                foreach ($this->_datas['canRoles'] as $idRole) {
                    foreach ($userRoles as $uRole) {
                        $uRoleId = $uRole->getRoleId();
                        if ($idRole == $uRoleId) {
                            return $this;
                        }
                    }
                }
            }

            // check if admin Role
            foreach ($userRoles as $uRole) {
                $idRole = $uRole->getRoleId();
                if ($idRole == $adminRole->getId()) {
                    return $this;
                }
            }
        }
    }
