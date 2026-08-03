<?php 
namespace SchoolPalm\ModuleBridge\Query;

interface QueryEngineHooks
{
    public function beforeInsert(array $data): array;

    public function beforeUpdate(array $data, array $criteria): array;

    public function afterInsert(mixed $result, array $data): mixed;

    public function afterUpdate(mixed $result, array $data, array $criteria): mixed;
}