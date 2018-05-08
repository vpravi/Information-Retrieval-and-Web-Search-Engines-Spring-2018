<?php
$file= "http://localhost:8983/solr/myexample/suggest?q=".strtolower($_GET['name_startsWith'])."&wt=json";
    $flag = 0;
    $json = file_get_contents($file);
    $object = json_decode($json);
    $SuggestionArray = array();

    foreach ($object->suggest->suggest as $key=>$value){ 
        foreach ($value->suggestions as $each){
            if ((!preg_match('/[^a-zA-Z\d]/', $each->term)) || (preg_match( '/\d/',  $each->term)))
            { 
                $suggestion = $each->term;
                $SuggestionArray[] = $suggestion; 
                $flag++;
            }
        }
    }
echo json_encode($SuggestionArray);
?>