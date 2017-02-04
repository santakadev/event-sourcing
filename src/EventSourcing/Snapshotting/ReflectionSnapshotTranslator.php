<?php

namespace DDDominio\EventSourcing\Snapshotting;

use DDDominio\EventSourcing\Common\EventSourcedAggregateRoot;

abstract class ReflectionSnapshotTranslator implements SnapshotTranslatorInterface
{
    /**
     * @return string
     */
    protected abstract function aggregateClass();

    /**
     * @return string
     */
    protected abstract function snapshotClass();

    /**
     * @return array
     */
    protected abstract function aggregateToSnapshotPropertyDictionary();

    /**
     * @param EventSourcedAggregateRoot $aggregate
     * @return object
     */
    public function buildSnapshotFromAggregate($aggregate)
    {
        $snapshotClass = $this->snapshotClass();
        $snapshot = (new \ReflectionClass($snapshotClass))->newInstanceWithoutConstructor();
        $reflectedClass = new \ReflectionClass($snapshotClass);

        $dictionary = $this->aggregateToSnapshotPropertyDictionary();

        foreach ($dictionary as $aggregateProperty => $snapshotProperty) {
            $nameProperty = $reflectedClass->getProperty($snapshotProperty);
            $nameProperty->setAccessible(true);
            $nameProperty->setValue($snapshot, $aggregate->$aggregateProperty());
        }

        return $snapshot;
    }

    /**
     * @param SnapshotInterface $snapshot
     * @return object
     */
    public function buildAggregateFromSnapshot($snapshot)
    {
        $aggregateClass = $this->aggregateClass();
        $aggregate = (new \ReflectionClass($aggregateClass))->newInstanceWithoutConstructor();
        $reflectedClass = new \ReflectionClass($aggregateClass);

        $dictionary = $this->aggregateToSnapshotPropertyDictionary();

        foreach ($dictionary as $aggregateProperty => $snapshotProperty) {
            $nameProperty = $reflectedClass->getProperty($aggregateProperty);
            $nameProperty->setAccessible(true);
            $nameProperty->setValue($aggregate, $snapshot->$snapshotProperty());
        }

        return $aggregate;
    }
}
