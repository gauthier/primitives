<?php

    namespace Tests\ObjectivePHP\Primitives;

    use ObjectivePHP\PHPUnit\TestCase;
    use ObjectivePHP\Primitives\Collection\Collection;
    use ObjectivePHP\Primitives\Exception;
    use ObjectivePHP\Primitives\Merger\ValueMerger;
    use ObjectivePHP\Primitives\String\String;

    class CollectionTest extends TestCase
    {

        public function testRestrictTo()
        {
            $collection      = (new Collection)->restrictTo(Collection::class, false);
            $otherCollection = new Collection;
            $collection[]    = $otherCollection;

            $this->expectsException(function () use ($collection)
            {
                $collection[] = 'this is not a Collection object';
            }, Exception::class, null, Exception::COLLECTION_FORBIDDEN_VALUE);
        }

        public function testRestrictToRestrictionRemoval()
        {
            $collection      = (new Collection)->restrictTo(Collection::class);
            $otherCollection = new Collection;
            $collection->append($otherCollection);
            $collection->restrictTo('mixed');
            $collection[] = 'any value';
            $this->assertEquals('any value', $collection[1]);
        }

        /**
         * @dataProvider dataProviderForTestTypeValidity
         */
        public function testRestrictToWithVariousRestrictions($type, $valid)
        {
            $collection = new Collection();

            if (!is_null($valid))
            {
                $collection->restrictTo($type, false);
                $this->assertEquals($valid, $collection->getType());
            }
            else
            {
                $this->expectsException(function () use ($collection, $type)
                {
                    $collection->restrictTo($type);
                }, Exception::class, null, Exception::COLLECTION_INVALID_TYPE);
            }
        }

        public function dataProviderForTestTypeValidity()
        {
            return
                [
                    [\RecursiveDirectoryIterator::class, \RecursiveDirectoryIterator::class],
                    [\ArrayAccess::class, \ArrayAccess::class],
                    [false, false],
                    ['mixed', false],
                    [null, false],
                    ['UNKNOWN', null]
                ];
        }

        public function testStringNormalization()
        {
            $collection = new Collection();

            $collection->restrictTo(String::class, false)->addNormalizer(function (&$value)
            {
                // we add here a more restrictive normalizer than the default one
                if (is_string($value)) $value = new String($value);
            })
            ;
            $collection[] = 'scalar string';
            $collection[] = new String('another string');
            $this->assertInstanceOf(String::class, $collection[0]);
            $this->assertEquals('scalar string', (string) $collection[0]);
            $this->assertEquals('another string', (string) $collection[1]);

            $this->expectsException(function () use ($collection)
            {
                $collection[2] = 0x1;
            }, Exception::class, null, Exception::COLLECTION_FORBIDDEN_VALUE);
        }

        public function testRestrictToInterface()
        {
            $collection = (new Collection())->restrictTo(TestInterface::class);

            $this->assertEquals(TestInterface::class, $collection->getType());
        }

        public function testAllowedKeysCanBeDefinedAndFetched()
        {
            $collection = new Collection();

            $collection->setAllowedKeys('allowed_key');
            $this->assertEquals(['allowed_key'], $collection->getAllowedKeys()->toArray());
            $collection->setAllowedKeys(['a', 'b']);
            $this->assertEquals(['a', 'b'], $collection->getAllowedKeys()->toArray());

        }

        public function testOnlyAllowedKeysCanBeFilled()
        {
            $collection = new Collection();

            $collection->setAllowedKeys('allowed_key');
            $collection['allowed_key'] = 'string';
            $this->assertEquals('string', $collection['allowed_key']);
            $this->expectsException(function () use ($collection)
            {
                $collection['illegal_key'] = 'test';
            }, Exception::class, null, Exception::COLLECTION_FORBIDDEN_KEY);

        }

        public function testOnlyAllowedKeysCanBeRead()
        {
            $collection = new Collection();

            $collection->setAllowedKeys('allowed_key');

            $this->assertNull($collection['allowed_key']);


            $this->expectsException(function () use ($collection)
            {
                $collection['illegal_key'];
            }, Exception::class, null, Exception::COLLECTION_FORBIDDEN_KEY);
        }

        public function testEachLoopWithCallback()
        {
            $collection = new Collection([1, 2, 3]);

            $this->assertSame($collection, $collection->each(function ()
            {
            }));

            $this->assertEquals([2, 4, 6], $collection->each(function (&$value)
            {
                $value *= 2;
            })->getArrayCopy());

            $this->expectsException(function () use ($collection)
            {
                $collection->each('not callable');
            }, Exception::class, null, Exception::INVALID_CALLBACK);
        }

        public function testFilter()
        {
            $records    = [1, false, null, ''];
            $collection = new Collection($records);

            $this
                ->expectsException(function () use ($collection)
                {
                    $collection->filter('exception');
                }, Exception::class, null, Exception::INVALID_CALLBACK);

            // default behaviour: filter returns a new Collection
            $filtered = $collection->filter();
            $this->assertInstanceOf(Collection::class, $filtered);
            $this->assertSame($collection, $filtered);
            $this->assertEquals([1], $filtered->getArrayCopy());

            // alternative: it returns self
            $filtered = $collection->copy()->filter();
            $this->assertInstanceOf(Collection::class, $filtered);
            $this->assertNotSame($collection, $filtered);
            $this->assertEquals([1], $filtered->getArrayCopy());


            // other scenarii
            $records    = [1, 'test', 'test', ''];
            $collection = new Collection($records);
            $filtered   = $collection->filter(function ()
            {
                return false;
            });
            $this->assertInstanceOf(Collection::class, $filtered);
            $this->assertSame($collection, $filtered);
            $this->assertEquals([], $filtered->getArrayCopy());


            $filtered = $collection->filter(function ()
            {
                return false;
            }, true);
            $this->assertSame($collection, $filtered);
            $this->assertEquals([], $filtered->getArrayCopy());
        }

        public function testJoin()
        {
            $collection = (new Collection([new String('Objective'), new String('PHP')]))->restrictTo(String::class);

            $this->assertEquals('Objective PHP', $collection->join());
        }

        public function testFlip()
        {
            $data = ['a' => 'w', 'b' => 'x', 'y' => null, 'z' => ''];


            $collection = (new Collection($data))->flip();

            $this->assertEquals(['w' => 'a', 'x' => 'b', 0 => 'y', 1 => 'z'], $collection->toArray());
        }

        public function testAppend()
        {
            $collection = new Collection();

            $collection->append('value1');
            $this->assertEquals(['value1'], $collection->toArray());
            $collection->append('value2', 'value3');
            $this->assertEquals(['value1', 'value2', 'value3'], $collection->toArray());


            $collection = new Collection();
            $result     = $collection->append('test');
            $this->assertSame($collection, $result);
        }

        public function testNormalizer()
        {
            $collection = new Collection(['a', 'b', 'c']);
            $collection->addNormalizer(function (&$value)
            {
                $value = strtoupper($value);
            });

            $this->assertEquals('A', $collection[0]);

            $collection->append('d');
            $this->assertEquals('D', $collection[3]);
        }

        public function testNormalizerStack()
        {
            $collection = new Collection(['a', 'b', 'C']);
            $collection->addNormalizer(function (&$value)
            {
                $value = strtolower($value);
            });
            $collection->addNormalizer(function (&$value)
            {
                $value = '_' . strtolower($value) . '_';
            });

            $this->assertEquals('_a_', $collection[0]);
            $this->assertEquals('_b_', $collection[1]);
            $this->assertEquals('_c_', $collection[2]);

            $collection->append('D');
            $this->assertEquals('_d_', $collection[3]);
        }

        public function testKeyNormalization()
        {
            $collection = new Collection(['X' => 'a', 'y' => 'b', 'Z' => 'C']);
            $collection->addNormalizer(function (&$value, &$key)
            {
                $key   = strtoupper($key);
                $value = strtolower($value);
            });

            // @todo allow key normalization for previously stored entries too!

            $collection['d'] = 'TEST';
            $this->assertEquals(['X' => 'a', 'Y' => 'b', 'Z' => 'c', 'D' => 'test'], $collection->getInternalValue());
        }

        public function testValidator()
        {
            $collection = new Collection(['a', 'b', 'c']);
            $collection->addValidator($validator = function ($value)
            {
                return strlen($value) == 1;
            });

            $this->assertAttributeEquals(Collection::cast([$validator]), 'validators', $collection);

            $this->expectsException(function () use ($collection)
            {
                $collection[] = 'invalid string!';
            }, Exception::class);
        }

        public function testCastWithAnArray()
        {
            $value            = ['a', 'b', 'c'];
            $castedCollection = Collection::cast($value);

            $this->assertInstanceOf(Collection::class, $castedCollection);
            $this->assertEquals($value, $castedCollection->getInternalValue());
            $this->assertSame($castedCollection, Collection::cast($castedCollection));
        }

        public function testCastWithAnArrayObject()
        {
            $value            = new \ArrayObject(['a', 'b', 'c']);
            $castedCollection = Collection::cast($value);
            $this->assertInstanceOf(Collection::class, $castedCollection);
            $this->assertEquals($value->getArrayCopy(), $castedCollection->getInternalValue());
            $this->assertSame($castedCollection, Collection::cast($castedCollection));
        }

        public function testMerge()
        {
            $data       = ['b' => 'y'];
            $collection = new Collection(['a' => 'x']);

            $collection->merge($data);
            $this->assertEquals(new Collection(['a' => 'x', 'b' => 'y']), $collection);

            $collection->merge(['a' => 'z']);
            $this->assertEquals(new Collection(['a' => 'z', 'b' => 'y']), $collection);
        }

        public function testMergeWithCombiningValueMerger()
        {
            $collection       = new Collection(['a' => 'x']);
            $mergedCollection = new Collection(['a' => 'y']);

            $merger = $this->getMockBuilder(ValueMerger::class)->disableOriginalConstructor()->getMock();
            $merger->expects($this->once())->method('merge')->with('x', 'y')->willReturn('merged value');

            $collection->addMerger('a', $merger);

            $collection->merge($mergedCollection);

            $this->assertEquals(['a' => 'merged value'], $collection->toArray());
        }

        public function testAdd()
        {
            $collection = new Collection(['a' => 'x']);
            $collection->add(['a' => 'ignored', 'b' => 'y']);
            $this->assertEquals(new Collection(['a' => 'x', 'b' => 'y']), $collection);
        }

        public function testGetValues()
        {
            $collection = new Collection(['a' => 'x']);

            $values = $collection->getValues();

            $this->assertEquals(new Collection([0 => 'x']), $values);
        }

        public function testKeysExport()
        {
            $collection = new Collection(['a' => 'x']);

            $values = $collection->getKeys();

            $this->assertEquals(new Collection([0 => 'a']), $values);
        }

        public function testIsEmpty()
        {
            $collection = new Collection();

            $this->assertEquals(0, count($collection));
            $this->assertTrue($collection->isEmpty());

            $collection->append('some value');

            $this->assertEquals(1, count($collection));
            $this->assertFalse($collection->isEmpty());
        }

        public function testHas()
        {
            $collection = new Collection(['a' => 'x']);
            $this->assertTrue($collection->has('a'));
            $this->assertFalse($collection->has('b'));
        }

        public function testLacks()
        {
            $collection = new Collection(['a' => 'x']);
            $this->assertFalse($collection->lacks('a'));
            $this->assertTrue($collection->lacks('b'));
        }

        public function testSearch()
        {
            $collection = new Collection(['a' => 'x', 'b' => 'Y']);
            $this->assertEquals('a', $collection->search('x'));
            $this->assertEquals('a', $collection->search('X'));
            $this->assertEquals(null, $collection->search('X', true));
            $this->assertEquals('b', $collection->search('y'));
            $this->assertEquals(null, $collection->search('y', true));
        }

        public function testContains()
        {
            $collection = new Collection(['a' => 'x', 'b' => 'Y']);
            $this->assertTrue($collection->contains('x'));
            $this->assertTrue($collection->contains('X'));
            $this->assertFalse($collection->contains('X', true));
            $this->assertTrue($collection->contains('y'));
            $this->assertFalse($collection->contains('y', true));
        }

    }


    /*********************
     * HELPERS
     ********************/
    interface TestInterface
    {

    }