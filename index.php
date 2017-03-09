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

function data_location($netid) {
  if (strpos($netid, '..') !== false) exit('Invalid email address.');
  return __DIR__ . '/data/' . $netid . '.json';
}

function data_simple_location() {
  return __DIR__ . '/data-simple/' . microtime() . '.json';
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

$meeting_code = 'discoball';

function can_access($page) {
  global $user_data, $meeting_code;
  switch ($page) {
    case         'save-meeting': $level = 1; break;
    case              'meeting': $level = 1; break;
    case                  'bio': $level = 2; break;
    case             'save-bio': $level = 2; break;
    case                 'team': $level = 3; break;
    case            'save-team': $level = 3; break;
    case              'support': $level = 4; break;
    case         'save-support': $level = 4; break;
    case      'save-no-support': $level = 4; break;
    case                 'line': $level = 5; break;
    case          'save-engage': $level = 5; break;
    case       'save-transform': $level = 5; break;
    case                'paper': $level = 6; break;
    case 'save-paper-transform': $level = 6; break;
    case    'save-paper-engage': $level = 6; break;
    case                 'gala': $level = 7; break;
    default                    : return true;
  }
  if ($level >= 1 && !array_key_exists('netid', $_SESSION)) return false;
  if ($level >= 2 && $user_data['code'] !== $meeting_code) return false;
  if ($level >= 3 &&
    (  !array_key_exists('bio', $user_data)
    || !array_key_exists('project-description', $user_data)
    || !array_key_exists('project-title', $user_data)
    )) return false;
  if ($level >= 4 && !array_key_exists('team', $user_data)) return false;
  if ($level >= 5 && !array_key_exists('support', $user_data)) return false;
  if ($level >= 6 && !array_key_exists('line', $user_data)) return false;
  if ($user_data['line'] === 'transform') {
    if ($level >= 7 &&
      (  !array_key_exists('submitted', $user_data)
      || !$user_data['submitted']
      )) return false;
  } else {
    if ($level >= 7 &&
      (  !array_key_exists('engage-proposal', $user_data)
      || !$user_data['engage-proposal']
      )) return false;
  }
  return true;
}

function render_page($twig_name) {
  global $user_data;
  $loader = new Twig_Loader_Filesystem('templates/');
  $twig = new Twig_Environment($loader);
  echo $twig->render($twig_name, array(
    'netid' => $_SESSION['netid'],
    'data' => $user_data,
    'paper_status' =>
      (array_key_exists('submitted', $user_data) && $user_data['submitted'])
      ? 'Submitted, review pending'
      : 'Not submitted',
    'color' => (array_key_exists('line', $user_data) && $user_data['line'] == 'engage')
      ? 'red'
      : 'blue',
  ));
}

$matched = true;
if (count($parts) === 0) {
  if (can_access('gala')) redirect_to('gala');
  if (can_access('paper')) redirect_to('paper');
  if (can_access('line')) redirect_to('line');
  if (can_access('support')) redirect_to('support');
  if (can_access('team')) redirect_to('team');
  if (can_access('bio')) redirect_to('bio');
  if (can_access('meeting')) redirect_to('meeting');
  render_page('login.twig');
} else if (count($parts) === 1) {

  if (!can_access($parts[0])) redirect_to('.');

  if ($parts[0] === 'logout') {
    unset($_SESSION['netid']);
    redirect_to('.');
  } else if ($parts[0] === 'login') {
    $_SESSION['netid'] = $_POST['netid'];
    redirect_to('.');
  } else if ($parts[0] === 'meeting') {
    render_page('meeting.twig');
  } else if ($parts[0] === 'save-meeting') {
    $user_data['code'] = $_POST['code'];
    save_user_data($user_data);
    redirect_to('meeting');
  } else if ($parts[0] === 'bio') {
    render_page('bio.twig');
  } else if ($parts[0] === 'save-bio') {
    $user_data['bio'] = $_POST['bio'];
    $user_data['project-title'] = $_POST['project-title'];
    $user_data['project-description'] = $_POST['project-description'];
    save_user_data($user_data);
    redirect_to('bio');
  } else if ($parts[0] === 'team') {
    render_page('team.twig');
  } else if ($parts[0] === 'save-team') {
    $user_data['team'] = json_decode($_POST['json']);
    save_user_data($user_data);
    redirect_to('team');
  } else if ($parts[0] === 'support') {
    render_page('support.twig');
  } else if ($parts[0] === 'save-support') {
    $user_data['support'] = $_POST['support'];
    save_user_data($user_data);
    redirect_to('line');
  } else if ($parts[0] === 'save-no-support') {
    $user_data['support'] = false;
    save_user_data($user_data);
    redirect_to('line');
  } else if ($parts[0] === 'line') {
    render_page('line.twig');
  } else if ($parts[0] === 'save-engage') {
    $user_data['line'] = 'engage';
    save_user_data($user_data);
    redirect_to('paper');
  } else if ($parts[0] === 'save-transform') {
    $user_data['line'] = 'transform';
    save_user_data($user_data);
    redirect_to('paper');
  } else if ($parts[0] === 'paper') {
    if ($user_data['line'] === 'engage') {
      render_page('paper-engage.twig');
    } else {
      render_page('paper-transform.twig');
    }
  } else if ($parts[0] === 'save-paper-engage') {
    $user_data['engage-proposal'] = $_POST['engage-proposal'];
    save_user_data($user_data);
    redirect_to('.');
  } else if ($parts[0] === 'save-paper-transform') {
    rename
      ( $_FILES['connect_project']['tmp_name']
      , paper_location($_SESSION['netid'], $_FILES['connect_project'])
      );
    $user_data['submitted'] = true;
    save_user_data($user_data);
    redirect_to('.');
  } else if ($parts[0] === 'gala') {
    render_page('gala.twig');
  } else if ($parts[0] === 'informed') {
    render_page('informed.twig');
  } else if ($parts[0] === 'submit-informed') {
    foreach (array('email', 'interests') as $k) {
      if (isset($_POST[$k])) {
        $user_data[$k] = $_POST[$k];
      }
    }
    file_put_contents(data_simple_location(), json_encode($user_data));
    redirect_to('thanks');
  } else if ($parts[0] === 'thanks') {
    render_page('thanks.twig');
  } else if ($parts[0] === 'grand-challenges.csv') {
    $temp = tmpfile();
    fputcsv($temp, array('Email', 'Idea', 'Collaborators', 'Colleagues', 'Interest'));
    foreach (scandir(__DIR__ . '/data-simple/') as $f) {
      $file = __DIR__ . '/data-simple/' . $f;
      if (preg_match("/\\.json$/", $file)) {
        $json = json_decode(file_get_contents($file), true);
        fputcsv($temp, array(
          $json['email'],
          $json['idea'],
          $json['collaborators'],
          $json['colleagues'],
          $json['interests'],
        ));
      }
    }
    fseek($temp, 0);
    header('Content-type: text/csv');
    echo fread($temp, 10000000);
  } else {
    $matched = false;
  }
} else {
  $matched = false;
}

if (!$matched) {
  redirect_to('.');
}
