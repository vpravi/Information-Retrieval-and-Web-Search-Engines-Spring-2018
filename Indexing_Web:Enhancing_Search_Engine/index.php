<?php
// make sure browsers see this page as utf-8 encoded HTML
header('Content-Type: text/html; charset=utf-8');
$limit = 10;

include 'SpellCorrector.php';
include_once('simple_html_dom.php');
function checkIfContains( $needle, $haystack ) {
    return preg_match( '#\b' . preg_quote( $needle, '#' ) . '\b#i', $haystack ) !== 0;
}

function formatString( $input_string ) {
    return strtolower(trim($input_string));
}

function IsNullOrEmptyString($question){
    return (!isset($question) || trim($question)==='');
}

$query_box = isset($_REQUEST['q']) ? $_REQUEST['q'] : false; 
$results = false;

if ($query_box)
{ 
  $query_box = trim($query_box);
  $no_of_words = str_word_count($query_box);
  

  if($no_of_words>1){
    $query_piece = preg_split('/\s+/', $query_box);
    for($i=0;$i<$no_of_words;$i++){
      $each_word_value = SpellCorrector::correct(trim($query_piece[$i]));
      $query .= $each_word_value." ";
    }
  }else{
    $query = SpellCorrector::correct($query_box);
  }

  
  require_once('solr-php-client-master/Apache/Solr/Service.php');

  $solr = new Apache_Solr_Service('localhost', 8983, '/solr/myexample/');

  $additionalParametersPage = array(
    'sort' => 'pageRankFile desc'
  );  

  if (get_magic_quotes_gpc() == 1) {
    $query = stripslashes($query); 
  }
  try
  { 
    $params['qt'] = '/suggest';
    $result_suggest = $solr->search($query, 0, $limit, $params);  
    $resultsSolr = $solr->search($query, 0, $limit);
    $resultsPage = $solr->search($query, 0, $limit, $additionalParametersPage);
    $params = array();
  }
  catch (Exception $e)
  {     
    die("<html><head><title>SEARCH EXCEPTION</title><body><pre>{$e->__toString()}</pre></body></html>");
  } 
}
?> 

<html>
  <head>
    <title>PHP Solr Client Example</title>
    <link href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/base/jquery-ui.css" rel="stylesheet" type="text/css"/>
      <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.5/jquery.min.js"></script>
      <script src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/jquery-ui.min.js"></script>
    <script>
        $(document).ready(function(){
          $('#q').focus();
        });

      $(document).ready(function() {
  $('#q').autocomplete({

    source: function( request, response ) {
      
      var numWordsRequest = request.term.split(" ").length; 
      if(request.term.split(" ").length > 1){
        var oldTerm = request.term.split(request.term.split(" ")[numWordsRequest-1])[0];
        var requestTerm = request.term.split(" ")[numWordsRequest-1];
      }else{
        var requestTerm = request.term;
      }

      $.ajax({
        url : 'autocomplete.php',
        dataType: 'json',
        data: {
          name_startsWith: requestTerm,
        },
        success: function( data ) {
          if (numWordsRequest>1){ 
            var reformattedResponse = data.map(function(itemString){
              return oldTerm + itemString;
            });
            response(reformattedResponse);
          }else{
            response(data);
          }
        }
      });
    },
    autoFocus: true,
    minLength: 0        
  });
      });
      </script>
  </head>
  <body>
    <div style="border: 1px solid;width: 35%;padding: 10px;margin: 10px;">
    <form accept-charset="utf-8" method="get">
      <label for="q">Search:</label>
      <input id="q" name="q" type="text" value="<?php echo htmlspecialchars($query_box, ENT_QUOTES, 'utf-8'); ?>"/>
      <input type="radio" name="algorithm" <?php if (!isset($_GET["algorithm"]) || $_GET["algorithm"]=="Lucene") echo "checked";?> value="Lucene"> Lucene
      <input type="radio" name="algorithm" <?php if (isset($_GET["algorithm"]) && $_GET["algorithm"]=="pageRank") echo "checked";?> value="pageRank"> Page Rank 
      <br><br>
      <input name="submit" type="submit"/> 
    </form>
    </div>

<?php
// display results
if(isset($_GET["submit"]))
{
  if($_GET["algorithm"] == "Lucene")
  {
    $results = $resultsSolr;
  }
  else if($_GET["algorithm"] == "pageRank")
  {
    $results = $resultsPage; 
  }
}
if ($results) 
{
  $total = (int) $results->response->numFound; 
  $start = min(1, $total);
  $end = min($limit, $total);

  $file = fopen("/Users/Vishnupriya/Sites/UrlToHtml_NBCNews.csv","r");
  $data = array();
  while(! feof($file))
    {
      $row = fgetcsv($file);
    if(isset($row[2]))
        $data[$row[0]] = $row[1] . ".html";
      else
        $data[$row[0]] = $row[1];
  }
?>
  <div style="font-size:15px; font-family:sans-serif;margin:10px">Did you mean 
    <span style="font-style: italic; color:red; text-decoration-line: inherit; font-weight: bold;">
      <?php echo $query; ?>
    </span> ?
  </div>
  <div style="font-size:15px; font-family:sans-serif;margin:10px">
    <span >Showing results for 
      <span style="font-style: italic; color:red; text-decoration-line: inherit; font-weight: bold; "><?php echo $query;?></span>
    </span>
  </div>
  <div style="font-size:15px; font-family: 'Roboto',arial,sans-serif;margin:10px;">Results <?php echo $start; ?> - <?php echo $end;?> of <?php echo $total; ?>:</div>
  <ol start="1" style="font-size:13px;padding: 0;"> 
  <?php
  foreach ($results->response->docs as $doc)
  {
  $id = $doc->id;
  $filename = basename($id);
  $snippet = "";
  $Arr = explode(" ",$query);
  $handle = fopen("/Users/Vishnupriya/Sites/crawl_data/".$filename, "r");
  $flag = 0;
  $boldedsnip = "";  
  while (($line = fgets($handle)) !== false) {
    $a = fgets($handle);
    $html_contents = str_get_html($line);
    $str = strtolower($html_contents->plaintext);
    $str = strip_tags($str);
    $array = array();
    foreach($Arr as $item){
      $First = substr($item,0,1);
      $rest = substr($item,1);
      $newitem = strtoupper($First).$rest;
      array_push($array,$newitem);
    }
      $newval = implode(" ",$array);
      if (stripos($str, $query) !== false) {
        $index = stripos($str,$query);
        $flag = 1;  
        if($index > 152){
          $start = $index - 30;
          $end = $index + min(strlen($str),70);
          $snippet = substr($str,$start,$end-$start);
          $snippet = $snippet."...";
          $snippet = str_ireplace($query, "<strong>".$query."</strong>", $snippet);
          $snippet = str_ireplace($newval, "<strong>".$newval."</strong>", $snippet);
          $boldedsnip = $boldedsnip.$snippet;
        }else if(strlen($str)>115 || $index < 30){
          $start = 0;
          $end = $index + min(strlen($str),70);
          $snippet = substr($str,$start,$end-$start);
          $snippet = $snippet."...";
          $snippet = str_ireplace($$query, "<strong>".$query."</strong>", $snippet);
          $snippet = str_ireplace($newval, "<strong>".$newval."</strong>", $snippet);
          $boldedsnip = $boldedsnip.$snippet;
        }
        else{
          if(strlen($str) <= 3){$snippet = "No Snippet Available";}
          else{
            $snippet = $str;
            $snippet = str_ireplace($query, "<strong>".$query."</strong>", $snippet);
            $snippet = str_ireplace($newval, "<strong>".$newval."</strong>", $snippet);
            $boldedsnip = $boldedsnip.$snippet;
          }
        }
      }   
    if($flag == 1){
      break;
    }
    }
  fclose($handle);

//making key value pairs of CSV for missing urls
$row = array();
$file = fopen("UrlToHtml_NBCNews.csv","r");
while (($line = fgetcsv($file)) !== FALSE) {
  $row[$line[0]]=$line[1];
}
fclose($file);

$qArr = explode(" ",$query);
$la = $doc->og_description;
  foreach($qArr as $item){
    $First = substr($item,0,1);
    $rest = substr($item,1);
    $newitem = strtoupper($First).$rest;
    $la = str_ireplace($item, "<strong>".$item."</strong>", $la);
    $la = str_ireplace($newitem, "<strong>".$newitem."</strong>", $la);
}
//retrieving missing urls from csv     
if (strlen($doc->og_url)<1) {
  $id = $doc->id;
  $key = substr($id,36);
  $url = $row[$key];
} 

?>  
<li style="margin:20px;">
<div style="font-family:Helvetica;margin-bottom:10;font-size:10px;">
  <table>   
    <tr>
      <p style="font-size:12px;font-weight: bold">
      <?php if (strlen($doc->og_url)<1) echo '<a target="_blank" href="'.$url.'">'.$doc->title.'</a>'; else if (gettype($doc->title) != "string") echo ("No Title Available"); else echo '<a target="_blank" href="'.$doc->og_url.'">'.$doc->title.'</a>' ?><br></p>
      <p style="font-size:10px;">
      <?php 
      if (strlen($boldedsnip)<1) echo '<span id="desc" style="font-size:10px;">'.$la.'</span>'; else echo '<span id="desc" style="font-size:10px;">'.$boldedsnip.'</span>'; if (strlen($la)<1) echo 'No Description Available'; ?><br></p>
      <p style="font-size:10px;">URL: <?php if (strlen($doc->og_url)<1) echo '<a target="_blank" href="'.$url.'">'.$url.'</a>'; else echo '<a target="_blank" href="'.$doc->og_url.'">'.$doc->og_url.'</a>' ?>&nbsp;&nbsp;&nbsp;ID: <?php echo $doc->id ?><br></p>
    </tr>
    <br>
  </table>
</div>
</li>
    <?php } 
    ?>
  </ol>
      <?php } 
    ?>
</body> 
</html>