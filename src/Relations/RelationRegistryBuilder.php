<?php
namespace SchoolPalm\ModuleBridge\Relations;

/**
 * Builds a merged relation registry from installed module relation providers.
 */

class RelationRegistryBuilder
{
    public function build(array $modules): array
    {
        $registry = [];

        foreach ($modules as $module) {

            $providerClass = $module['relations'] ?? null;

            if (!$providerClass || !class_exists($providerClass)) {
                continue;
            }

            /** @var RelationProvider $provider */
            $provider = app($providerClass);

            foreach ($provider->relations() as $name => $relation) {

                /**
                 * IMPORTANT:
                 * $name is already fully qualified:
                 * e.g. unnovatebrains.common.student.Student
                 */
                $registry[$name] = $relation;
            }
        }

        return $registry;
    }
}

