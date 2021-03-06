<?php

namespace DDDominio\EventSourcing\EventStore;

use DDDominio\EventSourcing\Common\DomainEvent;
use DDDominio\EventSourcing\Common\EventStreamInterface;
use DDDominio\EventSourcing\Serialization\SerializerInterface;
use DDDominio\EventSourcing\Versioning\EventUpgraderInterface;
use DDDominio\EventSourcing\Versioning\UpgradableEventStoreInterface;
use DDDominio\EventSourcing\Versioning\Version;
use Ramsey\Uuid\Uuid;

abstract class AbstractEventStore implements EventStoreInterface, UpgradableEventStoreInterface
{
    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var EventUpgraderInterface
     */
    private $eventUpgrader;

    /**
     * @var EventStoreListenerInterface[]|callable[]
     */
    private $eventListeners;

    /**
     * @param SerializerInterface $serializer
     * @param EventUpgraderInterface $eventUpgrader
     */
    public function __construct(
        SerializerInterface $serializer,
        EventUpgraderInterface $eventUpgrader
    ) {
        $this->serializer = $serializer;
        $this->eventUpgrader = $eventUpgrader;
    }

    /**
     * @param string $streamId
     * @param DomainEvent[] $events
     * @param int $expectedVersion
     * @throws ConcurrencyException
     * @throws EventStreamDoesNotExistException
     */
    public function appendToStream($streamId, $events, $expectedVersion = self::EXPECTED_VERSION_EMPTY_STREAM)
    {
        if ($this->streamExists($streamId)) {
            $this->assertOptimisticConcurrency($streamId, $expectedVersion);
        } else {
            $this->assertEventStreamExistence($streamId, $expectedVersion);
        }
        $this->executeEventListeners($events, EventStoreEvents::PRE_APPEND);
        $this->appendStoredEvents(
            $streamId,
            $this->storedEventsFromEvents($streamId, $events),
            $expectedVersion
        );
        $this->executeEventListeners($events, EventStoreEvents::POST_APPEND);
    }

    /**
     * @param EventStreamInterface $eventStream
     * @return EventStreamInterface
     */
    protected function domainEventStreamFromStoredEvents($eventStream)
    {
        return $eventStream->map(function (StoredEvent $storedEvent) {
            $this->eventUpgrader->migrate($storedEvent);
            return new DomainEvent(
                $this->serializer->deserialize($storedEvent->data(), $storedEvent->type()),
                json_decode($storedEvent->metadata(), true),
                $storedEvent->occurredOn(),
                $storedEvent->version()
            );
        });
    }

    /**
     * @param string $streamId
     * @param int $expectedVersion
     * @throws ConcurrencyException
     */
    private function assertOptimisticConcurrency($streamId, $expectedVersion)
    {
        if ($expectedVersion !== self::EXPECTED_VERSION_ANY
            && $expectedVersion !== $this->streamVersion($streamId)) {
            throw ConcurrencyException::fromVersions(
                $this->streamVersion($streamId),
                $expectedVersion
            );
        }
    }

    /**
     * @param string $streamId
     * @param int $expectedVersion
     * @throws EventStreamDoesNotExistException
     */
    private function assertEventStreamExistence($streamId, $expectedVersion)
    {
        if ($expectedVersion > 0) {
            throw EventStreamDoesNotExistException::fromStreamId($streamId);
        }
    }

    /**
     * @param string $streamId
     * @param DomainEvent[] $events
     * @return array
     */
    private function storedEventsFromEvents($streamId, $events)
    {
        return array_map(function (DomainEvent $event) use ($streamId) {
            return new StoredEvent(
                $this->nextStoredEventId(),
                $streamId,
                get_class($event->data()),
                $this->serializer->serialize($event->data()),
                json_encode($event->metadata()->all(), JSON_FORCE_OBJECT),
                $event->occurredOn(),
                is_null($event->version()) ? Version::fromString('1.0') : $event->version()
            );
        }, $events);
    }

    /**
     * @return string
     */
    protected function nextStoredEventId()
    {
        return Uuid::uuid4()->toString();
    }

    /**
     * @param string $type
     * @param Version $from
     * @param Version $to
     */
    public function migrate($type, $from, $to)
    {
        foreach ($this->readStoredEventsOfTypeAndVersion($type, $from) as $event) {
            $this->eventUpgrader->migrate($event, $to);
        }
    }

    /**
     * @param $eventStoreEvent
     * @param EventStoreListenerInterface|callable $eventStoreListener
     */
    public function addEventListener($eventStoreEvent, $eventStoreListener)
    {
        $this->eventListeners[$eventStoreEvent][] = $eventStoreListener;
    }

    /**
     * @param string $streamId
     * @param StoredEvent[] $storedEvents
     * @param int $expectedVersion
     */
    abstract protected function appendStoredEvents($streamId, $storedEvents, $expectedVersion);

    /**
     * @param string $streamId
     * @return bool
     */
    abstract protected function streamExists($streamId);

    /**
     * @param string $streamId
     * @return int
     */
    abstract protected function streamVersion($streamId);

    /**
     * @param string $type
     * @param Version $version
     * @return EventStreamInterface
     */
    abstract protected function readStoredEventsOfTypeAndVersion($type, $version);

    /**
     * @param DomainEvent[] $events
     * @param string $eventStoreEvent
     */
    protected function executeEventListeners($events, $eventStoreEvent)
    {
        if (isset($this->eventListeners[$eventStoreEvent])) {
            foreach ($this->eventListeners[$eventStoreEvent] as $eventListener) {
                if ($eventListener instanceof EventStoreListenerInterface) {
                    $eventListener->handle($events);
                } else {
                    $eventListener($events);
                }
            }
        }
    }
}
