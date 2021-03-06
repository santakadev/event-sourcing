<?php

namespace DDDominio\Tests\EventSourcing\Common;

use DDDominio\EventSourcing\Common\AggregateReconstructor;
use DDDominio\EventSourcing\Common\EventStream;
use DDDominio\EventSourcing\Snapshotting\SnapshotInterface;
use DDDominio\EventSourcing\Snapshotting\Snapshotter;
use DDDominio\Tests\EventSourcing\TestData\DummyCreated;
use DDDominio\Tests\EventSourcing\TestData\DummyDeleted;
use DDDominio\Tests\EventSourcing\TestData\DummyEventSourcedAggregate;
use DDDominio\Tests\EventSourcing\TestData\DummyReflectionSnapshotTranslator;
use DDDominio\Tests\EventSourcing\TestData\DummySnapshot;
use DDDominio\Tests\EventSourcing\TestData\NameChanged;
use Doctrine\Common\Annotations\AnnotationRegistry;

class AggregateReconstructorTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        AnnotationRegistry::registerLoader('class_exists');
    }

    /**
     * @test
     */
    public function reconstructAnAggregateFromOneEvent()
    {
        $snapshotter = $this->createMock(Snapshotter::class);
        $reconstructor = new AggregateReconstructor($snapshotter);
        $dummyCreatedEvent = new DummyCreated('id', 'name', 'description', new \DateTimeImmutable());
        $eventStream = new EventStream([$dummyCreatedEvent]);

        $aggregate = $reconstructor->reconstitute(DummyEventSourcedAggregate::class, $eventStream);

        $this->assertEquals(1, $aggregate->version());
        $this->assertEquals('name', $aggregate->name());
        $this->assertEquals('description', $aggregate->description());
    }

    /**
     * @test
     */
    public function reconstructAnAggregateFromMultipleEvents()
    {
        $snapshotter = $this->createMock(Snapshotter::class);
        $reconstructor = new AggregateReconstructor($snapshotter);
        $dummyCreatedEvent = new DummyCreated('id', 'name', 'description', new \DateTimeImmutable());
        $dummyNameChanged = new NameChanged('new name', new \DateTimeImmutable());
        $eventStream = new EventStream([$dummyCreatedEvent, $dummyNameChanged]);

        $aggregate = $reconstructor->reconstitute(DummyEventSourcedAggregate::class, $eventStream);

        $this->assertEquals(2, $aggregate->version());
        $this->assertEquals('new name', $aggregate->name());
        $this->assertEquals('description', $aggregate->description());
    }

    /**
     * @test
     */
    public function reconstructAnAggregateUsingAnSnapshot()
    {
        $snapshotTranslator = new DummyReflectionSnapshotTranslator();
        $snapshotter = new Snapshotter();
        $snapshotter->addSnapshotTranslator(
            DummyEventSourcedAggregate::class,
            $snapshotTranslator
        );
        $reconstructor = new AggregateReconstructor($snapshotter);
        $snapshot = new DummySnapshot('id', 'name', 'description', 2);
        $eventStream = new EventStream([new NameChanged('new name', new \DateTimeImmutable())]);

        $aggregate = $reconstructor->reconstitute(
            DummyEventSourcedAggregate::class,
            $eventStream,
            $snapshot
        );

        $this->assertEquals(3, $aggregate->version());
        $this->assertEquals('id', $aggregate->id());
        $this->assertEquals('new name', $aggregate->name());
        $this->assertEquals('description', $aggregate->description());
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     */
    public function notEventSourcedAggregateCanNotBeReconstructed()
    {
        $snapshotter = $this->createMock(Snapshotter::class);
        $reconstructor = new AggregateReconstructor($snapshotter);

        $reconstructor->reconstitute(__CLASS__, new EventStream([]));
    }

    /**
     * @test
     */
    public function reconstructUsingSnapshot()
    {
        $snapshot = $this->createMock(SnapshotInterface::class);
        $aggregateMock = $this->createMock(DummyEventSourcedAggregate::class);
        $aggregateMock
            ->method('version')
            ->willReturn(10);
        $snapshooter = $this->createMock(Snapshotter::class);
        $snapshooter
            ->method('translateSnapshot')
            ->willReturn($aggregateMock);
        $reconstructor = new AggregateReconstructor($snapshooter);

        $reconstructedAggregate = $reconstructor->reconstitute(
            DummyEventSourcedAggregate::class,
            new EventStream([]),
            $snapshot
        );

        $this->assertEquals(10, $reconstructedAggregate->version());
    }

    /**
     * @test
     */
    public function whenLastEventIsAnAggregateDeleterItShouldReturnNull()
    {
        $snapshotter = $this->createMock(Snapshotter::class);
        $reconstructor = new AggregateReconstructor($snapshotter);
        $eventStream = new EventStream([
            new DummyCreated('id', 'name', 'description', new \DateTimeImmutable()),
            new DummyDeleted('id', new \DateTimeImmutable())
        ]);

        $aggregate = $reconstructor->reconstitute(DummyEventSourcedAggregate::class, $eventStream);

        $this->assertNull($aggregate);
    }

    /**
     * @test
     */
    public function whenEvenStreamIsEmptyItShouldReturnNull()
    {
        $snapshotter = $this->createMock(Snapshotter::class);
        $reconstructor = new AggregateReconstructor($snapshotter);
        $eventStream = new EventStream([]);

        $aggregate = $reconstructor->reconstitute(DummyEventSourcedAggregate::class, $eventStream);

        $this->assertNull($aggregate);
    }
}
