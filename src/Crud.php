<?php
    /**
     * Crud class
     * @author      Gerald Plusquellec
     */
    namespace Thin;
    class Crud
    {
        private $_em;
        public static $dataTypes = array(
            'numeric' => array(
                'tinyint', 'bool', 'smallint', 'int',
                'numeric', 'int4', 'integer', 'mediumint', 'bigint',
                'decimal', 'float', 'double'
            ),
            'text' => array(
                'char', 'bpchar', 'varchar',
                'smalltext', 'text', 'mediumtext', 'longtext'
            ),
            'time' => array('date', 'datetime', 'timestamp')
        );
        private $_config;

        public function __construct($em)
        {
            $this->_em = $em;
            $this->_config = include(CONFIG_PATH . DS . 'crud.php');
        }

        public static function get($path)
        {
            $config = include(CONFIG_PATH . DS . 'crud.php');
            $now = array();
            $path = repl('crud.', '', $path);
            list($model, $seg) = explode('.', $path, 2);
            return Arrays::exists($model, $config) ? $config[$model][$seg] : null;
        }

        public function create($data)
        {
            if (is_object($data)) {
                $data = (array) $data;
            }

            if (!is_array($data)) {
                throw new Exception("You must provide an array to create a row.");
            }

            return $this->_em->create($data);
        }

        public function read($id)
        {
            $row = $this->_em->find($id)->toArray();
            return $row;
        }

        public function update($id, $data)
        {
            $row = $this->_em->find($id);

            if (is_object($data)) {
                $data = (array) $data;
            }

            if (!is_array($data)) {
                throw new Exception("You must provide an array to update a row.");
            }

            return $row->create($data);

        }

        public function delete($id)
        {
            return $this->_em->find($id)->delete();
        }

        public static function displayBoolNum($boolNum)
        {
            if (1 == $boolNum) {
                return 'Oui';
            } elseif (0 == $boolNum) {
                return 'Non';
            }
            return '';
        }

        public static function closure($code)
        {
            $file = CACHE_PATH . DS . sha1($code) . '.php';
            $code = '<?php ' . NL . 'echo ' . $code . ';' . NL;
            File::put($file, $code);
            ob_start();
            include $file;
            $content = ob_get_contents();
            ob_end_clean();
            File::delete($file);
            return $content;
        }

        public static function internalFunction($function)
        {
            return @eval('return ' . $function . ';');
        }

        public static function pagination(Paginator $paginator)
        {
            ob_start();
            $tpl = LIBRARIES_PATH . DS . 'Thin' . DS . 'Crud' . DS . 'paginator.phtml';
            include($tpl);
            $content = ob_get_contents();
            ob_end_clean();
            return $content;
        }

        public static function checkEmpty($field)
        {
            $request = request();
            $value = isset($request->$field) ? $request->$field : null;
            return (empty($value)) ? '' : $value;
        }

        public static function defaultConfig($em)
        {
            $config = array();
            $config['fields'] = array();
            foreach ($em->fieldsSave() as $field) {
                $config['fields'][$field] = array(
                    'label'         => ucwords(repl('_', ' ', $field)),
                    'content'       => '',
                    'contentSearch' => '',
                    'sortable'      => true,
                    'searchable'    => true,
                    'required'      => false,
                    'onList'        => true,
                    'onExport'      => true,
                    'onView'        => true,
                    'options'       => null,
                    'fieldType'     => 'text',
                );
            }

            $baseConfig = array(
                'addable'                   => true,
                'editable'                  => true,
                'deletable'                 => true,
                'duplicable'                => true,
                'viewable'                  => true,
                'pagination'                => true,
                'titleList'                 => 'Liste de '. $em->_getTable(),
                'titleAdd'                  => 'Ajouter un enregistrement',
                'titleEdit'                 => 'Mettre à jour un enregistrement',
                'titleDelete'               => 'Supprimer un enregistrement',
                'titleView'                 => 'Afficher un enregistrement',
                'noResultMessage'           => 'Aucun enregistrement à afficher',
                'itemsByPage'               => 20,
                'search'                    => true,
                'order'                     => true,
                'defaultOrder'              => $em->pk(),
                'defaultOrderDirection'     => 'ASC',
                'export'                    => array('excel', 'csv', 'pdf', 'json'),
            );

            $config = $config + $baseConfig;

            return $config;
        }

        public static function getSelectFromVocabulary($key, array $vocabulary, $selectName = 'selectName')
        {
            $select = '<select id="' . $selectName . '">' . NL;
            $select .= '<option value="crudNothing">Choisir</option>' . NL;
            foreach ($vocabulary as $key => $value) {
                $select .= '<option value="' . $key . '">'. Html\Helper::display($value) . '</option>' . NL;
            }
            $select .= '</select>' . NL;
            return $select;
        }

        public static function getDataFromKey($key, $model, $field, $order = null)
        {
            $array = array();
            $em = new $model;
            $emKey = $em->_getEmFromKey($key);

            $fields = $emKey->fields();

            $array[''] = 'Choisir';
            if (is_array($field)) {
                $data = $emKey->order($order)->fetch()->select();
            } else {
                $data = $emKey->order($field)->fetch()->select();
            }
            if (null !== $data) {
                foreach ($data as $row) {
                    if (!is_array($field)) {
                        $getter = 'get' . Inflector::camelize($field);
                        $value = $row->$getter();
                    } else {
                        $value = array();
                        foreach ($field as $tmpField) {
                            if (!strstr($tmpField, '%%')) {
                                if (in_array($tmpField, $fields)) {
                                    $getter = 'get' . Inflector::camelize($tmpField);
                                    array_push($value, $row->$getter());
                                } else {
                                    array_push($value, $tmpField);
                                }
                            } else {
                                list($tmpField, $fn) = explode('%%', $tmpField, 2);
                                array_push($value, $row->$tmpField()->$fn());
                            }
                        }
                        $value = implode(' ', $value);
                    }
                    $array[$row->getId()] = Html\Helper::display($value);
                }
            }
            return $array;
        }

        public static function getSelectFromKey($key, $model, $field, $selectName = 'selectName', $order = null)
        {
            $select = '<select id="' . $selectName . '">' . NL;
            $select .= '<option value="crudNothing">Choisir</option>' . NL;
            $em = new $model;
            $emKey = $em->_getEmFromKey($key);

            $fields = $emKey->fields();

            if (is_array($field)) {
                $data = $emKey->order($order)->fetch()->select();
            } else {
                $data = $emKey->order($field)->fetch()->select();
            }
            if (null !== $data) {
                foreach ($data as $row) {
                    if (!is_array($field)) {
                        $getter = 'get' . Inflector::camelize($field);
                        $value = $row->$getter();
                    } else {
                        $value = array();
                        foreach ($field as $tmpField) {
                            if (!strstr($tmpField, '%%')) {
                                if (in_array($tmpField, $fields)) {
                                    $getter = 'get' . Inflector::camelize($tmpField);
                                    array_push($value, $row->$getter());
                                } else {
                                    array_push($value, $tmpField);
                                }
                            } else {
                                list($tmpField, $fn) = explode('%%', $tmpField, 2);
                                array_push($value, $row->$tmpField()->$fn());
                            }
                        }
                        $value = implode(' ', $value);
                    }
                    $select .= '<option value="' . $row->getId() . '">'. Html\Helper::display($value) . '</option>' . NL;
                }
            }
            $select .= '</select>' . NL;
            return $select;
        }

        public static function makeQueryDisplay($queryJs, $em)
        {
            $config  = static::get('crud.' . get_class($em) . '.info');
            if (null === $config) {
                $config = static::defaultConfig($em);
            }

            $fields = $config['fields'];

            $queryJs = substr($queryJs, 9, -2);
            $query = repl('##', ' AND ', $queryJs);
            $query = repl('%%', ' ', $query);

            $query = repl('NOT LIKE', 'ne contient pas', $query);
            $query = repl('LIKESTART', 'commence par', $query);
            $query = repl('LIKEEND', 'finit par', $query);
            $query = repl('LIKE', 'contient', $query);
            $query = repl('%', '', $query);

            foreach ($fields as $field => $fieldInfos) {
                if (strstr($query, $field)) {
                    if (strlen($fieldInfos['content'])) {
                        $seg = Utils::cut($field, " '", $query);
                        $segs = explode(" '", $query);
                        for ($i = 0 ; $i < count($segs) ; $i++) {
                            $seg = trim($segs[$i]);
                            if (strstr($seg, $field)) {
                                $goodSeg = trim($segs[$i + 1]);
                                list($oldValue, $dummy) = explode("'", $goodSeg, 2);
                                $content = repl(array('##self##', '##em##', '##field##'), array($oldValue, $em, $field), $fieldInfos['content']);
                                $value = Html\Helper::display(static::internalFunction($content));
                                $newSeg = repl("$oldValue'", "$value'", $goodSeg);
                                $query = repl($goodSeg, $newSeg, $query);
                            }
                        }
                    }
                    $query = repl($field, Inflector::lower($fieldInfos['label']), $query);
                }
            }
            $query = repl('=', 'vaut', $query);
            $query = repl('<', 'plus petit que', $query);
            $query = repl('>', 'plus grand que', $query);
            $query = repl('>=', 'plus grand ou vaut', $query);
            $query = repl('<=', 'plus petit ou vaut', $query);
            $query = repl(' AND ', ' et ', $query);
            $query = repl(" '", ' <span style="color: #ffdd00;">', $query);
            $query = repl("'", '</span>', $query);

            return $query;
        }

        public static function makeQueryDataDisplay($queryJs, $type)
        {
            $settings   = Data::$_settings[$type];
            $fields     = Data::$_fields[$type];

            $queryJs    = substr($queryJs, 9, -2);
            $query      = repl('##', ' AND ', $queryJs);
            $query      = repl('%%', ' ', $query);

            $query = repl('NOT LIKE', 'ne contient pas', $query);
            $query = repl('LIKESTART', 'commence par', $query);
            $query = repl('LIKEEND', 'finit par', $query);
            $query = repl('LIKE', 'contient', $query);
            $query = repl('%', '', $query);

            foreach ($fields as $field => $fieldInfos) {
                $label = (Arrays::exists('label', $fieldInfos)) ? $fieldInfos['label'] : ucfirst(\Thin\Inflector::lower($field));
                if(Arrays::exists('contentList', $fieldInfos)) {
                    $segs = explode(" '", $query);
                    for ($i = 0 ; $i < count($segs) ; $i++) {
                        $seg = trim($segs[$i]);
                        if (strstr($seg, $field)) {
                            $goodSeg = trim($segs[$i + 1]);
                            list($oldValue, $dummy) = explode("'", $goodSeg, 2);
                            $method = Arrays::first($fieldInfos['contentList']);
                            $value = \ThinHelper\Html::$method($oldValue, $fieldInfos['contentList'][1], Arrays::last($fieldInfos['contentList']));
                            $newSeg = repl("$oldValue'", "$value'", $goodSeg);
                            $query = repl($goodSeg, $newSeg, $query);
                        }
                    }
                }
                if (strstr($query, $field)) {
                    $query = repl($field, Inflector::lower($label), $query);
                }
            }
            $query = repl('=', 'vaut', $query);
            $query = repl('<', 'plus petit que', $query);
            $query = repl('>', 'plus grand que', $query);
            $query = repl('>=', 'plus grand ou vaut', $query);
            $query = repl('<=', 'plus petit ou vaut', $query);
            $query = repl(' AND ', ' et ', $query);
            $query = repl(" '", ' <span style="color: #ffdd00;">', $query);
            $query = repl("'", '</span>', $query);

            return $query;
        }

        public static function makeQuery($queryJs, $em)
        {
            $queryJs = substr($queryJs, 9, -2);

            $prefix = $em->_getDbName() . '.' . $em->_getTable() . '.';

            $query = repl('##', ' AND ' . $prefix, $prefix . $queryJs);
            $query = repl('%%', ' ', $query);
            $query = repl('LIKESTART', 'LIKE', $query);
            $query = repl('LIKEEND', 'LIKE', $query);
            return $query;
        }

        public static function exportExcel($data, $em)
        {
            $config  = static::get('crud.' . get_class($em) . '.info');
            if (null === $config) {
                $config = static::defaultConfig($em);
            }

            $fields = $config['fields'];

            $excel = '<html xmlns:o="urn:schemas-microsoft-com:office:office"
    xmlns:x="urn:schemas-microsoft-com:office:excel"
    xmlns="http://www.w3.org/TR/REC-html40">

        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
            <meta name="ProgId" content="Excel.Sheet">
            <meta name="Generator" content="Microsoft Excel 11">
            <style id="Classeur1_17373_Styles">
            <!--table
                {mso-displayed-decimal-separator:"\,";
                mso-displayed-thousand-separator:" ";}
            .xl1517373
                {padding-top:1px;
                padding-right:1px;
                padding-left:1px;
                mso-ignore:padding;
                color:windowtext;
                font-size:10.0pt;
                font-weight:400;
                font-style:normal;
                text-decoration:none;
                font-family:Arial;
                mso-generic-font-family:auto;
                mso-font-charset:0;
                mso-number-format:General;
                text-align:general;
                vertical-align:bottom;
                mso-background-source:auto;
                mso-pattern:auto;
                white-space:nowrap;}
            .xl2217373
                {padding-top:1px;
                padding-right:1px;
                padding-left:1px;
                mso-ignore:padding;
                color:#FFFF99;
                font-size:10.0pt;
                font-weight:700;
                font-style:normal;
                text-decoration:none;
                font-family:Arial, sans-serif;
                mso-font-charset:0;
                mso-number-format:General;
                text-align:center;
                vertical-align:bottom;
                background:#003366;
                mso-pattern:auto none;
                white-space:nowrap;}
            -->
            </style>
        </head>

            <body>
            <!--[if !excel]>&nbsp;&nbsp;<![endif]-->

            <div id="Classeur1_17373" align="center" x:publishsource="Excel">

            <table x:str border="0" cellpadding="0" cellspacing="0" width=640 style="border-collapse:
             collapse; table-layout: fixed; width: 480pt">
             <col width="80" span=8 style="width: 60pt">
             <tr height="17" style="height:12.75pt">
              ##headers##
             </tr>
             ##content##
            </table>
            </div>
        </body>
    </html>';
            $tplHeader = '<td class="xl2217373">##value##</td>';
            $tplData = '<td>##value##</td>';

            $headers = array();

            foreach ($fields as $field => $fieldInfos) {
                if (true === $fieldInfos['onExport']) {
                    $label = $fieldInfos['label'];
                    $headers[] = Html\Helper::display($label);
                }
            }
            $xlsHeader = '';
            foreach ($headers as $header) {
                $xlsHeader .= repl('##value##', $header, $tplHeader);
            }
            $excel = repl('##headers##', $xlsHeader, $excel);

            $xlsContent = '';
            foreach ($data as $item) {
                $xlsContent .= '<tr>';
                foreach ($fields as $field => $fieldInfos) {
                    if (true === $fieldInfos['onExport']) {
                        $content = $fieldInfos['content'];
                        $getter = 'get' . Inflector::camelize($field);
                        $value = $item->$getter();
                        if (strstr($content, '##self##') || strstr($content, '##em##')) {
                            $content = repl(array('##self##', '##em##', '##field##'), array($value, $em, $field), $content);
                            $value = static::internalFunction($content);
                        }
                        if (empty($value)) {
                            $value = '&nbsp;';
                        }
                        $xlsContent .= repl('##value##', Html\Helper::display($value), $tplData);
                    }
                }
                $xlsContent .= '</tr>';
            }

            $excel = repl('##content##', $xlsContent, $excel);


            $redirect = URLSITE . 'file.php?type=xls&name=' . ('extraction_' . $em->_getTable() . '_' . date('d_m_Y_H_i_s') . '.xls') . '&file=' . md5($excel);
            $cache = CACHE_PATH . DS . md5($excel) . '.xls';
            file_put_contents($cache, $excel);
            Utils::go($redirect);

            //*GP* header ("Content-type: application/excel");
            //*GP* header ('Content-disposition: attachement; filename="extraction_' . $em->_getTable() . '_' . date('d_m_Y_H_i_s') . '.xls"');
            //*GP* header("Content-Transfer-Encoding: binary");
            //*GP* header("Expires: 0");
            //*GP* header("Cache-Control: no-cache, must-revalidate");
            //*GP* header("Pragma: no-cache");
            //*GP* die($excel);
        }

        public static function exportJson($data, $em)
        {
            $config  = static::get('crud.' . get_class($em) . '.info');
            if (null === $config) {
                $config = static::defaultConfig($em);
            }

            $fields = $config['fields'];

            $array = array();

            $i = 0;

            foreach ($data as $item) {
                foreach ($fields as $field => $fieldInfos) {
                    if (true === $fieldInfos['onExport']) {
                        $content = $fieldInfos['content'];
                        $getter = 'get' . Inflector::camelize($field);
                        $value = $item->$getter();
                        if (strstr($content, '##self##') || strstr($content, '##em##')) {
                            $content = repl(array('##self##', '##em##', '##field##'), array($value, $em, $field), $content);
                            $value = static::internalFunction($content);
                        }
                        if (empty($value)) {
                            $value = null;
                        }
                        $array[$i][$fieldInfos['label']] = Html\Helper::display($value);
                    }
                }
                $i++;
            }

            $json = json_encode($array);
            header('Content-disposition: attachment; filename=extraction_' . $em->_getTable() . '_' . date('d_m_Y_H_i_s') . '.json');
            header('Content-type: application/json');
            Html\Render::json($json);
            exit;
        }

        public static function exportCsv($data, $em)
        {
            $config  = static::get('crud.' . get_class($em) . '.info');
            if (null === $config) {
                $config = static::defaultConfig($em);
            }

            $fields = $config['fields'];

            $csv = '';

            foreach ($fields as $field => $fieldInfos) {
                if (true === $fieldInfos['onExport']) {
                    $label = $fieldInfos['label'];
                    $csv .= Html\Helper::display($label) . ';';
                }
            }

            $csv = substr($csv, 0, -1);

            foreach ($data as $item) {
                $csv .= "\n";
                foreach ($fields as $field => $fieldInfos) {
                    if (true === $fieldInfos['onExport']) {
                        $content = $fieldInfos['content'];
                        $getter = 'get' . Inflector::camelize($field);
                        $value = $item->$getter();
                        if (strstr($content, '##self##') || strstr($content, '##em##')) {
                            $content = repl(array('##self##', '##em##', '##field##'), array($value, $em, $field), $content);
                            $value = static::internalFunction($content);
                        }
                        if (empty($value)) {
                            $value = '';
                        }
                        $csv .= Html\Helper::display($value) . ';';
                    }
                }
                $csv = substr($csv, 0, -1);
            }

            if (true === Utils::isUtf8($csv)) {
                $csv = utf8_decode($csv);
            }

            header("Content-type: application/excel");
            header('Content-disposition: attachement; filename="extraction_' . $em->_getTable() . '_' . date('d_m_Y_H_i_s') . '.csv"');
            header("Content-Transfer-Encoding: binary");
            header("Expires: 0");
            header("Cache-Control: no-cache, must-revalidate");
            header("Pragma: no-cache");
            die($csv);
        }

        public static function exportPdf($data, $em)
        {
            $config  = static::get('crud.' . get_class($em) . '.info');
            if (null === $config) {
                $config = static::defaultConfig($em);
            }

            $fields = $config['fields'];

            $pdf = '<html>
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
            <link href="//fonts.googleapis.com/css?family=Abel" rel="stylesheet" type="text/css" />
            <title>Extraction ' . $em->_getTable() . '</title>
            <style>
                *
                {
                    font-family: Abel, ubuntu, verdana, tahoma, arial, sans serif;
                    font-size: 11px;
                }
                h1
                {
                    text-transform: uppercase;
                    font-size: 135%;
                }
                th
                {
                    font-size: 120%;
                    color: #fff;
                    background-color: #394755;
                    text-transform: uppercase;
                }
                td
                {
                    border: solid 1px #394755;
                }

                a, a:visited, a:hover
                {
                    color: #000;
                    text-decoration: underline;
                }
            </style>
        </head>
        <body>
            <center><h1>Extraction &laquo ' . $em->_getTable() . ' &raquo;</h1></center>
            <p></p>
            <table width="100%" cellpadding="5" cellspacing="0" border="0">
            <tr>
                ##headers##
            </tr>
            ##content##
            </table>
            <p>&copy; Thin 1996 - ' . date('Y') . ' </p>
        </body>
        </html>';
            $tplHeader = '<th>##value##</th>';
            $tplData = '<td>##value##</td>';

            $headers = array();

            foreach ($fields as $field => $fieldInfos) {
                if (true === $fieldInfos['onExport']) {
                    $label = $fieldInfos['label'];
                    $headers[] = Html\Helper::display($label);
                }
            }
            $pdfHeader = '';
            foreach ($headers as $header) {
                $pdfHeader .= repl('##value##', $header, $tplHeader);
            }
            $pdf = repl('##headers##', $pdfHeader, $pdf);

            $pdfContent = '';
            foreach ($data as $item) {
                $pdfContent .= '<tr>';
                foreach ($fields as $field => $fieldInfos) {
                    if (true === $fieldInfos['onExport']) {
                        $content = $fieldInfos['content'];
                        $getter = 'get' . Inflector::camelize($field);
                        $value = $item->$getter();
                        if (strstr($content, '##self##') || strstr($content, '##em##')) {
                            $content = repl(array('##self##', '##em##', '##field##'), array($value, $em, $field), $content);
                            $value = static::internalFunction($content);
                        }
                        if (empty($value)) {
                            $value = '&nbsp;';
                        }
                        $pdfContent .= repl('##value##', Html\Helper::display($value), $tplData);
                    }
                }
                $pdfContent .= '</tr>';
            }

            $pdf = repl('##content##', $pdfContent, $pdf);
            return Pdf::make($pdf, "extraction_" . $em->_getTable() . "_" . date('d_m_Y_H_i_s'), false);
        }

        public static function makeFormElement($field, $value, $fieldInfos, $em, $hidden = false)
        {
            if (true === $hidden) {
                return Form::hidden($field, $value, array('id' => $field));
            }
            $label = Html\Helper::display($fieldInfos['label']);
            $oldValue = $value;
            if (Arrays::exists('contentForm', $fieldInfos)) {
                if (!empty($fieldInfos['contentForm'])) {
                    $content = $fieldInfos['contentForm'];
                    $content = repl(array('##self##', '##field##', '##em##'), array($value, $field, $em), $content);

                    $value = static::internalFunction($content);
                }
            }
            if (true === is_string($value)) {
                $value = Html\Helper::display($value);
            }

            $type = $fieldInfos['fieldType'];
            $required = $fieldInfos['required'];

            switch ($type) {
                case 'select':
                    return Form::select($field, $value, $oldValue, array('id' => $field, 'required' => $required), $label);
                case 'password':
                    return Form::$type($field, array('id' => $field, 'required' => $required), $label);
                default:
                    return Form::$type($field, $value, array('id' => $field, 'required' => $required), $label);
            }
        }

        public static function getRoute($name, array $args = array())
        {
            $url = URLSITE . 'admin/' . $name . '/##entity##/##table##/##id##';
            foreach ($args as $k => $v) {
                $url = repl("##$k##", $v, $url);
            }
            return $url;
        }
    }
