<?PHP

require_once "recombination.php";

//recombination has probably already been included, but
//i'm including it here, because this script relies on the
//classes it defines.

$ranks=new patterns("patterns/ranks.txt");
//By defining an object, it allows for the file to be open and read,
//and stored in memory, whilst this will increase memory consumption
//it will make the apply and match methods much faster
//and it will also make the i/o on the drive decrease.

//It will return the first pattern that matches the
//sentence completely.
//it applies the patterns in the order they are listed
//in the file.

//If it doesn't match any pattern it will return 0;

function rank($sentence) {
$max = 0;
//This will just apply the ranks to it
global $ranks;
//echo get_class($ranks);
$hits = $ranks->match($sentence);
if(is_array($hits)) {
foreach($hits as $hit) {
if($hit["left"]==0&&$hit["right"]==count($sentence)-1) {
if($max<$hit["label"]) {
$max = $hit["label"];
}
}
}
}
return $max;
}
