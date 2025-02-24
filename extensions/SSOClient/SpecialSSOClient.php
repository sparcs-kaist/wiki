<?php
/**
 * SpecialSSOClient.php
 * Based on MediaWiki OAuth2 Client
 * Based on TwitterLogin by David Raison, which is based on the guideline published by Dave Challis at http://blogs.ecs.soton.ac.uk/webteam/2010/04/13/254/
 * @license: LGPL (GNU Lesser General Public License) http://www.gnu.org/licenses/lgpl.html
 *
 * @file SpecialSSOClient.php
 * @ingroup SSOClient
 *
 * @author Sam Jo
 * @author Joost de Keijzer
 * @author Nischay Nahata for Schine GmbH
 *
 */

if (!defined('MEDIAWIKI')) {
    die('This is a MediaWiki extension, and must be run from within MediaWiki.');
}

class SpecialSSOClient extends SpecialPage {
    private $_state;
    
    /**
     * Required settings in global $wgSSOClient
     *
     * $wgSSOClient['id']
     * $wgSSOClient['key']
     */
    public function __construct() {
        parent::__construct('SSOClient');
    }

    // default method being called by a specialpage
    public function execute($parameter) {
        $this->setHeaders();
        switch ($parameter) {
            case 'redirect':
                $this->_redirect();
                break;
            case 'callback':
                $this->_handleCallback();
                break;
            default:
                $this->_default();
                break;
        }
    }

    private function _getAuthorizationUrl() {
        global $wgSSOClient;
        $client_id = $wgSSOClient['id'];
        $state = substr(hash('sha512', rand()), 0, 8);
        $this->_state = $state;
        return 'https://sparcssso.kaist.ac.kr/api/v2/token/require/?client_id=' . $client_id . '&state=' . $state;
    }
    
    private function _getUserInfo($code, $state) {
        global $wgRequest, $wgSSOClient;
        $state_old = $wgRequest->getSession()->get('ssostate');
        if ($state != $state_old) {
            exit('SSO state has been changed: ' . $state_old . ' -> ' . $state);
        }

        $client_id = $wgSSOClient['id'];
        $client_key = $wgSSOClient['key'];
        $timestamp = time();
        $sign = hash_hmac('md5', $code.$timestamp, $client_key);
        
        $url = 'https://sparcssso.kaist.ac.kr/api/v2/token/info/';
        $data = array(
            'client_id' => $client_id,
            'code' => $code,
            'timestamp' => $timestamp,
            'sign' => $sign
        );

        $options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data)
            )
        );
        $context  = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        return json_decode($result, true);
    }
    
    private function _redirect() {
        global $wgRequest, $wgOut;
        $wgRequest->getSession()->persist();
        $wgRequest->getSession()->set('returnto', $wgRequest->getVal('returnto'));

        // Fetch the authorization URL from the provider; this returns the
        // urlAuthorize option and generates and applies any necessary parameters
        // (e.g. state).
        $authorizationUrl = $this->_getAuthorizationUrl();

        // Get the state generated for you and store it to the session.
        $wgRequest->getSession()->set('ssostate', $this->_state);
        $wgRequest->getSession()->save();

        // Redirect the user to the authorization URL.
        $wgOut->redirect($authorizationUrl);
    }

    private function _handleCallback(){
        try {
            $userInfo = $this->_getUserInfo($_GET['code'], $_GET['state']);
        } catch (Exception $e) {
            // Failed to get the access token or user details.
            exit($e->getMessage());
        }

        $user = $this->_userHandling($userInfo);
        $user->setCookies();

        global $wgOut, $wgRequest;
        $title = null;
        $wgRequest->getSession()->persist();
        if ($wgRequest->getSession()->exists('returnto')) {
            $title = Title::newFromText($wgRequest->getSession()->get('returnto'));
            $wgRequest->getSession()->remove('returnto');
            $wgRequest->getSession()->save();
        }

        if (!$title instanceof Title || 0 > $title->mArticleID) {
            $title = Title::newMainPage();
        }
        $wgOut->redirect($title->getFullURL());
        return true;
    }

    private function _default(){
        global $wgOut, $wgUser;
        
        $wgOut->setPagetitle(wfMessage('ssoclient-login-header')->text());
        if (!$wgUser->isLoggedIn()) {
            $wgOut->addWikiMsg('ssoclient-you-can-login-to-this-wiki-with-sso');
            $wgOut->addWikiMsg('ssoclient-login-with-sso', $this->getPageTitle('redirect')->getPrefixedURL());
        } else {
            $wgOut->addWikiMsg('ssoclient-youre-already-loggedin');
        }
        return true;
    }

    protected function _userHandling($response) {
        global $wgSSOClient, $wgAuth, $wgRequest;

        $username = $response['sparcs_id'];
        $email = $response['email'];

        $user = User::newFromName($username, 'creatable');
        if (!$user) {
            throw new MWException('Could not create user with username:' . $username);
            die();
        }
        $user->setRealName($username);
        $user->setEmail($email);
        $user->load();
        if (!($user instanceof User && $user->getId())) {
            $user->addToDatabase();
            $user->confirmEmail();
        }
        $user->setToken();

        // Setup the session
        $wgRequest->getSession()->persist();
        $user->setCookies();
        $this->getContext()->setUser($user);
        $user->saveSettings();

        global $wgUser;
        $wgUser = $user;
        $sessionUser = User::newFromSession($this->getRequest());
        $sessionUser->load();
        return $user;
    }
}
