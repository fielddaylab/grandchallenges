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

function paper_location($netid, $file) {
  if ($file['type'] === 'application/pdf') {
    $ext = 'pdf';
  } else if ($file['type'] === 'application/vnd.oasis.opendocument.text') {
    $ext = 'odt';
  } else {
    echo 'Unsupported file type.<br>';
    var_dump($file);
    die();
  }
  return __DIR__ . '/data/' . $netid . '.' . $ext;
}

if (array_key_exists('netid', $_SESSION)) {
  $loc = data_location($_SESSION['netid']);
  if (file_exists($loc)) {
    $user_data = json_decode(file_get_contents($loc), true);
  } else {
    $user_data = array();
  }
} else {
  $user_data = array();
}

function save_user_data($data) {
  file_put_contents(data_location($_SESSION['netid']), json_encode($data));
}

$matched = true;
if (count($parts) === 0) {
  if (array_key_exists('netid', $_SESSION)) {
    if (!array_key_exists('info', $user_data)) {
      redirect_to('info');
    } else if (!array_key_exists('team', $user_data)) {
      redirect_to('team');
    } else if (!array_key_exists('project', $user_data)) {
      redirect_to('project');
    } else {
      redirect_to('paper');
    }
  } else {
    echo $twig->render('login.twig', array());
  }
} else if (count($parts) === 1) {
  if ($parts[0] === 'logout') {
    unset($_SESSION['netid']);
    redirect_to('.');
  } else if ($parts[0] === 'login') {
    $_SESSION['netid'] = $_POST['netid'];
    redirect_to('.');
  } else if ($parts[0] === 'info') {
    if (!array_key_exists('netid', $_SESSION)) redirect_to('.');
    echo $twig->render('info.twig', array(
      'netid' => $_SESSION['netid'],
      'data' => $user_data,
    ));
  } else if ($parts[0] === 'save-info') {
    if (!array_key_exists('netid', $_SESSION)) redirect_to('.');
    $user_data['info'] = array(
      'first_name' => $_POST['first_name'],
      'last_name' => $_POST['last_name'],
      'email' => $_POST['email'],
      'bio' => $_POST['bio'],
    );
    save_user_data($user_data);
    redirect_to('.');
  } else if ($parts[0] === 'team') {
    if (!array_key_exists('netid', $_SESSION)) redirect_to('.');
    if (!array_key_exists('info', $user_data)) redirect_to('info');
    echo $twig->render('team.twig', array(
      'netid' => $_SESSION['netid'],
      'data' => $user_data,
    ));
  } else if ($parts[0] === 'save-team') {
    if (!array_key_exists('netid', $_SESSION)) redirect_to('.');
    $user_data['team'] = json_decode($_POST['json']);
    save_user_data($user_data);
    redirect_to('.');
  } else if ($parts[0] === 'project') {
    if (!array_key_exists('netid', $_SESSION)) redirect_to('.');
    if (!array_key_exists('info', $user_data)) redirect_to('info');
    if (!array_key_exists('team', $user_data)) redirect_to('team');
    echo $twig->render('project.twig', array(
      'netid' => $_SESSION['netid'],
      'data' => $user_data,
    ));
  } else if ($parts[0] === 'save-project') {
    if (!array_key_exists('netid', $_SESSION)) redirect_to('.');
    $user_data['project'] = array(
      'title' => $_POST['title'],
      'description' => $_POST['description'],
      'network_help' => $_POST['network_help'],
    );
    save_user_data($user_data);
    redirect_to('.');
  } else if ($parts[0] === 'paper') {
    if (!array_key_exists('netid', $_SESSION)) redirect_to('.');
    if (!array_key_exists('info', $user_data)) redirect_to('info');
    if (!array_key_exists('team', $user_data)) redirect_to('team');
    if (!array_key_exists('project', $user_data)) redirect_to('project');
    echo $twig->render('paper.twig', array(
      'netid' => $_SESSION['netid'],
      'data' => $user_data,
      'status' => $user_data['submitted'] ? 'Submitted, review pending' : 'Not submitted',
    ));
  } else if ($parts[0] === 'save-paper') {
    if (!array_key_exists('netid', $_SESSION)) redirect_to('.');
    rename
      ( $_FILES['connect_project']['tmp_name']
      , paper_location($_SESSION['netid'], $_FILES['connect_project'])
      );
    $user_data['submitted'] = true;
    save_user_data($user_data);
    redirect_to('.');
  } else {
    $matched = false;
  }
} else {
  $matched = false;
}

if (!$matched) {
  redirect_to('.');
}
