<?php
/**
 * SSOClient.php
 * Based on TwitterLogin by David Raison, which is based on the guideline published by Dave Challis at http://blogs.ecs.soton.ac.uk/webteam/2010/04/13/254/
 * @license: LGPL (GNU Lesser General Public License) http://www.gnu.org/licenses/lgpl.html
 *
 * @file SSOClient.php
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

class SSOClientHooks {
    public static function onPersonalUrls(array &$personal_urls, Title $title) {
        global $wgSSOClient, $wgUser, $wgRequest;

        if ($wgUser->isLoggedIn()) {
            return true;
        }

        $page = Title::newFromURL($wgRequest->getVal('title', ''));
        $inExt = (null == $page || $page->isSpecialPage());
        $personal_urls['anon_sso_login'] = array(
            'text' => wfMessage('ssoclient-header-link-text')->text(),
            'active' => false,
        );
        if ($inExt) {
            $personal_urls['anon_sso_login']['href'] = Skin::makeSpecialUrlSubpage('SSOClient', 'redirect');
        } else {
            $personal_urls['anon_sso_login']['href'] = Skin::makeSpecialUrlSubpage(
                'SSOClient',
                'redirect',
                wfArrayToCGI(array('returnto' => $page))
            );
        }

        if (isset($personal_urls['anonlogin'])) {
            if($inExt) {
                $personal_urls['anonlogin']['href'] = Skin::makeSpecialUrl('Userlogin');
            }
        }
        return true;
    }
}
