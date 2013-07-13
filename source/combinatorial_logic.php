<?PHP

//This script works out different combinations from an array input...

//This is just one combinatorial function, there are two overall,
//this one just deals with arrays, and not with objects.

function array_combinations() {

$arrays = func_get_args();
$results = array();

while(1) {

$output = array();

foreach($arrays as &$array) {
$output[] = current($array);
}

$results[] = $output;

for($a=count($arrays);$a--;$a>0) {
if(next($arrays[$a])===false) {
if($a>0) {
reset($arrays[$a]);
}
else
{
return $results;
}
}
else
{
break;
}

}

}



}

