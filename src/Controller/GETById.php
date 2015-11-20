<?php
/**
 * Copyright 2015 Spafaridis Xenofon
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace Phramework\JSONAPI\Controller;

use \Phramework\Models\Request;
use \Phramework\Exceptions\RequestException;

/**
 * DELETE
 * @package JSONAPI
 * @since 1.0.0
 * @license https://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 * @author Spafaridis Xenophon <nohponex@gmail.com>
 */
abstract class GETById extends \Phramework\JSONAPI\Controller\Relationships
{

    /**
     * handles GETById requests
     * @param  array  $params                          Request parameters
     * @param  integer|string $id                      Requested resource's id
     * @param  string $modelClass                      Resource's primary model
     * to be used
     * @param  array $additionalGetArguments           [Optional] Array with any
     * additional arguments that the primary data is requiring
     * @param  array $additionalRelationshipsArguments [Optional] Array with any
     * additional arguemnt primary data's relationships are requiring
     */
    protected static function handleGETByid(
        $params,
        $id,
        $modelClass,
        $additionalGetArguments = [],
        $additionalRelationshipsArguments = []
    ) {
        //Rewrite resource's id
        $id = Request::requireId($params);

        $data = call_user_func_array(
            [
                $modelClass,
                $modelClass::GET_BY_PREFIX . ucfirst($modelClass::getIdAttribute())
            ],
            array_merge([$id], $additionalGetArguments)
        );

        //Check if resource exists
        static::exists($data);

        $requestInclude = static::getRequestInclude($params);

        $includedData = $modelClass::getIncludedData(
            $data,
            $requestInclude,
            $additionalRelationshipsArguments
        );

        static::viewData(
            $data,
            ['self' => $modelClass::getSelfLink($id)],
            null,
            (empty($requestInclude) ? null : $includedData)
        );
    }
}
