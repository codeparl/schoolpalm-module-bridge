<?php

namespace SchoolPalm\ModuleBridge\Traits;

trait HasQueryEngineHooks
{
    // --- Single Insert ---
    public function runBeforeInsert(array $data): array
    {
        return method_exists($this, 'beforeInsert')
            ? $this->beforeInsert($data)
            : $data;
    }

    public function runAfterInsert($result, array $data)
    {
        if (method_exists($this, 'afterInsert')) {
            $this->afterInsert($result, $data);
        }
        return $result;
    }

    // --- Bulk Insert ---
    public function runBeforeInsertMany(array $dataSet): array
    {
        return method_exists($this, 'beforeInsertMany')
            ? $this->beforeInsertMany($dataSet)
            : $dataSet;
    }

    public function runAfterInsertMany($results, array $dataSet)
    {
        if (method_exists($this, 'afterInsertMany')) {
            $this->afterInsertMany($results, $dataSet);
        }
        return $results;
    }

    // --- Update (single or bulk) ---
    public function runBeforeUpdate(array $data, array $criteria): array
    {
        return method_exists($this, 'beforeUpdate')
            ? $this->beforeUpdate($data, $criteria)
            : $data;
    }

    public function runAfterUpdate($result, array $data, array $criteria)
    {
        if (method_exists($this, 'afterUpdate')) {
            $this->afterUpdate($result, $data, $criteria);
        }
        return $result;
    }

    // --- Delete ---
    public function runBeforeDelete($identifier): void
    {
        if (method_exists($this, 'beforeDelete')) {
            $this->beforeDelete($identifier);
        }
    }

    public function runAfterDelete($result, $identifier): void
    {
        if (method_exists($this, 'afterDelete')) {
            $this->afterDelete($result, $identifier);
        }
    }
}