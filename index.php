<?php

require_once __DIR__ . '/vendor/autoload.php';

session_start();

$parts = array();
foreach (explode('/', substr($_SERVER['REQUEST_URI'], strlen(dirname($_SERVER['PHP_SELF'])))) as $part) {
  if ($part !== '') {
    $parts[] = $part;
  }
}

function redirect_to($url) {
  header('Location: ' . $url);
  die();
}

$loader = new Twig_Loader_Filesystem('templates/');
$twig = new Twig_Environment($loader);

function data_location($netid) {
  return __DIR__ . '/data/' . $netid . '.json';
}

$matched = true;
if (count($parts) === 0) {
  if (array_key_exists('netid', $_SESSION)) {
    echo $twig->render('logged-in.twig', array(
      'netid' => $_SESSION['netid'],
      'data' => file_get_contents(data_location($_SESSION['netid'])),
    ));
  } else {
    echo $twig->render('login.twig', array());
  }
} else if (count($parts) === 1) {
  if ($parts[0] === 'logout') {
    unset($_SESSION['netid']);
    redirect_to('/');
  } else if ($parts[0] === 'login') {
    $_SESSION['netid'] = $_POST['netid'];
    $loc = data_location($_POST['netid']);
    if (!file_exists($loc)) {
      file_put_contents($loc, '{}');
    }
    redirect_to('/');
  } else {
    $matched = false;
  }
} else {
  $matched = false;
}

if (!$matched) {
  redirect_to('/');
}
