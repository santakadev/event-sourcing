<?php

namespace DDDominio\Tests\EventSourcing\EventStore;

use DDDominio\EventSourcing\EventStore\EventStore;
use DDDominio\EventSourcing\EventStore\InMemoryEventStore;
use DDDominio\EventSourcing\EventStore\StoredEvent;
use DDDominio\EventSourcing\EventStore\StoredEventStream;
use Doctrine\Common\Annotations\AnnotationRegistry;
use DDDominio\EventSourcing\Common\DomainEvent;
use DDDominio\EventSourcing\Common\EventStream;
use DDDominio\EventSourcing\Serialization\JsonSerializer;
use DDDominio\EventSourcing\Serialization\Serializer;
use DDDominio\EventSourcing\Versioning\EventAdapter;
use DDDominio\EventSourcing\Versioning\EventUpgrader;
use DDDominio\EventSourcing\Versioning\JsonTransformer\JsonTransformer;
use DDDominio\EventSourcing\Versioning\JsonTransformer\TokenExtractor;
use DDDominio\EventSourcing\Versioning\Version;
use JMS\Serializer\SerializerBuilder;
use DDDominio\Tests\EventSourcing\TestData\DescriptionChanged;
use DDDominio\Tests\EventSourcing\TestData\NameChanged;
use DDDominio\Tests\EventSourcing\TestData\VersionedEvent;
use DDDominio\Tests\EventSourcing\TestData\VersionedEventUpgrade10_20;

class InMemoryEventStoreTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @var EventUpgrader
     */
    private $eventUpgrader;

    protected function setUp()
    {
        AnnotationRegistry::registerAutoloadNamespace(
            'JMS\Serializer\Annotation', __DIR__ . '/../../../vendor/jms/serializer/src'
        );
        $this->serializer = new JsonSerializer(
            SerializerBuilder::create()->build()
        );
        $tokenExtractor = new TokenExtractor();
        $jsonTransformer = new JsonTransformer($tokenExtractor);
        $eventAdapter = new EventAdapter($jsonTransformer);
        $this->eventUpgrader = new EventUpgrader($eventAdapter);
        $this->eventUpgrader->registerUpgrade(
            new VersionedEventUpgrade10_20($eventAdapter)
        );
    }

    /**
     * @test
     */
    public function appendAnEventToANewStreamShouldCreateAStreamContainingTheEvent()
    {
        $eventStore = new InMemoryEventStore($this->serializer, $this->eventUpgrader);
        $domainEvent = new NameChanged('name', new \DateTimeImmutable());

        $eventStore->appendToStream('streamId', [$domainEvent]);
        $stream = $eventStore->readFullStream('streamId');

        $this->assertInstanceOf(EventStream::class, $stream);
        $this->assertCount(1, $stream);
    }

    /**
     * @test
     */
    public function appendAnEventToAnExistentStream()
    {
        $eventStore = new InMemoryEventStore($this->serializer, $this->eventUpgrader);
        $domainEvent = new NameChanged('name', new \DateTimeImmutable());

        $eventStore->appendToStream('streamId', [$domainEvent]);
        $eventStore->appendToStream('streamId', [$domainEvent], 1);
        $stream = $eventStore->readFullStream('streamId');

        $this->assertCount(2, $stream);
    }

    /**
     * @test
     * @expectedException \DDDominio\EventSourcing\EventStore\ConcurrencyException
     */
    public function ifTheExpectedVersionOfTheStreamDoesNotMatchWithRealVersionAConcurrencyExceptionShouldBeThrown()
    {
        $storedEvent = $this->createMock(StoredEvent::class);
        $streamId = 'streamId';
        $storedEventStream = new StoredEventStream($streamId, [$storedEvent]);
        // expected version form streamId: 1
        $eventStore = new InMemoryEventStore(
            $this->serializer,
            $this->eventUpgrader,
            [$streamId => $storedEventStream]
        );
        $domainEvent = $this->createMock(DomainEvent::class);

        $eventStore->appendToStream('streamId', [$domainEvent]);
    }

    /**
     * @test
     * @expectedException \DDDominio\EventSourcing\EventStore\EventStreamDoesNotExistException
     */
    public function whenAppendingToANewStreamIfAVersionIsSpecifiedAnExceptionShouldBeThrown()
    {
        $eventStore = new InMemoryEventStore($this->serializer, $this->eventUpgrader);
        $domainEvent = $this->createMock(DomainEvent::class);

        $eventStore->appendToStream('newStreamId', [$domainEvent], 10);
    }

    /**
     * @test
     */
    public function readAnEventStream()
    {
        $domainEvent = new NameChanged('name', new \DateTimeImmutable());
        $storedEvent = new StoredEvent(
            'id',
            'streamId',
            get_class($domainEvent),
            $this->serializer->serialize($domainEvent),
            $domainEvent->occurredOn(),
            Version::fromString('1.0')
        );
        $storedEventStream = new StoredEventStream('streamId', [$storedEvent]);
        $eventStore = new InMemoryEventStore(
            $this->serializer,
            $this->eventUpgrader,
            ['streamId' => $storedEventStream]
        );

        $stream = $eventStore->readFullStream('streamId');

        $this->assertCount(1, $stream);
        $this->assertInstanceOf(DomainEvent::class, $stream->events()[0]);
    }

    /**
     * @test
     */
    public function readAnEmptyStream()
    {
        $eventStore = new InMemoryEventStore($this->serializer, $this->eventUpgrader);

        $stream = $eventStore->readFullStream('NonExistentStreamId');

        $this->assertTrue($stream->isEmpty());
        $this->assertCount(0, $stream);
    }

    /**
     * @test
     */
    public function findStreamEventsForward()
    {
        $domainEvents = [
            new NameChanged('new name', new \DateTimeImmutable()),
            new DescriptionChanged('new description', new \DateTimeImmutable()),
            new NameChanged('another name', new \DateTimeImmutable()),
            new NameChanged('my name', new \DateTimeImmutable()),
        ];
        $storedEvents = $this->storedEventsFromDomainEvents($domainEvents);
        $storedEventStream = new StoredEventStream('streamId', $storedEvents);
        $eventStore = new InMemoryEventStore(
            $this->serializer,
            $this->eventUpgrader,
            ['streamId' => $storedEventStream]
        );

        $stream = $eventStore->readStreamEventsForward('streamId', 2);

        $this->assertCount(3, $stream);
        $events = $stream->events();
        $this->assertEquals('new description', $events[0]->description());
        $this->assertEquals('another name', $events[1]->name());
        $this->assertEquals('my name', $events[2]->name());
    }

    /**
     * @test
     */
    public function findStreamEventsForwardWithEventCount()
    {
        $domainEvents = [
            new NameChanged('new name', new \DateTimeImmutable()),
            new DescriptionChanged('new description', new \DateTimeImmutable()),
            new NameChanged('another name', new \DateTimeImmutable()),
            new NameChanged('my name', new \DateTimeImmutable()),
        ];
        $storedEvents = $this->storedEventsFromDomainEvents($domainEvents);
        $storedEventStream = new StoredEventStream('streamId', $storedEvents);

        $eventStore = new InMemoryEventStore(
            $this->serializer,
            $this->eventUpgrader,
            ['streamId' => $storedEventStream]
        );

        $stream = $eventStore->readStreamEventsForward('streamId', 2, 2);

        $this->assertCount(2, $stream);
        $events = $stream->events();
        $this->assertEquals('new description', $events[0]->description());
        $this->assertEquals('another name', $events[1]->name());
    }

    /**
     * @test
     */
    public function findStreamEventsForwardShouldReturnEmptyStreamIfStartVersionIsGreaterThanStreamVersion()
    {
        $domainEvents = [
            new NameChanged('new name', new \DateTimeImmutable()),
            new DescriptionChanged('new description', new \DateTimeImmutable()),
            new NameChanged('another name', new \DateTimeImmutable()),
            new NameChanged('my name', new \DateTimeImmutable()),
        ];
        $storedEvents = $this->storedEventsFromDomainEvents($domainEvents);
        $storedEventStream = new StoredEventStream('streamId', $storedEvents);

        $eventStore = new InMemoryEventStore(
            $this->serializer,
            $this->eventUpgrader,
            ['streamId' => $storedEventStream]
        );

        $stream = $eventStore->readStreamEventsForward('streamId', 5);

        $this->assertTrue($stream->isEmpty());
    }

    /**
     * @test
     */
    public function whenReadingFullStreamItShouldUpgradeOldStoredEvents()
    {
        $oldStoredEvent = new StoredEvent(
            'id',
            'streamId',
            VersionedEvent::class,
            '{"name":"Name","occurredOn":"2016-12-04 17:35:35"}',
            new \DateTimeImmutable('2016-12-04 17:35:35'),
            Version::fromString('1.0')
        );
        $storedEventStream = new StoredEventStream('streamId', [$oldStoredEvent]);
        $streams = [$storedEventStream->id() => $storedEventStream];
        $eventStore = new InMemoryEventStore(
            $this->serializer,
            $this->eventUpgrader,
            $streams
        );

        $stream = $eventStore->readFullStream('streamId');

        $domainEvent = $stream->events()[0];
        $this->assertEquals('Name', $domainEvent->username());
    }

    /**
     * @test
     */
    public function whenReadingStreamEventsForwardItShouldUpgradeOldStoredEvents()
    {
        $oldStoredEvent = new StoredEvent(
            'id',
            'streamId',
            VersionedEvent::class,
            '{"name":"Name","occurredOn":"2016-12-04 17:35:35"}',
            new \DateTimeImmutable('2016-12-04 17:35:35'),
            Version::fromString('1.0')
        );
        $storedEventStream = new StoredEventStream('streamId', [$oldStoredEvent]);
        $streams = [$storedEventStream->id() => $storedEventStream];
        $eventStore = new InMemoryEventStore(
            $this->serializer,
            $this->eventUpgrader,
            $streams
        );

        $stream = $eventStore->readStreamEventsForward('streamId');

        $domainEvent = $stream->events()[0];
        $this->assertEquals('Name', $domainEvent->username());
    }

    /**
     * @test
     */
    public function itShouldUpgradeEventsInEventStore()
    {
        $oldStoredEvent = new StoredEvent(
            'id',
            'streamId',
            VersionedEvent::class,
            '{"name":"Name","occurred_on":"2016-12-04 17:35:35"}',
            new \DateTimeImmutable('2016-12-04 17:35:35'),
            Version::fromString('1.0')
        );
        $storedEventStream = new StoredEventStream('streamId', [$oldStoredEvent]);
        $streams = [$storedEventStream->id() => $storedEventStream];
        $eventStore = new InMemoryEventStore(
            $this->serializer,
            $this->eventUpgrader,
            $streams
        );

        $eventStore->migrate(
            VersionedEvent::class,
            Version::fromString('1.0'),
            Version::fromString('2.0')
        );

        $stream = $eventStore->readFullStream('streamId');
        $this->assertCount(1, $stream);
        $event = $stream->events()[0];
        $this->assertTrue(Version::fromString('2.0')->equalTo($event->version()));
        $this->assertEquals('Name', $event->username());
        $this->assertEquals('2016-12-04 17:35:35', $event->occurredOn()->format('Y-m-d H:i:s'));
    }

    /**
     * @test
     */
    public function addAfterEventsAppendedEventListener()
    {
        $appendedEvents = [];
        $eventListener = function($events) use (&$appendedEvents) {
            $appendedEvents = $events;
        };
        $eventStore = new InMemoryEventStore(
            $this->serializer,
            $this->eventUpgrader
        );
        $eventStore->addEventListener(EventStore::AFTER_EVENTS_APPENDED, $eventListener);
        $events = [new NameChanged('name', new \DateTimeImmutable())];

        $eventStore->appendToStream('streamId', $events);

        $this->assertCount(1, $appendedEvents);
        $this->assertInstanceOf(NameChanged::class, $appendedEvents[0]);
        $this->assertEquals('name', $appendedEvents[0]->name());
    }

    /**
     * @test
     */
    public function addMultipleAfterEventsAppendedEventListener()
    {
        $anEventListenerCalled = false;
        $anEventListener = function() use (&$anEventListenerCalled) {
            $anEventListenerCalled = true;
        };
        $anotherEventListenerCalled = false;
        $anotherEventListener = function() use (&$anotherEventListenerCalled ) {
            $anotherEventListenerCalled  = true;
        };
        $eventStore = new InMemoryEventStore(
            $this->serializer,
            $this->eventUpgrader
        );
        $eventStore->addEventListener(EventStore::AFTER_EVENTS_APPENDED, $anEventListener);
        $eventStore->addEventListener(EventStore::AFTER_EVENTS_APPENDED, $anotherEventListener);
        $events = [new NameChanged('name', new \DateTimeImmutable())];

        $eventStore->appendToStream('streamId', $events);

        $this->assertTrue($anEventListenerCalled);
        $this->assertTrue($anotherEventListenerCalled);
    }

    /**
     * @param DomainEvent[] $domainEvents
     * @return StoredEvent[]
     */
    private function storedEventsFromDomainEvents($domainEvents)
    {
        return array_map(function(DomainEvent $domainEvent) {
            return new StoredEvent(
                'id',
                'streamId',
                get_class($domainEvent),
                $this->serializer->serialize($domainEvent),
                $domainEvent->occurredOn(),
                Version::fromString('1.0')
            );
        }, $domainEvents);
    }
}