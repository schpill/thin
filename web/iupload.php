<?php

    $posturl        = "http://www.imageshack.us/upload_api.php";

    $fileupload     = $_FILES["img"]['tmp_name'];

    $isIframe       = ($_POST["iframe"]) ? true : false;
    $idarea         = $_POST["idarea"];
    $maxwidth       = is_numeric($_POST["maxwidth"]) ? $_POST["maxwidth"] : 600;
    $maxheight      = is_numeric($_POST["maxheight"]) ? $_POST["maxheight"] : 600;


    $postData = array(
        'fileupload' => "@" . $fileupload,
        'key'        => '239EFGIY052f49d3ed8c8b7f7991ec05513fb5e8',
        'optimage'   => 1,
        'optsize'    => $maxwidth . "x" . $maxheight,
        'xml'        => 'yes'
    );

    $ch = curl_init($posturl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    $result = curl_exec($ch);
    curl_close($ch);

    $xml = simplexml_load_string($result);

    if ($isIframe) {
        #use for iframe upload
        echo '<html><body>OK<script>window.parent.$("#' . $idarea . '").insertImage("' . $xml->links->image_link . '","' . $xml->links->thumb_link . '").closeModal().updateUI();</script></body></html>';
    } else {
        // use for drag & drop
        header("Content-type: text/javascript");
        if (!$xml) {
            echo '{"status":0,"msg":"Erreur de traitement"}';
        } else if (isset($xml->error)) {
            echo '{"status":0,"msg":"' . $xml->error . '"}';
        } else {
            #OK
            echo '{"status":1,"msg":"OK","image_link":"' . $xml->links->image_link . '","thumb_link":"' . $xml->links->thumb_link . '"}';
        }
    }
