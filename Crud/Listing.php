<?php
    /**
     * Crud List class
     * @author          Gerald Plusquellec
     */
    namespace Thin\Crud;
    class Listing
    {
        private $_em;
        private $_config;
        private $_types;
        private $_desc;
        private $_request;
        private $_items;
        public  $_pagination        = null;
        public  $_search            = null;
        public  $_searchDisplay     = null;
        public  $_export            = array();

        public function __construct($em)
        {
            $this->_em      = $em;
            $this->_types   = $em->types();
            $this->_desc    = $em->describe();
            $this->_request = request();
            $this->_config  = \Thin\Crud::get('crud.' . get_class($em) . '.info');
            if (null === $this->_config) {
                $this->_config = \Thin\Crud::defaultConfig($em);
            }
        }

        public function select()
        {
            $pagination     = $this->_config['pagination'];
            $search         = $this->_config['search'];

            $whereList      = '';

            if (\Thin\Arrays::exists('whereList', $this->_config)) {
                $whereList = $this->_config['whereList'];
            }

            $order          = (!strlen($this->_request->getCrudOrder())) ? $this->_config['defaultOrder'] : $this->_request->getCrudOrder();
            $orderDirection = (!strlen($this->_request->getCrudOrderDirection())) ? $this->_config['defaultOrderDirection'] : $this->_request->getCrudOrderDirection();
            $export         = (!strlen($this->_request->getCrudTypeExport())) ? null : $this->_request->getCrudTypeExport();

            $offset         = (!strlen($this->_request->getCrudNumPage())) ? 0 : $this->_request->getCrudNumPage() * $this->_config['itemsByPage'];
            $limit          = $this->_config['itemsByPage'];

            $where          = (!strlen($this->_request->getCrudWhere())) ? '' : \Thin\Crud::makeQuery($this->_request->getCrudWhere(), $this->_em);

            $data           = $this->_em->order($order, $orderDirection)->fetch()->where($whereList)->where($where)->select();

            $count = count($data);

            if (true === $pagination) {
                $pageNumber        = ($offset / $limit < 1) ? 1 : $offset / $limit;
                $paginator         = \Thin\Paginator::make((array) $data, $count, $limit);
                $this->_items      = $paginator->getItemsByPage($pageNumber);
                $this->_pagination = \Thin\Crud::pagination($paginator);
            } else {
                $this->_items   = $data;
            }

            if (0 < $count && null !== $export) {
                $method = 'export' . ucfirst(\Thin\Inflector::lower($export));
                return \Thin\Crud::$method($data, $this->_em);
            }

            if (true === $search) {
                $this->makeSearch();
            }

            return $this;
        }

        public function render()
        {
            if (!count($this->_items)) {
                return '<div class="span4"><div class="alert alert-info"><button type="button" class="close" data-dismiss="alert">×</button>' . $this->_config['noResultMessage'] . '</div></div>';
            }

            $pagination = $this->_config['pagination'];
            $fields     = $this->_config['fields'];
            $addable    = $this->_config['addable'];
            $viewable   = $this->_config['viewable'];
            $editable   = $this->_config['editable'];
            $deletable  = $this->_config['deletable'];
            $duplicable = $this->_config['duplicable'];

            if (\Thin\Arrays::exists('export', $this->_config)) {
                $export     = $this->_config['export'];

                if (count($export)) {
                    $this->_export = $export;
                }
            }

            $order          = (!strlen($this->_request->getCrudOrder())) ? $this->_config['defaultOrder'] : $this->_request->getCrudOrder();
            $orderDirection = (!strlen($this->_request->getCrudOrderDirection())) ? $this->_config['defaultOrderDirection'] : $this->_request->getCrudOrderDirection();

            $sorted = (true === $this->_config['order']) ? 'tablesorter' : '';

            $html = '<table class="table table-striped ' . $sorted . ' table-bordered table-condensed">' . NL;
            $html .= '<thead>' . NL;
            $html .= '<tr>' . NL;
            foreach ($fields as $field => $infosField) {
                if (true === $infosField['onList']) {
                    if (true !== $infosField['sortable']) {
                        $html .= '<th class="no-sorter">' . \Thin\Html\Helper::display($infosField['label']) . '</th>' . NL;
                    } else {
                        if ($field == $order) {
                            $directionJs = ('ASC' == $orderDirection) ? 'DESC' : 'ASC';
                            $js = 'orderGoPage(\'' . $field . '\', \'' . $directionJs . '\');';
                            $html .= '<th>
                                <div onclick="' . $js . '" class="text-left field-sorting ' . \Thin\Inflector::lower($orderDirection) . '" rel="' . $field . '">
                                ' . \Thin\Html\Helper::display($infosField['label']) . '
                                </div>
                            </th>';
                        } else {
                            $js = 'orderGoPage(\'' . $field . '\', \'ASC\');';
                            $html .= '<th>
                                <div onclick="' . $js . '" class="text-left field-sorting" rel="' . $field . '">
                                ' . \Thin\Html\Helper::display($infosField['label']) . '
                                </div>
                            </th>';
                        }
                    }
                }
            }
            $html .= '<th class="no-sorter">Actions</th>' . NL;
            $html .= '</tr>' . NL;
            $html .= '</thead>' . NL;
            $html .= '<tbody>' . NL;
            foreach ($this->_items as $item) {
                $html .= '<tr>' . NL;
                foreach ($fields as $field => $infosField) {
                    if (true === $infosField['onList']) {
                        $content = $infosField['content'];
                        $options = (\Thin\Arrays::exists('options', $infosField)) ? $infosField['options'] : array();
                        if (empty($options)) {
                            $options = array();
                        }
                        if (!in_array('nosql', $options)) {
                            $getter = 'get' . \Thin\Inflector::camelize($field);
                            $value = $item->$getter();
                        } else {
                            $value = $content;
                        }
                        if (strstr($content, '##self##') || strstr($content, '##em##') || strstr($content, '##field##') || strstr($content, '##id##')) {
                            $content = repl(array('##self##', '##em##', '##field##', '##id##'), array($value, $this->_em, $field, $item->getId()), $content);
                            $value = \Thin\Crud::internalFunction($content);
                        }
                        if (empty($value)) {
                            $value = '&nbsp;';
                        }
                        $html .= '<td>'. \Thin\Html\Helper::display($value) . '</td>' . NL;
                    }
                }

                $actions = '';

                if (true === $viewable) {
                    $actions .= '<a href="'. \Thin\Crud::getRoute('view', array('id' => $item->getId(), 'entity' => $item->_getEntity(), 'table' => $item->_getTable())) . '"><i title="afficher" class="icon-file"></i></a>&nbsp;&nbsp;&nbsp;';
                }

                if (true === $editable) {
                    $actions .= '<a href="'. \Thin\Crud::getRoute('edit', array('id' => $item->getId(), 'entity' => $item->_getEntity(), 'table' => $item->_getTable())) . '"><i title="éditer" class="icon-edit"></i></a>&nbsp;&nbsp;&nbsp;';
                }

                if (true === $duplicable) {
                    $actions .= '<a href="'. \Thin\Crud::getRoute('duplicate', array('id' => $item->getId(), 'entity' => $item->_getEntity(), 'table' => $item->_getTable())) . '"><i title="dupliquer" class="icon-plus"></i></a>&nbsp;&nbsp;&nbsp;';
                }

                if (true === $deletable) {
                    $actions .= '<a href="#" onclick="if (confirm(\'Confirmez-vous la suppression de cet élément ?\')) document.location.href = \''. \Thin\Crud::getRoute('delete', array('id' => $item->getId(), 'entity' => $item->_getEntity(), 'table' => $item->_getTable())) . '\';"><i title="supprimer" class="icon-trash"></i></a>&nbsp;&nbsp;&nbsp;';
                }

                $html .= '<td class="col_plus">' . $actions . '</td>' . NL;
                $html .= '</tr>' . NL;
            }
            $html .= '</tbody>' . NL;
            $html .= '</table>' . NL;
            return $html;
        }

        protected function makeSearch()
        {
            $where = (!strlen($this->_request->getCrudWhere())) ? '' : \Thin\Crud::makeQueryDisplay($this->_request->getCrudWhere(), $this->_em);


            $search = '<div class="span10">' . NL;

            if (!empty($where)) {
                $search .= '<span class="badge badge-success">Recherche en cours : '.$where.'</span>';
                $search .= '&nbsp;&nbsp;<a class="btn btn-warning" href="#" onclick="document.location.href = document.URL;"><i class="icon-trash icon-white"></i> Supprimer cette recherche</a>&nbsp;&nbsp;';
            }
            $search .= '<button id="newCrudSearch" type="button" class="btn btn-info" onclick="$(\'#crudSearchDiv\').slideDown();$(\'#newCrudSearch\').hide();$(\'#hideCrudSearch\').show();"><i class="icon-search icon-white"></i> Effectuer une nouvelle recherche</button>';
            $search .= '&nbsp;&nbsp;<button id="hideCrudSearch" type="button" style="display: none;" class="btn btn-danger" onclick="$(\'#crudSearchDiv\').slideUp();$(\'#newCrudSearch\').show();$(\'#hideCrudSearch\').hide();">Masquer la recherche</button>';
            $search .= '<fieldset id="crudSearchDiv" style="display:none;">' . NL;

            $search .= '<hr />' . NL;

            $i = 0;
            $fieldsJs = array();
            $js = '<script type="text/javascript">' . NL;
            foreach ($this->_config['fields'] as $field => $infosField) {
                if (true === $infosField['searchable']) {
                    $fieldsJs[] = "'$field'";
                    $search .= '<div class="control-group">' . NL;
                    $search .= '<label class="control-label">' . \Thin\Html\Helper::display($infosField['label']) . '</label>' . NL;
                    $search .= '<div class="controls" id="crudControl_' . $i . '">' . NL;
                    $search .= '<select id="crudSearchOperator_' . $i . '">
                    <option value="=">=</option>
                    <option value="LIKE">Contient</option>
                    <option value="NOT LIKE">Ne contient pas</option>
                    <option value="START">Commence par</option>
                    <option value="END">Finit par</option>
                    <option value="<">&lt;</option>
                    <option value=">">&gt;</option>
                    <option value="<=">&le;</option>
                    <option value=">=">&ge;</option>
                    </select>' . NL;
                    $content = $infosField['contentSearch'];
                    if (empty($content)) {
                        $search .= '<input type="text" id="crudSearchValue_' . $i . '" value="" />';
                    } else {
                        $content = repl(array('##field##', '##em##', '##i##'), array($field, $this->_em, $i), $content);
                        $search  .= \Thin\Crud::internalFunction($content);
                    }
                    $search .= '&nbsp;&nbsp;<a class="btn" href="#" onclick="addRowSearch(\'' . $field . '\', ' . $i . '); return false;"><i class="icon-plus"></i></a>';
                    $search .= '</div>' . NL;
                    $search .= '</div>' . NL;
                    $i++;
                }
            }
            $js .= 'var searchFields = [' . implode(', ', $fieldsJs)  . ']; var numFieldsSearch = ' . ($i - 1) . ';';
            $js .= '</script>' . NL;
            $search .= '<div class="control-group">
                <div class="controls">
                    <button type="submit" class="btn btn-primary" name="Rechercher" onclick="makeCrudSearch();">Rechercher</button>
                </div>
            </div>' . NL;
            $search .= '</fieldset>' . NL;
            $search .= '</div>
        <div class="span2"></div>' . NL . $js . NL;
            $this->_search = $search;
        }
    }
