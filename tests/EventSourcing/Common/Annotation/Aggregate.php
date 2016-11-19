<?php

namespace Tests\EventSourcing\Common\Annotation;

use EventSourcing\Common\Annotation\EventSourcedAggregate;
use EventSourcing\Common\Annotation\PublishDomainEvent;

/**
 * @EventSourcedAggregate()
 */
class Aggregate
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $name;

    /**
     * @param string $id
     * @param string $name
     *
     * @PublishDomainEvent(event="AggregateAdded")
     */
    public function __construct($id, $name)
    {
        $this->id = $id;
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function id()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function name()
    {
        return $this->name;
    }

    /**
     * @param string $name
     *
     * @PublishDomainEvent(event="AggregateNameChanged")
     */
    public function changeName($name)
    {
        $this->name = $name;
    }
}