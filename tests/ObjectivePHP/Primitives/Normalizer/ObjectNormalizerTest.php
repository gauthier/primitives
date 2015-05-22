<?php

    namespace Tests\ObjectivePHP\Primitives\Normalizer;

    use helpers\SomeObject;
    use ObjectivePHP\PHPUnit\TestCase;
    use ObjectivePHP\Primitives\Normalizer\ObjectNormalizer;

    class ObjectNormalizerTest extends TestCase
    {

        public function testNormalizerLeavesObjectsUntouched()
        {
            $normalizer = new ObjectNormalizer(SomeObject::class);

            $value = new SomeObject();

            $normalizer($value);

            $this->assertInstanceOf(SomeObject::class, $value);

        }

        public function testNormalizerInstantiateAnObjectIfValueIfNeeded()
        {
            $normalizer = new ObjectNormalizer(SomeObject::class);

            $value = ['arg1', 'arg2'];

            $normalizer($value);

            $this->assertInstanceOf(SomeObject::class, $value);
            $this->assertAttributeEquals('arg1', 'property', $value);
            $this->assertAttributeEquals('arg2', 'otherProperty', $value);

            $value = 'single argument';

            $normalizer($value);

            $this->assertInstanceOf(SomeObject::class, $value);
            $this->assertAttributeEquals('single argument', 'property', $value);
            $this->assertAttributeEquals(null, 'otherProperty', $value);

        }

    }

    namespace helpers;

    class SomeObject
    {

        protected $property;

        protected $otherProperty;

        public function __construct($arg = null, $otherArg = null)
        {
            $this->property      = $arg;
            $this->otherProperty = $otherArg;
        }
    }