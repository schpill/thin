<?php
    namespace Thin\Google;
    /**
     * Chart class
     *
     * @author      Gerald Plusquellec
     */

    class Chart
    {
        private static $_first = true;
        private static $_count = 0;

        private $_chartType;

        private $_data;
        private $_dataType;
        private $_skipFirstRow;

        /**
         * sets the chart type and updates the chart counter
         */
        public function __construct($chartType, $skipFirstRow = false)
        {
            $this->_chartType       = $chartType;
            $this->_skipFirstRow    = $skipFirstRow;

            self::$_count++;
        }

        /**
         * loads the dataset and converts it to the correct format
         */
        public function load($data, $dataType = 'json')
        {
            $this->_data = ($dataType != 'json') ? $this->dataToJson($data) : $data;
        }

        /**
         * load jsapi
         */
        private function initChart()
        {
            self::$_first = false;

            $output = '';
            // start a code block
            $output .= '<script type="text/javascript" src="https://www.google.com/jsapi"></script>' . "\n";
            $output .= '<script type="text/javascript">google.load(\'visualization\', \'1.0\', {\'packages\':[\'corechart\', \'imagepiechart\']});</script>' . "\n";

            return $output;
        }

        /**
         * draws the chart
         */

        public function draw($div, array $options = [])
        {
            $output = '';

            if (self::$_first) {
                $output .= $this->initChart();
            }

            // start a code block
            $output .= '<script type="text/javascript">' . "\n";

            // set callback function
            $output .= 'google.setOnLoadCallback(drawChart' . self::$_count . ');' . "\n";

            // create callback function
            $output .= 'function drawChart' . self::$_count . '() {' . "\n";

            $output .= 'var data = new google.visualization.DataTable(' . $this->_data . ');' . "\n";

            // set the options
            $output .= 'var options = ' . json_encode($options) . ';' . "\n";

            // create and draw the chart
            $output .= 'var chart = new google.visualization.' . $this->_chartType . '(document.getElementById(\'' . $div . '\'));' . "\n";
            $output .= 'chart.draw(data, options);' . "\n";

            $output .= '} </script>' . "\n";

            return $output;
        }

        /**
         * substracts the column names from the first and second row in the dataset
         */
        private function getColumns($data)
        {
            $cols = [];

            foreach ($data[0] as $key => $value) {
                if (is_numeric($key)){
                    if (is_string($data[1][$key])) {
                        $cols[] = ['id' => '', 'label' => $value, 'type' => 'string'];
                    } else {
                        $cols[] = ['id' => '', 'label' => $value, 'type' => 'number'];
                    }

                    $this->_skipFirstRow = true;
                } else {
                    if (is_string($value)) {
                        $cols[] = ['id' => '', 'label' => $key, 'type' => 'string'];
                    } else {
                        $cols[] = ['id' => '', 'label' => $key, 'type' => 'number'];
                    }
                }
            }

            return $cols;
        }

        /**
         * convert array data to json
         */
        private function dataToJson($data)
        {
            $cols = $this->getColumns($data);

            $rows = [];

            foreach ($data as $key => $row) {
                if ($key != 0 || !$this->_skipFirstRow) {
                    $c = [];

                    foreach ($row as $v) {
                        $c[] = ['v' => $v];
                    }

                    $rows[] = ['c' => $c];
                }
            }

            return json_encode(['cols' => $cols, 'rows' => $rows]);
        }
    }

    /* Exemple
     *
        $chart = new Chart('LineChart');

        $data = [
            'cols' => [
                ['id' => '', 'label' => 'Annee', 'type' => 'string'],
                ['id' => '', 'label' => 'Recettes', 'type' => 'number'],
                ['id' => '', 'label' => 'Revenus', 'type' => 'number']
            ],
            'rows' => [
                ['c' => [['v' => '1990'), ['v' => 150), ['v' => 100]]],
                ['c' => [['v' => '1995'), ['v' => 300), ['v' => 50]]],
                ['c' => [['v' => '2000'), ['v' => 180), ['v' => 200]]],
                ['c' => [['v' => '2005'), ['v' => 400), ['v' => 100]]],
                ['c' => [['v' => '2010'), ['v' => 300), ['v' => 600]]],
                ['c' => [['v' => '2015'), ['v' => 350), ['v' => 400]]]
            ]
        ];

        $chart->load(json_encode($data));

        $options = ['title' => 'Revenus', 'theme' => 'maximized', 'width' => 500, 'height' => 200];
        echo $chart->draw('Revenus', $options);


        // demonstration of pie chart and simple array
        $chart = new Chart('PieChart');

        $data = [
            ['champignons', 'parts'],
            ['oignons', 2],
            ['olives', 1],
            ['fromage', 4]
        ];

        $chart->load($data, 'array');

        $options = ['title' => 'pizza', 'is3D' => true, 'width' => 500, 'height' => 400];
        echo $chart->draw('Pizza', $options);
        echo '<div id="Revenus"></div>
        <div id="Pizza"></div>';
        exit;
     *
     * */
