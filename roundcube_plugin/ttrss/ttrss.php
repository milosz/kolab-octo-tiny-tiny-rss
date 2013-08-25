<?php

/**
 * Kolab Tiny Tiny RSS Plugin
 *
 * @author Aleksander 'A.L.E.C' Machniak <machniak@kolabsys.com>
 * @author Thomas Bruederli <bruederli@kolabsys.com>
 * @licence GNU AGPL
 *
 * Configuration (see config.inc.php)
 * 
 * 
 * Modified OwnCloud Plugin
 * 
 * For description visit:
 * http://blog.sleeplessbeastie.eu/2013/06/28/kolab-how-to-integrate-tiny-tiny-rss/
 */

class ttrss extends rcube_plugin
{
    // all task excluding 'login'
   public $task = '?(?!login).*';
    // skip frames
    public $noframe = true;

    function init()
    {
        // requires kolab_auth plugin
        if (empty($_SESSION['kolab_uid'])) {
            // temporary:
            $_SESSION['kolab_uid'] = $_SESSION['username'];
            // return;
        }

        $rcmail = rcube::get_instance();

        $this->add_texts('localization/', false);

        // register task
        $this->register_task('ttrss');

        // register actions
        $this->register_action('index', array($this, 'action'));
        $this->add_hook('session_destroy', array($this, 'logout'));

        // handler for sso requests sent by the ttrss kolab_auth app
        if ($rcmail->action == 'ttrsssso' && !empty($_POST['token'])) {
            $this->add_hook('startup', array($this, 'sso_request'));
        }

        // add taskbar button
        $this->add_button(array(
            'command'    => 'ttrss',
            'class'      => 'button-ttrss',
            'classsel'   => 'button-ttrss button-selected',
            'innerclass' => 'button-inner',
            'label'      => 'ttrss.ttrss',
            ), 'taskbar');

        // add style for taskbar button (must be here) and Help UI
        $this->include_stylesheet($this->local_skin_path()."/ttrss.css");
    }

    function action()
    {
        $rcmail = rcube::get_instance();

        $rcmail->output->add_handlers(array('ttrssframe' => array($this, 'frame')));
        $rcmail->output->set_pagetitle($this->gettext('ttrss'));
        $rcmail->output->send('ttrss.ttrss');
    }

    function frame()
    {
        $rcmail = rcube::get_instance();
        $this->load_config();

        // generate SSO auth token
        if (empty($_SESSION['ttrssauth']))
            $_SESSION['ttrssauth'] = md5('ttrsssso' . $_SESSION['user_id'] . microtime() . $rcmail->config->get('des_key'));

        $src  = $rcmail->config->get('ttrss_url');
        $src .= '?kolab_auth=' . strrev(rtrim(base64_encode(http_build_query(array(
            'session' => session_id(),
            'cname'   => session_name(),
            'token'   => $_SESSION['ttrssauth'],
        ))), '='));

        return html::tag('iframe', array('id' => 'ttrssframe', 'src' => $src,
            'width' => "100%", 'height' => "100%", 'frameborder' => 0));
    }

    function logout()
    {
        $rcmail = rcube::get_instance();
        $this->load_config();

        // send logout request to ttrss
        $logout_url = $rcmail->config->get('ttrss_url') . '/backend.php?op=logout';
        $rcmail->output->add_script("new Image().src = '$logout_url';", 'foot');
    }

    function sso_request()
    {
        $response = array();
        $sign_valid = false;

        $rcmail = rcube::get_instance();
        $this->load_config();

        // check signature
        if ($hmac = $_POST['hmac']) {
            unset($_POST['hmac']);
            $postdata = http_build_query($_POST, '', '&');
            $sign_valid = ($hmac == hash_hmac('sha256', $postdata, $rcmail->config->get('ttrss_secret', '<undefined-secret>')));
        }

        // if TTRSS sent a valid auth request, return plain username and password
        if ($sign_valid && !empty($_POST['token']) && $_POST['token'] == $_SESSION['ttrssauth']) {
            $user = $_SESSION['kolab_uid']; // requires kolab_auth plugin
            $pass = $rcmail->decrypt($_SESSION['password']);
            $response = array('user' => $user, 'pass' => $pass);
        }

        echo json_encode($response);
        exit;
    }

}
