<?php

namespace DDDominio\EventSourcing\Serialization;

interface Serializer
{
    /**
     * @param object|array $object
     * @return string
     */
    public function serialize($object);

    /**
     * @param string $serializedObject
     * @param string $class
     * @return object|array
     */
    public function deserialize($serializedObject, $class);
}