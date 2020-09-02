<?php
// Register a new OAuth app
// https://github.com/settings/applications/new
$githubClientID = '';
$githubClientSecret = '';

// The URL for this script, used as the redirect URL
// Make sure to set the redirect URL in GitHub to this value too
$baseURL = 'http://localhost:8080/';

// This is the URL we'll send the user to first to get their authorization
$githubAuthorizationEndpoint = 'https://github.com/login/oauth/authorize';

// This is the endpoint our server will request an access token from
$githubTokenEndpoint = 'https://github.com/login/oauth/access_token';

// This is the GitHub base URL we can use to make authenticated API requests
$githubBaseURL = 'https://api.github.com/';

// This is GitHub's endpoint to return info about the authenticated user
// https://docs.github.com/en/rest/reference/users#get-the-authenticated-user
$githubUserinfoEndpoint = $githubBaseURL . 'user';

// Start a session so we have a place to store things between redirects
session_start();


// Start the login process by sending the user to Github's authorization page
if(isset($_GET['action']) && $_GET['action'] == 'login') {
  unset($_SESSION['access_token']);

  // Generate a random hash and store in the session
  $_SESSION['state'] = bin2hex(random_bytes(16));

  $params = [
    'response_type' => 'code',
    'client_id' => $githubClientID,
    'redirect_uri' => $baseURL,
    'state' => $_SESSION['state']
  ];

  // Redirect the user to Github's authorization page
  header('Location: '.$githubAuthorizationEndpoint.'?'.http_build_query($params));
  die();
}

// Give us a way to log the user out of this app
if(isset($_GET['action']) && $_GET['action'] == 'logout') {
  unset($_SESSION['access_token']);
  header('Location: '.$baseURL);
  die();
}

// When Github redirects the user back here,
// there will be a "code" and "state" parameter in the query string
if(isset($_GET['code'])) {
  // Verify the state matches our stored state
  if(!isset($_GET['state']) || $_SESSION['state'] != $_GET['state']) {
    header('Location: ' . $baseURL . '?error=invalid_state');
    die();
  }

  // Exchange the authorization code for an access token
  $token = githubAPIRequest($githubTokenEndpoint, [
    'grant_type' => 'authorization_code',
    'client_id' => $githubClientID,
    'client_secret' => $githubClientSecret,
    'redirect_uri' => $baseURL,
    'code' => $_GET['code']
  ]);
  $_SESSION['access_token'] = $token['access_token'];

  // Use the access token to look up the username
  $user = githubAPIRequest($githubUserinfoEndpoint, false, [
  	'Authorization: Bearer ' . $_SESSION['access_token'],
  ]);

  $_SESSION['user_id'] = $user['id'];
  $_SESSION['username'] = $user['login'];
  $_SESSION['user'] = $user;

  header('Location: ' . $baseURL);
  die();
}

// If there is an access token in the session the user is already logged in
if(!isset($_GET['action'])) {
  if(!empty($_SESSION['access_token'])) {
    echo '<h3>Logged In</h3>';

    echo '<p>Hello, '.$_SESSION['username'].'</p>';

    echo '<pre>';
    print_r($_SESSION['user']);
   	echo '</pre>';

    echo '<p><a href="?action=logout">Log Out</a></p>';
  } else {
    echo '<h3>Not logged in</h3>';
    echo '<p><a href="?action=login">Log In</a></p>';
  }
  die();
}





// This helper function will make API requests to GitHub, setting
// the appropriate headers GitHub expects, and decoding the JSON response
function githubAPIRequest($url, $post=FALSE, $headers=[]) {
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

  if($post)
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));

  $headers = [
    'Accept: application/vnd.github.v3+json, application/json',
    'User-Agent: https://example-app.com/'
  ];

  if(isset($_SESSION['access_token']))
    $headers[] = 'Authorization: Bearer ' . $_SESSION['access_token'];

  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

  $response = curl_exec($ch);
  return json_decode($response, true);
}


