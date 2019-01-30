<?php

ini_set('memory_limit',-1);
include 'SpellCorrector.php';
include 'simple_html_dom.php';


// make sure browsers see this page as utf-8 encoded HTML
header('Content-Type: text/html; charset=utf-8');
$limit = 10;
$query = isset($_REQUEST['q']) ? $_REQUEST['q'] : false;
$results = false;

if($query){
        require_once('solr-php-client/Apache/Solr/Service.php');
        $solr = new Apache_Solr_Service('localhost', 8983, '/solr/test1/');
        if(get_magic_quotes_gpc() == 1){
                $query = stripslashes($query);
        }
        try{
        if(!isset($_GET['algorithm']))$_GET['algorithm']="lucene";
        if($_GET['algorithm'] == "lucene"){

             $results = $solr->search($query, 0, $limit);

        }else{

            $param = array('sort'=>'pageRankFile desc');
            $results = $solr->search($query, 0, $limit, $param);

        }

     }
        catch(Exception $e){
                die("<html><head><title>SEARCH EXCEPTION</title></head><body><pre>{$e->__toString()}</pre></body></html>");
        }
}
?>

<html>
<style type="text/css">

</style>
<head>
<link rel="stylesheet" href="http://code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
    <script src="http://code.jquery.com/jquery-1.10.2.js"></script>
    <script src="http://code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css">
 
  <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js"></script>
</head>
<body>

    
    <script>
        $(function() {
            var count=0;
            var dropdown = [];
            var get_url = "http://localhost:8983/solr/test1/suggest?indent=on&q=";
            var get_params = "&wt=json&indent=true";
            $("#q").autocomplete({
                source : function(request, response) {
                     var finalString="",before="";
                     var query = $("#q").val().toLowerCase();
                     var maxDisplay = 5;
                     var space =  query.lastIndexOf(' ');
                     if(query.length-1>space && space!=-1){
                      finalString=query.substr(space+1);
                      before = query.substr(0,space);
                    }
                    else{
                      finalString=query.substr(0);
                    }
                    var URL = get_url + finalString + get_params;

                    //console.log(URL);

                    $.ajax({
                        url : URL,
                        success : function(data) {
                            //console.log(data);
                            var js = data.suggest.suggest
                            var docs = JSON.stringify(js);
                            var jsonData = JSON.parse(docs);
                            var answer = jsonData[finalString].suggestions;

                            var j=0;
                            for (var i=0; i<answer.length; i++){
                                if (before == ""){
                                    dropdown[j] = answer[j].term
                                } else{
                                    dropdown[j] = before + " " + answer[j].term;
                                }
                                j++;
                            }
                            response(dropdown.slice(0,maxDisplay));

                        },
                        dataType : 'jsonp',
                        jsonp : 'json.wrf'
                        });
                    dropdown=[];
                },
            minLength : 1
        })
        });
    </script><!--End of jQuery-->

<div class="row justify-content-center">
<form accept-charset="utf-8" method="get" >
    
    <label for="q"><i>LA Times News Search</i></label><br>
    <input id="q" name="q" type="text" value="<?php echo htmlspecialchars($query, ENT_QUOTES, 'utf-8'); ?>"/><br>
    
    <div class="form-check">
      <label class="form-check-label" for="radio1">
        <input type="radio" class="form-check-input" id="radio1" name="algorithm" value="lucene" checked <?php if(isset($_REQUEST['algorithm']) && $_GET["algorithm"]=="lucene") echo 'checked ="checked"' ?>> Lucene
      </label>
    </div>
    <div class="form-check">
      <label class="form-check-label" for="radio2">
        <input type="radio" class="form-check-input" id="radio2" name="algorithm" value="PageRank"  <?php if(isset($_REQUEST['algorithm']) && $_GET["algorithm"]=="pagerank") echo 'checked ="checked"' ?>> PageRank
      </label>
    </div>
   
    <button type="submit" class="btn btn-primary">Submit</button>
</form>
</div>
<?php
    if ($results)
    {
        
        $query_string= $_GET['q'];

        $complete="";
        $queries=explode(" ",$query);

        if(sizeof($queries)==1){
            $complete=SpellCorrector::correct($query);
        }
        else{
            foreach ($queries as $arrayElement) {
              $temp=SpellCorrector::correct($arrayElement);
              $complete=$complete." ".$temp;
            }
        }

        //echo $complete;
        if(strtolower(trim($query_string))!=strtolower(trim($complete))){
            echo "<label>Showing results for: " . $query_string . " <br/>";
            echo "<b>Did you mean: </b><a href='index.php?q=$complete'> ".$complete."</a><b>?</b></label>";
        }


        $total = (int) $results->response->numFound;
        $start = min(1, $total);
        $end = min($limit, $total);
 
        $inputFile = file("URLtoHTML_latimes.csv");
 
        foreach ($inputFile as $line) {
            $file = str_getcsv($line);
            $fileUrlMap[$file[0]] = trim($file[1]);
        }
 
        ?>
 
        <div>Results <?php echo $start; ?> - <?php echo $end;?> of <?php echo $total; ?>:</div>
        <ol>
            <?php

            foreach ($results->response->docs as $doc)
            {
                
                //echo $key;
                $key = str_replace("/Users/vikkram/Downloads/latimes/","",$doc->id);
                //echo $key; 
                $url = $fileUrlMap[$key];

                $searchWord = $_GET['q'];
                $queryWords = explode(" ",$searchWord);
                $snippet = "";
                if (is_null($doc->og_url))
                {
                    $textContents = str_get_html(file_get_contents($url));
                } 

                else{

                    if(is_array($doc->og_url))
                        $textContents = str_get_html(file_get_contents($doc->og_url[0]));
                    else
                        $textContents =  str_get_html(file_get_contents($doc->og_url));

                }

                $maxWordCount = 0;
                
                //echo $textContents->find('p');

                //$oneplus="";

                try{


                
                    foreach ($textContents->find('p') as $sentence) {
                        
                        $wordCount = 0;

                        $sentenceLow = strtolower($sentence);
                        $defaultSnippet = strip_tags($sentence);

                        foreach ($queryWords as $word) {

                            if (!empty($word) && strpos(strtolower($sentenceLow), strtolower($word)) !==false){
                                $wordCount++;
                            }

                            
                        }

                        if ($maxWordCount < $wordCount){
                            $snippet = $defaultSnippet;
                            $maxWordCount = $wordCount; 
                        }
                        
                    }
                }

                catch(exception $e)
                {
                    print_r($textContents);
                }

               

                foreach ($queryWords as $query) {

                    if(!empty($query) && strpos(strtolower($snippet), strtolower($query)) !==false)
                    {
                        $match_pos = strpos(strtolower($snippet), strtolower($query));
                        break;
                    }


                }

                $start_pos=0;

                // Centering the query word in the snippet

                if($match_pos> 80)
                    $start_pos = $match_pos-80;

                //$end_pos= min(strlen($snippet)-1 , $start_pos+160);

                
                $end_ellipsis="";

                $end_pos=$start_pos+160;
                if(strlen($snippet)> $end_pos)
                {
                    
                    $end_ellipsis="...";
                }
                else
                    $end_pos=strlen($snippet)-1;

               
                $start_ellipsis="";
                if($start_pos > 0)
                    $start_ellipsis = "...";


                $snippet = $start_ellipsis . substr($snippet, $start_pos, $end_pos - $start_pos +1) . $end_ellipsis;



               //echo strlen($snippet);
                error_reporting(E_ALL ^ E_NOTICE);
                              
                ?>
                    <li>
                    <b>Title: <a href="<?php echo $url ?>" style="text-decoration:none;"> 
                        <?php 
                            if(is_array($doc->title))
                                echo $doc->title[0]; 
                            else 
                                echo $doc->title; ?>  </a></b><br>
                    <i><b>URL: </b><a href="<?php echo $url ?>" style="color:#A52A2A;"><?php echo $url ?></a></i><br>
                    <b>id: </b><?php echo $doc->id ?> <br>
                    
                    <b>Snippet: </b><?php echo $snippet ?>
                    </li>
                    <br>
                <?php
            }
            ?>
        </ol><?php
    }
    ?>
</body>
</html>