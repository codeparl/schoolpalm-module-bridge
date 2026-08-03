<?php
namespace SchoolPalm\ModuleBridge\Relations;

/**
 * Contract implemented by modules that expose relation definitions.
 */
interface RelationProvider
{
    /**
     * Return relation definitions
     */
    public function relations(): array;
      public static function getDefinitions(): array;
}
