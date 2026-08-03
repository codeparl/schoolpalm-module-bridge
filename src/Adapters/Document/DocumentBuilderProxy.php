<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Adapters\Document;

/**
 * @method self fromArray(array $data)
 * @method self fromCollection(\Illuminate\Support\Collection $collection)
 * @method self fromQuery(string $model)
 * @method self fromModel(\Illuminate\Database\Eloquent\Model $model)
 *
 * @method self view(string $view, array $data = [])
 * @method self filename(string $filename)
 * @method self saveTo(string $path)
 *
 * @method self context(array $context)
 * @method self tenant(mixed $tenant)
 * @method self school(mixed $school)
 * @method self user(mixed $user)
 *
 * @method self chunk(int $size)
 * @method self merge(bool $merge = true)
 *
 * @method self queue()
 * @method self sync()
 *
 * @method self engine(string $engine)
 * @method self option(string $key, mixed $value)
 * @method self options(array $options)
 *
 * @method self driverConfig(array $config)
 * @method self setDriverOption(string $driver, string $key, mixed $value)
 * @method self setDriverOptions(string $driver, array $options)
 *
 * @method \UnnovateBrains\DocumentBuilder\Support\DocumentResult save()
 * @method \Symfony\Component\HttpFoundation\Response download()
 * @method \Symfony\Component\HttpFoundation\Response stream()
 * @method mixed dispatch()
 */
final class DocumentBuilderProxy {}
