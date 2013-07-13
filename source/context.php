<?PHP

//This file just keeps and maintains contexts.

class context {

function save($context) {
$uid = uniqid('', true);
file_put_contents("contexts/".$uid,serialize($context));
return $uid;
}

function load($context) {
return unserialize(file_get_contents("contexts/".$context));
}

}