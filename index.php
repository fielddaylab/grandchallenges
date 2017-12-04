<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (strpos($_SERVER['HTTP_HOST'], 'localhost') === false) {
  if(empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] == "off"){
    $redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header('HTTP/1.1 302 Moved Temporarily');
    header('Location: ' . $redirect);
    exit();
  }
}

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
  } else {
    echo 'Unsupported file type. Please upload a PDF file.';
    die();
  }
  return __DIR__ . '/data/' . $netid . '.' . $ext;
}

function paper_url($netid) {
  return 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/data/' . $netid . '.pdf';
}

function budget_location($netid, $file) {
  $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
  return __DIR__ . '/data/' . $netid . '-budget.' . $ext;
}

function budget_url($netid, $ext) {
  return 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/data/' . $netid . '-budget.' . ($ext === true ? 'pdf' : $ext);
}

function timeline_location($netid, $file) {
  $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
  return __DIR__ . '/data/' . $netid . '-timeline.' . $ext;
}

function timeline_url($netid, $ext) {
  return 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/data/' . $netid . '-timeline.' . ($ext === true ? 'pdf' : $ext);
}

$logged_in_netid = null;
if (array_key_exists('netid', $_SESSION)) $logged_in_netid = $_SESSION['netid'];
if (array_key_exists('uid', $_SERVER)) $logged_in_netid = $_SERVER['uid'];

if ($logged_in_netid !== null) {
  $loc = data_location($logged_in_netid);
  if (file_exists($loc)) {
    $user_data = json_decode(file_get_contents($loc), true);
  } else {
    $user_data = array();
  }
} else {
  $user_data = array();
}

function save_user_data($data) {
  global $logged_in_netid;
  file_put_contents(data_location($logged_in_netid), json_encode($data));
}

$meeting_code = 'discoball';

function can_access($page) {
  global $user_data, $meeting_code, $logged_in_netid;
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
    case           'save-paper': $level = 6; break;
    case                 'gala': $level = 7; break;
    default                    : return true;
  }
  if ($level >= 1 && $logged_in_netid === null) return false;
  if ($level >= 2 && $user_data['code'] !== $meeting_code) return false;
  if ($level >= 3 &&
    (  !array_key_exists('bio', $user_data)
    || !array_key_exists('project_description', $user_data)
    || !array_key_exists('project_title', $user_data)
    )) return false;
  if ($level >= 4 && !array_key_exists('team', $user_data)) return false;
  if ($level >= 5 && !array_key_exists('support', $user_data)) return false;
  if ($level >= 6 && !array_key_exists('line', $user_data)) return false;
  if ($level >= 7 &&
    (  !array_key_exists('submitted', $user_data)
    || !$user_data['submitted']
    || !array_key_exists('submitted_budget', $user_data)
    || !$user_data['submitted_budget']
    || !array_key_exists('submitted_timeline', $user_data)
    || !$user_data['submitted_timeline']
    || !$user_data['certify_complete']
    || !$user_data['certify_team']
    )) return false;
  return true;
}

function render_page($twig_name) {
  global $user_data, $meeting_code, $logged_in_netid;
  $loader = new Twig_Loader_Filesystem('templates/');
  $twig = new Twig_Environment($loader);
  echo $twig->render($twig_name, array(
    'netid' => $logged_in_netid,
    'data' => $user_data,
    'paper_status' =>
      (array_key_exists('submitted', $user_data) && $user_data['submitted'])
      ? 'Proposal uploaded'
      : 'Proposal not uploaded',
    'budget_status' =>
      (array_key_exists('submitted_budget', $user_data) && $user_data['submitted_budget'])
      ? 'Budget uploaded'
      : 'Budget not uploaded',
    'timeline_status' =>
      (array_key_exists('submitted_timeline', $user_data) && $user_data['submitted_timeline'])
      ? 'Timeline uploaded'
      : 'Timeline not uploaded',
    'color' => (array_key_exists('line', $user_data) && $user_data['line'] == 'engage')
      ? 'red'
      : 'blue',
    'correct_code' => $meeting_code,
    'paper_url' =>
      (array_key_exists('submitted', $user_data) && $user_data['submitted'])
      ? paper_url($logged_in_netid)
      : null,
    'budget_url' =>
      (array_key_exists('submitted_budget', $user_data) && $user_data['submitted_budget'])
      ? budget_url($logged_in_netid, $user_data['submitted_budget'])
      : null,
    'timeline_url' =>
      (array_key_exists('submitted_timeline', $user_data) && $user_data['submitted_timeline'])
      ? timeline_url($logged_in_netid, $user_data['submitted_timeline'])
      : null,
    'certify_complete' => array_key_exists('certify_complete', $user_data) && $user_data['certify_complete'],
    'certify_team' => array_key_exists('certify_team', $user_data) && $user_data['certify_team'],
  ));
}

function index_then_key($array, $index, $key) {
  return array_key_exists($index, $array) ? $array[$index][$key] : '';
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
    // unset cookies
    if (isset($_SERVER['HTTP_COOKIE'])) {
        $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
        foreach($cookies as $cookie) {
            $parts = explode('=', $cookie);
            $name = trim($parts[0]);
            setcookie($name, '', time()-1000);
            setcookie($name, '', time()-1000, '/');
        }
    }
    redirect_to('.');
  } else if ($parts[0] === 'login') {
    $_SESSION['netid'] = $_POST['netid'];
    redirect_to('.');
  } else if ($parts[0] === 'meeting') {
    render_page('meeting.twig');
  } else if ($parts[0] === 'save-meeting') {
    $user_data['code'] = $_POST['code'];
    save_user_data($user_data);
    redirect_to('bio');
  } else if ($parts[0] === 'bio') {
    render_page('bio.twig');
  } else if ($parts[0] === 'save-bio') {
    $user_data['bio'] = $_POST['bio'];
    $user_data['project_title'] = $_POST['project_title'];
    $user_data['project_description'] = $_POST['project_description'];
    save_user_data($user_data);
    redirect_to(isset($_POST['redirect']) && $_POST['redirect'] ? $_POST['redirect'] : 'team');
  } else if ($parts[0] === 'team') {
    render_page('team.twig');
  } else if ($parts[0] === 'save-team') {
    $user_data['team'] = json_decode($_POST['json']);
    save_user_data($user_data);
    redirect_to(isset($_POST['redirect']) && $_POST['redirect'] ? $_POST['redirect'] : 'support');
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
    render_page('paper.twig');
  } else if ($parts[0] === 'save-paper') {
    if (isset($_FILES['connect_project']) && $_FILES['connect_project']['tmp_name']) {
      rename
        ( $_FILES['connect_project']['tmp_name']
        , paper_location($logged_in_netid, $_FILES['connect_project'])
        );
      $user_data['submitted'] = true;
    }
    if (isset($_FILES['budget']) && $_FILES['budget']['tmp_name']) {
      rename
        ( $_FILES['budget']['tmp_name']
        , budget_location($logged_in_netid, $_FILES['budget'])
        );
      $ext = pathinfo($_FILES['budget']['name'], PATHINFO_EXTENSION);
      $user_data['submitted_budget'] = $ext;
    }
    if (isset($_FILES['timeline']) && $_FILES['timeline']['tmp_name']) {
      rename
        ( $_FILES['timeline']['tmp_name']
        , timeline_location($logged_in_netid, $_FILES['timeline'])
        );
      $ext = pathinfo($_FILES['timeline']['name'], PATHINFO_EXTENSION);
      $user_data['submitted_timeline'] = $ext;
    }
    $user_data['certify_complete'] = $_POST['certify_complete'];
    $user_data['certify_team'] = $_POST['certify_team'];
    save_user_data($user_data);

    if ( isset($user_data['submitted']) && $user_data['submitted']
      && isset($user_data['submitted_budget']) && $user_data['submitted_budget']
      && isset($user_data['submitted_timeline']) && $user_data['submitted_timeline']
      && $user_data['certify_complete']
      && $user_data['certify_complete']) {

      $subject = "Grand Challenges Submission";

      $body = "Thank you for submitting your proposal to Grand Challenges!";
      $body .= "\r\n";
      $body .= "\r\n" . paper_url($logged_in_netid);
      $body .= "\r\n";
      $body .= "\r\nPlease contact us with any questions at grandchallenges@education.wisc.edu.";
      $body .= "\r\n";
      $body .= "\r\nSincerely,";
      $body .= "\r\nThe Grand Challenges Team";

      $mail = new PHPMailer;
      $mail->setFrom('grandchallenges@education.wisc.edu', 'Grand Challenges');
      $mail->addAddress($logged_in_netid . "@wisc.edu", $logged_in_netid);
      $mail->Subject = $subject;
      $mail->Body = $body;
      $mail->send();

    }

    redirect_to('gala');
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
  } else if ($parts[0] === 'proposals.csv' || $parts[0] === 'transform.csv') {
    $temp = tmpfile();
    fputcsv($temp, array
      ( 'NetID'
      , 'Bio'
      , 'Project title'
      , 'Project description'
      , 'Team 1 first name'
      , 'Team 1 last name'
      , 'Team 1 email'
      , 'Team 1 contribution'
      , 'Team 2 first name'
      , 'Team 2 last name'
      , 'Team 2 email'
      , 'Team 2 contribution'
      , 'Team 3 first name'
      , 'Team 3 last name'
      , 'Team 3 email'
      , 'Team 3 contribution'
      , 'Team 4 first name'
      , 'Team 4 last name'
      , 'Team 4 email'
      , 'Team 4 contribution'
      , 'Team 5 first name'
      , 'Team 5 last name'
      , 'Team 5 email'
      , 'Team 5 contribution'
      , 'Team other members'
      , 'Need extra support?'
      , 'Line'
      , 'Proposal'
      , 'Budget'
      , 'Timeline'
      , 'Submission is final'
      ));
    foreach (scandir(__DIR__ . '/data/') as $f) {
      $file = __DIR__ . '/data/' . $f;
      if (preg_match("/\\.json$/", $file)) {
        $json = json_decode(file_get_contents($file), true);
        $netid = pathinfo($f, PATHINFO_FILENAME);
        if ($parts[0] === 'transform.csv' && $json['line'] !== 'transform') {
          continue;
        }
        fputcsv($temp, array
          ( $netid
          , $json['bio']
          , $json['project_title']
          , $json['project_description']
          , index_then_key($json['team']['community'], 0, 'first_name')
          , index_then_key($json['team']['community'], 0, 'last_name')
          , index_then_key($json['team']['community'], 0, 'email')
          , index_then_key($json['team']['community'], 0, 'bio')
          , index_then_key($json['team']['community'], 1, 'first_name')
          , index_then_key($json['team']['community'], 1, 'last_name')
          , index_then_key($json['team']['community'], 1, 'email')
          , index_then_key($json['team']['community'], 1, 'bio')
          , index_then_key($json['team']['community'], 2, 'first_name')
          , index_then_key($json['team']['community'], 2, 'last_name')
          , index_then_key($json['team']['community'], 2, 'email')
          , index_then_key($json['team']['community'], 2, 'bio')
          , index_then_key($json['team']['community'], 3, 'first_name')
          , index_then_key($json['team']['community'], 3, 'last_name')
          , index_then_key($json['team']['community'], 3, 'email')
          , index_then_key($json['team']['community'], 3, 'bio')
          , index_then_key($json['team']['community'], 4, 'first_name')
          , index_then_key($json['team']['community'], 4, 'last_name')
          , index_then_key($json['team']['community'], 4, 'email')
          , index_then_key($json['team']['community'], 4, 'bio')
          , json_encode(array_slice($json['team']['community'], 5))
          , ($json['support'] !== false ? $json['support'] : '')
          , $json['line']
          , ($json['submitted'] ? paper_url($netid) : '')
          , ($json['submitted_budget'] ? budget_url($netid, $json['submitted_budget']) : '')
          , ($json['submitted_timeline'] ? timeline_url($netid, $json['submitted_timeline']) : '')
          , ($json['certify_complete'] && $json['certify_team'] ? 'X' : '')
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
