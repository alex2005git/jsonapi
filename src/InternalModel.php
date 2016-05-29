<?php
/**
 * Copyright 2015-2016 Xenofon Spafaridis
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

use Phramework\JSONAPI\Model\DatabaseDataSource;
use Phramework\JSONAPI\Model\Directives;
use Phramework\JSONAPI\Model\Settings;
use Phramework\Validate\ObjectValidator;

/**
 * @license https://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 * @author Xenofon Spafaridis <nohponex@gmail.com>
 * @since 3.0.0
 * @todo define prefix schema, table space for attributes
 * @todo post, patch, delete methods
 * @todo handleGet and related helper methods
 * @todo database related, schema table
 * @todo resource parsing
 * @todo links related
 * @todo endpoint related
 * @todo relationship related and included data
 */
class InternalModel
{
    use Directives;
    
    /**
     * @var string
     */
    protected $resourceType;

    /**
     * @var string
     */
    protected $idAttribute = 'id';

    /**
     * @var \stdClass
     */
    protected $relationships;

    /**
     * @var callable
     */
    protected $get;

    /**
     * @var callable
     */
    protected $post;

    /**
     * @var callable
     */
//    protected $patch;

    /**
     * @var callable
     */
//    protected $delete;

    //todo add additional request methods ?

    /**
     * @var \stdClass
     */
    protected $validationModels;

    /**
     * For settings like table, schema etc
     * @var Settings
     */
    public $settings;

    /**
     * @var ObjectValidator
     */
    public $filterValidator;

    /**
     * InternalModel constructor.
     * Will create a new internal model initialized with:
     * - defaultDirectives Page directive limit with value of getMaxPageLimit()
     * @param string $resourceType
     */
    public function __construct($resourceType)
    {
        $this->resourceType      = $resourceType;

        $this->defaultDirectives    = (object) [
            Page::class => new Page($this->getMaxPageLimit())
        ];
        $this->relationships        = new \stdClass();
        $this->filterableAttributes = new \stdClass();
        
        $model = $this; //alias
        
        $this->post = function () use ($model) {
            //todo use selected data source
            //or add these default on setDataSource if $post is not set
            return DatabaseDataSource::post(...array_merge(
                [$model],
                func_get_args()
            ));
        };

        $this->validationModels = new \stdClass();

        $this->settings = new Settings();
        
        $this->filterValidator = new ObjectValidator(
            (object) [],
            [],
            false
        );
    }

    /**
     * @param IDirective[] ...$directives
     * @return Resource[]
     * @throws \LogicException When callable get property is not set
     */
    public function get(IDirective ...$directives) : array
    {
        $get = $this->get;

        //If callable get property is not set
        if ($get === null) {
            throw new \LogicException('Method "get" is not implemented');
        }

        return $get(...$directives);
    }

    /**
     * @param string|string[] $id
     * @param IDirective[] ...$directives
     * @return Resource|\stdClass|null
     * @todo rewrite cache from scratch
     */
    public function getById($id, IDirective ...$directives)
    {
        $collectionObject = new \stdClass();

        if (FALSE && !is_array($id) && ($cached = static::getCache($id)) !== null) {
            //Return a single resource immediately if cached
            return $cached;
        } elseif (is_array($id)) {
            $id = array_unique($id);

            $originalId = $id;

            foreach ($originalId as $resourceId) {
                $collectionObject->{$resourceId} = null;
                if (FALSE && ($cached = static::getCache($resourceId)) !== null) {
                    $collectionObject->{$resourceId} = $cached;
                    //remove $resourceId from id array, so we wont request the same item again,
                    //but it will be returned in $collectionObject
                    $id = array_diff($id, [$resourceId]);
                }
            }

            //If all ids are already available from cache, return immediately
            if (count($id) === 0) {
                return $collectionObject;
            }
        }

        $passedfilter = array_filter(
            $directives,
            function ($directive) {
                return get_class($directive) == Filter::class;
            }
        );

        $defaultFilter = $this->getDefaultDirectives()->{Filter::class} ?? null;

        //Prepare filter
        $filter = new Filter(
            is_array($id)
            ? $id
            : [$id]
        ); //Force array for primary data

        if (!empty($passedfilter)) {
            //merge with passed
            $filter = Filter::merge(
                $passedfilter[0],
                $filter
            );
        } elseif ($defaultFilter !== null) {
            //merge with default
            $filter = Filter::merge(
                $defaultFilter,
                $filter
            );
        }

        //remove already set page and filter
        $toPassDirectives = array_filter(
            $directives,
            function ($directive) {
                return !in_array(
                    get_class($directive),
                    [
                        Page::class,
                        Filter::class
                    ]
                );
            }
        );

        //pass new directives
        $toPassDirectives[] = $filter;
        $toPassDirectives[] = new Page(count($id));

        $collection = $this->get(
            ...$toPassDirectives
        );

        if (!is_array($id)) {
            if (empty($collection)) {
                return null;
            }

            //Store resource
            //FALSE && static::setCache($id, $collection[0]);

            //Return a resource
            return $collection[0];
        }

        //If ids are an array
        foreach ($collection as $resource) {
            $collectionObject->{$resource->id} = $resource;
            //FALSE && static::setCache($resource->id, $resource);
        }

        unset($collection);

        return $collectionObject;
    }

    /**
     * If a validation model for request method is not found, "DEFAULT" will be used
     * @param string          $requestMethod
     * @return ValidationModel
     * @throws \DomainException If none validation model is set
     */
    public function getValidationModel(string $requestMethod = 'DEFAULT')
    {
        $key = 'DEFAULT';

        if ($requestMethod !== null
            && property_exists($this->validationModels, $requestMethod)
        ) {
            $key = $requestMethod;
        }

        if (!isset($this->validationModels->{$key})) {
            throw new \DomainException(
                'No validation model is set'
            );
        }

        return $this->validationModels->{$key};
    }

    /**
     * @param ValidationModel $validationModel
     * @param string          $requestMethod
     * @return $this
     */
    public function setValidationModel(
        $validationModel,
        string $requestMethod = 'DEFAULT'
    ) {
        $key = 'DEFAULT';

        if ($requestMethod !== null) {
            $key = $requestMethod;
        }

        $this->validationModels->{$key} = $validationModel;

        return $this;
    }

    /**
     * @return string
     */
    public function getResourceType()
    {
        return $this->resourceType;
    }

    /**
     * @param string $resourceType
     * @return $this
     */
    public function setResourceType($resourceType)
    {
        $this->resourceType = $resourceType;

        return $this;
    }

    /**
     * @param callable $get
     * @return $this
     * @todo validate callable using https://secure.php.net/manual/en/reflectionfunction.construct.php
     */
    public function setGet(callable $get)
    {
        $this->get = $get;

        return $this;
    }

    /**
     * @return string
     */
    public function getIdAttribute()
    {
        return $this->idAttribute;
    }

    /**
     * @return \stdClass
     */
    public function getRelationships()
    {
        return $this->relationships;
    }

    public function collection(
        array $records = [],
        array $directives = null,
        $flags = Resource::PARSE_DEFAULT
    ) {
        return Resource::parseFromRecords(
            $records,
            $this,
            null,//$fields,
            $flags
        );
    }

    public function resource(
        $record,
        array $directives = null,
        $flags = Resource::PARSE_DEFAULT
    ) {
        return Resource::parseFromRecord(
            $record,
            $this,
            null,//$fields,
            $flags
        );
    }

    /**
     * @return ObjectValidator
     */
    public function getFilterValidator() : ObjectValidator
    {
        return $this->filterValidator;
    }

    /**
     * @param ObjectValidator $filterValidator
     * @return $this
     */
    public function setFilterValidator(ObjectValidator $filterValidator)
    {
        $this->filterValidator = $filterValidator;

        return $this;
    }
}
