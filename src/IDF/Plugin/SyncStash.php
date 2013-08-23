<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
# ***** BEGIN LICENSE BLOCK *****
# GPL >= 2
# ***** END LICENSE BLOCK ***** */

/**
 * This class is a plugin which allows to synchronise projects between
 * InDefero and Stash, calling a webhook to the Stash REST API.
 */
class IDF_Plugin_SyncStash
{
    /**
     * Entry point of the plugin.
     */
    static public function entry($signal, &$params)
    {
        // First check for the single mandatory config variable.
        if (!Pluf::f('idf_plugin_syncstash_base_url', false) or
            !Pluf::f('idf_plugin_syncstash_project', false)) {
            Pluf_Log::debug('IDF_Plugin_SyncStash plugin not configured.');
            return;
        }
        $plug = new IDF_Plugin_SyncStash();
        switch ($signal) {
        case 'IDF_Project::created':
            $plug->processProjectCreate($params['project']);
            break;
        case 'IDF_Project::preDelete':
            $plug->processProjectDelete($params['project']);
            break;
        }
    }

    /**
     * POST new project to Stash REST API.
     *
     * @param IDF_Project
     * @return bool Success
     */
    function processProjectCreate($project)
    {
        $data = json_encode(array('name' => $project->shortname));
        $params = array('http' => array(
                'method' => 'POST',
                'content' => $data,
                'user_agent' => 'Indefero Hook Sender (http://www.indefero.net)',
                'max_redirects' => 10,
                'timeout' => 15,
                'header' => 'Accept: application/json' . "\r\n" .
                    'Content-Type: application/json' . "\r\n",
            ));
        $url = Pluf::f('idf_plugin_syncstash_base_url') . 
            '/rest/api/latest/projects/' . 
            Pluf::f('idf_plugin_syncstash_project') . '/repos';
        return $this->_http_request($url, $params);
    }

    /**
     * DELETE project to Stash REST API.
     *
     * @param IDF_Project
     * @return bool Success
     */
    function processProjectDelete($project)
    {
        $params = array('http' => array(
                'method' => 'DELETE',
                'user_agent' => 'Indefero Hook Sender (http://www.indefero.net)',
                'max_redirects' => 10,
                'timeout' => 15,
                'header' => 'Accept: application/json' . "\r\n",
            ));
        $url = Pluf::f('idf_plugin_syncstash_base_url') . 
            '/rest/api/latest/projects/' . 
            Pluf::f('idf_plugin_syncstash_project') . '/repos/' .
            $project->shortname;
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
