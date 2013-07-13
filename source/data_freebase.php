<?PHP

//This will eventually be where the system gets information from Freebase on a
//specific noun

//It will only run on "groups" on nouns, or single noun elements.
//this means that a great deal of processing has to take place before hand
//it is also not possible for Freebase to change the structure of the linked list
//as processing will occur after the linked list has been evaluated,
//and are working only with a small subset of the linked list.


require_once "helper_mysql.php";


class freebase {

function lookup($query,$related=false) {

//We dont have contextual information avaliable currently:
if($related==true) {
return array();
}

//This is where the work starts:
//I'm just going to roll with a simple file_get_contents
//freebase will alter the HTTP status code, which could cause
//problems with 404 errors, but hopefully that will never occur :)

//Should build redundency into this, because freebase can't be relied on, nor can internet connection
//be assumed, but if there is no internet connection, then the server isn't publically accessible,
//so people can't talk to the system anyway. and freebase is now owned by Google, and their
//infrastructure seems very stable, so the chance of freebase actually going down is incredibly slim,
//and the time and code required to write the redundency would be huge because I would have to use
//a library that supports changing the timeouts to remote servers, and so i've decided i'm just going to
//assume that freebase is uncrashable.

//We'll try it without related object
//and then run the search with the related
//objects (which could be an array)
//we'll bump up the relevance score on
//related results though, to make our life easier.

//We actually need to do 2 requests:
//	-the orginal string
//	-noun

$url = "http://api.freebase.com/api/service/search?query=".$query."&threshold=20&limit=3";
if($related==true) {
$url.="&related=".rawurlencode($related);
}

$cache = mysql::get("words_freebase","url",$url);
if(count($cache)>0) {
$results = json_decode($cache[0]["data"],true);
return $results;
}


$results = freebase::exec($url);

$exact_results = array();
$fuzzy_results = array();

//print_r($results);

foreach($results as $result) {

$types = array();

foreach($result["type"] as $t) {
$types[] = $t["id"];
}

$parsed_result = array("link"=>$result["id"],"image"=>$result["image"],"types"=>$types);

if(strtolower($result["name"])==strtolower($query)) {
$exact_results[] = $parsed_result;
}

if(in_array(strtolower($query),array_map("strtolower",$result["alias"]))) {
$exact_results[] = $parsed_result;
}

$fuzzy_results[] = $parsed_result;

}

//if(count($exact_results)) {$fuzzy_results = $exact_results;}

mysql::add("words_freebase",0,$url,json_encode($fuzzy_results));

return $fuzzy_results;

}

function exec($rawurl) {
//This executes the raw request, and returns raw unprocessed data.
//echo $rawurl;
$json = file_get_contents($rawurl);
$array = json_decode($json,true);
$array = $array["result"];

return $array;
}

function sort(&$results) {
//This function sorts the list in relevance order,
//this requires a custom sorting algorithm,
uasort($results,"freebase_sort");
//No return value is required because the array is
//sorted by reference.
}

}

function freebase_sort($a,$b) {
if($a["relevance:score"]==$b["relevance:score"]) {
return 0;
}
if($a["relevance:score"]>$b["relevance:score"]) {
return 1;
}
return -1;
}