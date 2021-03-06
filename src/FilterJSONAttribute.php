<?php
/**
 * Copyright 2015 - 2016 Xenofon Spafaridis
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
namespace Phramework\JSONAPI;

/**
 * Fields helper methods
 * @since 1.0.0
 * @license https://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 * @author Xenofon Spafaridis <nohponex@gmail.com>
 * @property-read string      $attribute
 * @property-read string      $operator
 * @property-read mixed|null  $operand
 * @property-read string      $key
 */
class FilterJSONAttribute extends FilterAttribute
{
    /**
     * @var string
     */
    protected $key;

    /**
     * FilterAttribute constructor.
     * @param string $attribute
     * @param string $key
     * @param string $operator
     * @param string $operand
     */
    public function __construct(
        $attribute,
        $key,
        $operator,
        $operand
    ) {
        parent::__construct(
            $attribute,
            $operator,
            $operand
        );

        $this->key = $key;
    }

    /**
     * @param string $name
     * @return mixed
     * @throws \Exception
     */
    public function __get($name)
    {
        switch ($name) {
            case 'key':
                return $this->key;
        }

        return parent::__get($name);
    }
}
