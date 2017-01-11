<?php

namespace Tests\EventSourcing\Snapshotting;

use Tests\EventSourcing\Common\TestData\DummyEventSourcedAggregate;
use Tests\EventSourcing\Common\TestData\DummyReflectionSnapshotTranslator;
use Tests\EventSourcing\Common\TestData\DummySnapshot;

class ReflectionSnapshotTranslatorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function buildAnAggregateFromSnapshot()
    {
        $snapshot = new DummySnapshot(
            'id',
            'name',
            'description',
            10
        );
        $snapshotTranslator = new DummyReflectionSnapshotTranslator();

        $aggregate = $snapshotTranslator->buildAggregateFromSnapshot($snapshot);

        $this->assertEquals('id', $aggregate->id());
        $this->assertEquals('name', $aggregate->name());
        $this->assertEquals('description', $aggregate->description());
        $this->assertEquals(10, $aggregate->version());
    }

    /**
     * @test
     */
    public function buildAnSnapshotFromAggregate()
    {
        $aggregate = new DummyEventSourcedAggregate(
            'id',
            'name',
            'description'
        );
        $snapshotTranslator = new DummyReflectionSnapshotTranslator();

        $snapshot = $snapshotTranslator->buildSnapshotFromAggregate($aggregate);

        $this->assertEquals('id', $snapshot->id());
        $this->assertEquals('name', $snapshot->name());
        $this->assertEquals('description', $snapshot->description());
        $this->assertEquals(1, $snapshot->version());
    }
}
