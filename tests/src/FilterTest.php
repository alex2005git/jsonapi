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

use Phramework\JSONAPI\APP\Bootstrap;
use Phramework\JSONAPI\APP\Models\Article;
use Phramework\JSONAPI\APP\Models\Tag;
use Phramework\Models\Operator;
use Phramework\Phramework;
use Phramework\Validate\BooleanValidator;
use Phramework\Validate\ObjectValidator;
use Phramework\Validate\StringValidator;
use Phramework\Validate\UnsignedIntegerValidator;

/**
 * @coversDefaultClass Phramework\JSONAPI\Filter
 * @license https://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 * @author Xenofon Spafaridis <nohponex@gmail.com>
 */
class FilterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var InternalModel
     */
    protected $articleModel;
    
    public function setUp()
    {
        $this->articleModel = (new InternalModel('article'))
            ->setValidationModel(new ValidationModel(
                new ObjectValidator()
            ))->setFilterValidator(new ObjectValidator((object) [
                'id'      => new UnsignedIntegerValidator(0, 10),
                'meta' => new ObjectValidator((object) [
                    'timestamp' => new UnsignedIntegerValidator(),
                    'keywords'  => new StringValidator()
                ]),
                'updated' => new UnsignedIntegerValidator(),
                //'tag'     => new StringValidator()
            ]))->setValidationModel(new ValidationModel(new ObjectValidator(
                (object) [
                    'title'  => new StringValidator(2, 32),
                    'status' => (new BooleanValidator())
                        ->setDefault(true)
                ],
                ['title']
            )))->setFilterableAttributes((object) [
                'no-validator' => Operator::CLASS_COMPARABLE,
                'status'       => Operator::CLASS_COMPARABLE,
                'meta'         => Operator::CLASS_JSONOBJECT
                    | Operator::CLASS_NULLABLE
                    | Operator::CLASS_COMPARABLE,
                //'tag'          => Operator::CLASS_IN_ARRAY,
                'updated'      => Operator::CLASS_ORDERABLE
                    | Operator::CLASS_NULLABLE,
            ])->setRelationships((object) [
                'creator' => new Relationship(
                    (new InternalModel('user'))
                    ->setValidationModel(new ValidationModel(
                        new ObjectValidator(
                        )
                    )),
                    Relationship::TYPE_TO_ONE,
                    'creator-user_id'
                ),
                'tag' => new Relationship(
                    (new InternalModel('tag'))
                    ->setValidationModel(new ValidationModel(
                        new ObjectValidator(
                        )
                    )),
                    Relationship::TYPE_TO_MANY,
                    null,
                    (object) [
                        Phramework::METHOD_GET   => function () {},
                        Phramework::METHOD_POST  => function () {},
                        Phramework::METHOD_PATCH => function () {}
                    ]
                )
            ]);
    }

    /**
     * @covers ::__construct
     */
    public function testConstruct1()
    {
        $filter = new Filter(
            [1, 2, 3],
            (object) [
                'tag' => [1, 2]
            ]
        );
    }

    /**
     * @covers ::__construct
     */
    public function testConstruct2()
    {
        $filter = new Filter(
            [1, 2, 3],
            null
        );
    }

    /**
     * @covers ::__construct
     * @expectedException Exception
     */
    public function testConstructFailure2()
    {
        $filter = new Filter(
            [],
            (object) [
                'tag' => new \stdClass() //not an array
            ]
        );
    }

    /**
     * @covers ::__construct
     * @expectedException Exception
     */
    public function testConstructFailure3()
    {
        $filter = new Filter(
            [],
            null,
            [
                new \stdClass()
            ]
        );
    }

    /**
     * @covers ::parseFromRequest
     */
    public function testParseFromRequestPrimaryUsingIntval()
    {
        $parameters = (object) [
            'filter' => (object) [
                $this->articleModel->getResourceType() => 4
            ]
        ];

        $filter = Filter::parseFromRequest(
            $parameters,
            $this->articleModel
        );

        $this->assertInstanceOf(Filter::class, $filter);

        $this->assertEquals([4], array_values($filter->getPrimary()));
    }

    /**
     * @covers ::parseFromRequest
     */
    public function testParseFromRequestRelationshipEmpty()
    {
        $parameters = (object) [
            'filter' => (object) [
                'tag' => Operator::OPERATOR_EMPTY
            ]
        ];

        $filter = Filter::parseFromRequest(
            $parameters,
            $this->articleModel
        );

        $this->assertInstanceOf(Filter::class, $filter);

        $this->assertEquals(
            Operator::OPERATOR_EMPTY,
            $filter->getRelationships()->tag
        );
    }

    /**
     * @covers ::parseFromRequest
     */
    public function testParseFromRequestEmpty()
    {
        $filter = Filter::parseFromRequest(
            (object) [],
            $this->articleModel
        );

        $this->assertNull($filter);
    }

    /**
     * @covers ::parseFromRequest
     */
    public function testParseFromRequest()
    {
        $parameters = (object) [
            'filter' => [
                'article'   => '1, 2',
                'tag'       => '4, 5, 7',
                'creator'   => '1',
                'status'    => [true, false],
                'title'     => [
                    Operator::OPERATOR_LIKE . 'blog',
                    Operator::OPERATOR_NOT_LIKE . 'welcome'
                ],
                'updated'   => Operator::OPERATOR_NOT_ISNULL,
                'meta.keywords' => 'blog'
            ]
         ];

        $filter = Filter::parseFromRequest(
            $parameters,
            $this->articleModel
        );

        $this->assertInstanceOf(Filter::class, $filter);

        $this->assertContainsOnly(
            'string',
            $filter->getPrimary(),
            true
        );

        $this->assertInternalType(
            'object',
            $filter->getRelationships()
        );

        $this->assertInternalType(
            'array',
            $filter->getRelationships()->tag
        );

        $this->assertContainsOnly(
            'string',
            $filter->getRelationships()->tag,
            true
        );

        $this->assertContainsOnlyInstancesOf(
            FilterAttribute::class,
            $filter->getAttributes()
        );

        $this->assertSame(
            ['1', '2'],
            $filter->getPrimary()
        );

        $this->assertSame(
            ['4', '5', '7'],
            $filter->getRelationships()->tag
        );
        $this->assertSame(
            ['1'],
            $filter->getRelationships()->creator
        );

        $this->assertCount(6, $filter->getAttributes());

        $shouldContain1 = new FilterAttribute(
            'title',
            Operator::OPERATOR_LIKE,
            'blog'
        );

        $shouldContain2 = new FilterJSONAttribute(
            'meta',
            'keywords',
            Operator::OPERATOR_EQUAL,
            'blog'
        );

        $found1 = false;
        $found2 = false;

        foreach ($filter->getAttributes() as $filterAttribute) {
            if ($shouldContain1 == $filterAttribute) {
                $found1 = true;
            } elseif ($shouldContain2 == $filterAttribute) {
                $found2 = true;
            }
        }

        $this->assertTrue($found1);
        $this->assertTrue($found2);
    }

    /**
     * @covers ::parseFromRequest
     * @expectedException \Phramework\Exceptions\IncorrectParameterException
     */
    public function testParseFromRequestFailurePrimaryNotString()
    {
        $parameters = (object) [
            'filter' => [
                'article'   => [1, 2]
            ]
        ];

        Filter::parseFromRequest(
            $parameters,
            $this->articleModel
        );
    }


    /**
     * @covers ::validate
     * @expectedException \Phramework\Exceptions\IncorrectParameterException
     */
    public function testParseFromRequestFailurePrimaryToParse()
    {
        $parameters = (object) [
            'filter' => [
                $this->articleModel->getResourceType() => 10000
            ]
        ];

        Filter::parseFromRequest(
            $parameters,
            $this->articleModel
        )->validate($this->articleModel);
    }

    /**
     * @covers ::parseFromRequest
     * @expectedException \Phramework\Exceptions\IncorrectParameterException
     */
    public function testParseFromRequestFailureRelationshipNotString()
    {
        $parameters = (object) [
            'filter' => [
                'tag'   => [1, 2]
            ]
        ];

        Filter::parseFromRequest(
            $parameters,
            $this->articleModel
        )->validate($this->articleModel);
    }

    /**
     * @covers ::validate
     * @expectedException \Phramework\Exceptions\RequestException
     */
    public function testParseFromRequestFailureNotAllowedAttribute()
    {
        $parameters = (object) [
            'filter' => [
                'not-found'   => 1
            ]
        ];

        Filter::parseFromRequest(
            $parameters,
            $this->articleModel
        )->validate($this->articleModel);
    }

    /**
     * @covers ::validate
     * @expectedException Exception
     */
    public function testParseFromRequestFailureAttributeWithoutValidator()
    {
        $parameters = (object) [
            'filter' => [
                'no-validator'   => 1
            ]
        ];

        Filter::parseFromRequest(
            $parameters,
            $this->articleModel
        )->validate($this->articleModel);
    }

    /**
     * @covers ::parseFromRequest
     * @expectedException \Phramework\Exceptions\IncorrectParameterException
     */
    public function testParseFromRequestFailureAttributeIsArray()
    {
        $parameters = (object) [
            'filter' => [
                'updated'   => [[Operator::OPERATOR_ISNULL]]
            ]
        ];

        Filter::parseFromRequest(
            $parameters,
            $this->articleModel
        );
    }

    /**
     * @covers ::validate
     * @expectedException \Phramework\Exceptions\IncorrectParameterException
     */
    public function testParseFromRequestFailureAttributeToParse()
    {
        $parameters = (object) [
            'filter' => [
                'updated'   => 'qwerty'
            ]
        ];

        Filter::parseFromRequest(
            $parameters,
            $this->articleModel
        )->validate($this->articleModel);
    }

    /**
     * @covers ::validate
     * @expectedException \Phramework\Exceptions\RequestException
     */
    public function testParseFromRequestFailureAttributeNotAllowedOperator()
    {
        $parameters = (object) [
            'filter' => [
                'status'   => Operator::OPERATOR_GREATER_EQUAL . '1'
            ]
        ];

        Filter::parseFromRequest(
            $parameters,
            $this->articleModel
        )->validate($this->articleModel);
    }

    /**
     * @covers ::validate
     * @expectedException \Phramework\Exceptions\RequestException
     */
    public function testParseFromRequestFailureAttributeNotAcceptingJSONOperator()
    {
        $parameters = (object) [
            'filter' => [
                'status.ok'   => true
            ]
        ];

        Filter::parseFromRequest(
            $parameters,
            $this->articleModel
        )->validate($this->articleModel);
    }

    /**
     * @covers ::validate
     * @expectedException \Phramework\Exceptions\IncorrectParameterException
     */
    public function testParseFromRequestFailureAttributeUsingJSONPropertyValidator()
    {
        $parameters = (object) [
            'filter' => [
                'meta.timestamp'   => 'xsadas'
            ]
        ];

        $filter = Filter::parseFromRequest(
            $parameters,
            $this->articleModel
        );

        $filter->validate($this->articleModel);
    }

    /**
     * @covers ::parseFromRequest
     * @expectedException \Phramework\Exceptions\RequestException
     */
    public function testParseFromRequestFailureAttributeJSONSecondLevel()
    {
        $parameters = (object) [
            'filter' => [
                'meta.timestamp.time'   => 123456789
            ]
        ];

        $filter = Filter::parseFromRequest(
            $parameters,
            $this->articleModel
        );

        $filter->validate($this->articleModel);
    }

    /**
     * @covers ::validate
     */
    public function testValidate()
    {
        $filter = new Filter(
            [],
            (object) [
                'tag' => [1, 2, 3]
            ]
        );

        $model = $this->articleModel;

        $filter->validate($model);
    }

    /**
     * @covers ::validate
     * @expectedException \Phramework\Exceptions\RequestException
     */
    public function testValidateFailureRelationshipNotFound()
    {
        $filter = new Filter(
            [],
            (object) [
                'not-found-relationship' => [1, 2, 3]
            ]
        );

        $filter->validate($this->articleModel);
    }
}