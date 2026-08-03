<?php

namespace SchoolPalm\ModuleBridge\Generators;

class ReflectionScanner
{
    public function scanInterfaceMethods(string $contractClass): array
    {
        $reflection = new \ReflectionClass($contractClass);

        $methods = [];

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {

            if ($method->getDeclaringClass()->getName() === $contractClass) {
                $methods[] = $method;
            }
        }

        return $methods;
    }
}