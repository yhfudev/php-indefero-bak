<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
# ***** BEGIN LICENSE BLOCK *****
# GPL >= 2
# ***** END LICENSE BLOCK ***** */

/**
 * This class is a plugin which calls the git notification on the Jenkins API.
 */
class IDF_Plugin_SyncJenkins
{
    /**
     * Entry point of the plugin.
     */
    static public function entry($signal, &$params)
    {
        // First check for the single mandatory config variable.
        if (!Pluf::f('idf_plugin_syncjenkins_base_url', false) or
            !Pluf::f('git_remote_url', false)) {
            Pluf_Log::debug('IDF_Plugin_SyncJenkins plugin not configured.');
            return;
        }
        $plug = new IDF_Plugin_SyncJenkins();
        switch ($signal) {
        case 'gitpostupdate.php::run':
            Pluf_Log::event('IDF_Plugin_SyncJenkins', 'update');
            $plug->processPostUpdate($params['git_dir']);
            break;
        }
    }

    /**
     * POST new project to Jenkins REST API.
     *
     * @param Git dir
     * @return bool Success
     */
    function processPostUpdate($git_dir)
    {
        // Chop the ".git" and get what is left
        $pname = basename($params['git_dir'], '.git');
        $params = array('http' => array(
                'method' => 'GET',
                'user_agent' => 'Indefero Hook Sender (http://www.indefero.net)',
                'max_redirects' => 10,
                'timeout' => 15,
            ));
        $url = Pluf::f('idf_plugin_syncjenkins_base_url') . 
            '/git/notifyCommit?url=' . 
            sprintf(Pluf::f('git_remote_url'), $pname);
        Pluf_Log::debug(array('IDF_Plugin_SyncJenkins::processPostUpdate',
            'HTTP GET', array($url, $params)));
        return $this->_http_request($url, $params);
    }

    private function _http_request($url, $params)
    {
        $ctx = stream_context_create($params);
        $fp = @fopen($url, 'rb', false, $ctx);
        if (!$fp) {
            return false;
        }
        $meta = stream_get_meta_data($fp);
        @fclose($fp);
        Pluf_Log::debug(array('IDF_Plugin_SyncJenkins::_http_request',
            'HTTP RESPONSE', $meta));
        if (!isset($meta['wrapper_data'][0]) or $meta['timed_out']) {
            return false;
        }
        if (0 === strpos($meta['wrapper_data'][0], 'HTTP/1.1 2') or
            0 === strpos($meta['wrapper_data'][0], 'HTTP/1.1 3')) {
            return true;
        }
        return false;
    }
}
