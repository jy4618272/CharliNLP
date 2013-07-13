<?PHP

require_once "word.php";
require_once "recombination.php";

class parse {

function sentence($sentence) {

preg_match_all(":(.+?)\b:",$sentence." ",$matches);
$matches = $matches[0];
$nmatches = array();

$word = "";

foreach($matches as $match) {
if(strpos($match," ")===false) {
$word.=$match;
}
else
{
$nmatches[] = $word;
$word = str_replace(" ","",$match);
if($word!=="") {
$nmatches[] = $word;
$word = "";
}
}
}

$nmatches[] = $word;

//We want to look for "groups" of words.
//We're going to run a query through the database
//using only wiktionary (has the most phrases)
//(most rigerously cached)
//and we're going to use this information
//to help us find the cached information.

//INSERT LOOP TO FIND GROUPS OF WORDS.
//THIS FAILS AT THE POINT OF WHEN TBH WOULD EXPAND, AND TBH IS ALSO A PHRASE
//THIS LOOP MAY NEED TO BE RUN AT SEVERAL POINTS IN THE APPLICATION AT SOME POINT

//The "3" below says to look for phrases up to 3 words in length.
//Increasing this will increase the computing time requires by
//	(sentence length - number)x

//print_r($nmatches);

$n2matches = $nmatches;

for($nwords = 3;$nwords>1;$nwords--) {

for($wpos = 0;$wpos<=count($nmatches)-$nwords;$wpos++) {
$phrase = "";
for($a=0;$a<$nwords;$a++) {
$phrase.=$nmatches[$wpos+$a]." ";
}
$phrase = substr($phrase,0,-1);

$results=mysql::get("words","word",$phrase);
if(count($results)>0) {

foreach($results as $result) {
$json = json_decode($result["data"],true);
//print_r($result);
if($json[0]=="Phrase") {continue 2;}
}

//This is just one phrase. we need to remove the elements from nmatches,
//and place the phrase back in.

//echo $phrase."\n\r";

for($a=0;$a<$nwords;$a++) {
unset($n2matches[$wpos+$a]);
}

$n2matches[$wpos] = $phrase;

//print_r($n2matches);

}

}

}

ksort($n2matches);

$nmatches = array_values($n2matches);

$nmatches = array_reverse($nmatches);

ksort($nmatches);

$word = "end.";

foreach($nmatches as $match) {
$word = new word($match,$word);
//if($word->word=="like") {
//echo($word->word.count($word->refined)."\n\r");
//print_r($word->refined["verb"]["senses"]);
//reset($word->refined);
//}
}

//return the first word.
//all functions act on the whole linked list.
return $word;

}

function get_combination($word) {
	//$word is the first word.
	//Going to impliment this as a while loop:
	$output = array();
	$stack = array();
	$pos = $word;
	
	while(1) {
		$info = &$pos->refined;
		//echo "read ".$pos->word."\n";
		//sleep(1);
		$current = current($info);
		$output[] = $current;
		$stack[] = $pos;
		if($current["nextobj"]!=="end."&&get_class($current["nextobj"])=="word") {
			$pos = $current["nextobj"];
			continue;
		}
		break;
	}
	//We need to progress this array to the next index.
	while(1) {
		$pos = array_pop($stack);
		//echo "next in ".$pos->word."\n";
		$info = &$pos->refined;
		if(next($info)===false) {
			//echo "end in ".$pos->word."\n";
			reset($info);
			//We have reached the end of this elements array:
			//We need to check to make sure we haven't popped the whole stack:
			if(count($stack)==0) {
				//We have looped through all combinations.
				//echo "total end.\n";
				return false;
			}
			
		}
		else
		{
			//We have progressed fine, just escape this super loop.
			break;
		}
	}
	return $output;
}

}