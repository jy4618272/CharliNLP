<?PHP

require_once "database.php";

//This script will store any input in the database.
//It will return a ContextID, which will help
//the computer keep track of the conversation.

//It expects a contextID as input also, in order
//to update the "linked list" pointers

//(It's not really a linked list because it's stored
// in a mysql table)

class io_input {

function input($sentence,$context) {

//echo "WORKING";

//This takes the semantics of a sentence,
//and stores it in the database.

$exact = $sentence["exact"];
$types = $sentence["types"];
$pattern = $sentence["sentence"];
list($noun1,$noun2,$verb) = $exact;

$conversations = array();

//Store the exact output:
//$conversations[] = io_input::exact($pattern,$noun1,$noun2,$verb,$context);

foreach($types as $s) {

//Loop through all semantic objects,
//in order to scan the database for existing
//io.

list($n1l,$n2l,$vl) = $s;
$conversations[] = io_input::exact($pattern,$noun1,$noun2,$verb,$n1l,$n2l,$vl,$context);
}

if(count($conversations)==0) {
$conversations[] = io_input::exact($pattern,$noun1,$noun2,$verb,"","","",$context);
}

//We now have an array of $conversations, which are the IO's
//that match this sentence.

//We want to update the leading io to point to these new linked data:
$leadio = $context["leadio"];

foreach($leadio as $lio) {
$row = database::query("io",$lio);
$response = json_decode($row->response,true);
$response = array_merge($response,$conversations);
$response = array_unique($response);
$row->response = json_encode($response);
}

global $changes;
$changes = ",'leadio':'understood: ".(implode(",",$conversations))." added to ".(implode(",",$leadio))."'";

//We want to update the conversations to they refer to themselves:
foreach($conversations as $cv) {
$row = database::query("io",$cv);
$similar = json_decode($row->similar,true);
$similar = array_merge($similar,$conversations);
$similar = array_unique($similar);
$row->similar = json_encode($similar);
}

$sentence["leadio"] = $conversations;

$context = $sentence;

return $context;

}

function exact($match,$noun1,$noun2,$verb,$n1like,$n2like,$vlike,$context) {
//echo "add";
//Search to see if something exists already:
$result = database::query("io","pattern=$match","noun1=$noun1","noun2=$noun2","verb=$verb","noun1like=$n1like","noun2like=$n2like","verblike=$vlike");

if($result == false) {

//We want to work out how the context has cascaded down to this point
//in order to recreate this.

$n1swap = "";
$n2swap = "";
$vswap = "";

if($noun1==$context["exact"][0]&&strpos($match,"%noun1%")!==false) {
$n1swap = "noun1";
}

if($noun1==$context["exact"][1]&&strpos($match,"%noun1%")!==false) {
$n1swap = "noun2";
}

if($noun2==$context["exact"][0]&&strpos($match,"%noun2%")!==false) {
$n2swap = "noun1";
}

if($noun2==$context["exact"][1]&&strpos($match,"%noun2%")!==false) {
$n2swap = "noun2";
}

if($verb==$context["exact"][3]&&strpos($match,"%verb%")!==false) {
$vswap = "verb";
}

$result = database::add("io","pattern=$match","noun1like=$n1like","noun2like=$n2like","verblike=$vlike","noun1swap=$n1swap","noun2swap=$n2swap","verbswap=$vswap","noun1=$noun1","noun2=$noun2","verb=$verb","similar=[]","response=[]");
}

return $result."";

}

}