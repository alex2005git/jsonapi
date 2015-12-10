<?php

namespace Phramework\JSONAPI\Viewers;

/**
 * Implementation of IViewer for jsonapi
 *
 * Sends `Content-Type: application/vnd.api+json` response to client
 *
 * JSONP Support is disabled
 * @license https://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 * @author Xenofon Spafaridis <nohponex@gmail.com>
 * @link http://jsonapi.org/
 * @sinse 1.0.0
 */
class JSONAPI implements \Phramework\Viewers\IViewer
{
    /**
     * Display output
     *
     * @param array $parameters Output to display as json
     */
    public function view($parameters)
    {
        if (!headers_sent()) {
            header('Content-Type: application/vnd.api+json;charset=utf-8');
        }
        if (!is_object($parameters)) {
            $parameters = (object)$parameters;
        }
        //include JSON API Object
        $parameters->jsonapi = (object)['version' => '1.0'];

        echo json_encode($parameters);
    }
}
