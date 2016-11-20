<?php

namespace EventSourcing\Common\Model;

class Snapshotter
{
    private $snapshotStrategies = [];

    /**
     * @param string $aggregateClass
     * @param ReflectionSnapshotTranslator $snapshotStrategy
     */
    public function addSnapshotTranslator($aggregateClass, $snapshotStrategy)
    {
        $this->snapshotStrategies[$aggregateClass] = $snapshotStrategy;
    }

    /**
     * @param EventSourcedAggregate $aggregate
     * @return Snapshot
     */
    public function takeSnapshot($aggregate)
    {
        return $this->snapshotStrategies[get_class($aggregate)]->buildSnapshotFromAggregate($aggregate);
    }

    /**
     * @param Snapshot $snapshot
     * @return EventSourcedAggregate
     */
    public function translateSnapshot($snapshot)
    {
        return $this->snapshotStrategies[$snapshot->aggregateClass()]->buildAggregateFromSnapshot($snapshot);
    }
}
