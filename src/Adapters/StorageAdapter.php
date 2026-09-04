<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Adapters;

use SchoolPalm\ModuleBridge\Contracts\Host\ContextHost;
use SchoolPalm\ModuleBridge\Services\ContextResolver;
use UnnovateBrains\DocumentBuilder\Contracts\DocumentContent;
use UnnovateBrains\DocumentBuilder\Contracts\DocumentStorage;
use UnnovateBrains\DocumentBuilder\Contracts\StorageWorkspaceInterface;

final class StorageAdapter
{
    public function __construct(
        private readonly DocumentStorage $storage,
        private readonly ContextResolver $resolver
    ) {}


    protected function storage(): DocumentStorage
    {



        return $this->storage->forContext(
            $this->resolver->tenantId(),
            $this->resolver->schoolCode()
        );
    }


    public function batchWorkspace(
        string $batchUuid
    ): StorageWorkspaceInterface {

        return $this->storage()
            ->batchWorkspace($batchUuid);
    }


    public function put(
        string $path,
        mixed $contents
    ): string {
        return $this->storage()
            ->put($path, $contents);
    }


    public function putContent(
        string $path,
        DocumentContent $content
    ): string {

        return $this->storage()
            ->putContent($path, $content);
    }


    public function get(
        string $path
    ): string {

        return $this->storage()
            ->get($path);
    }


    public function readStream(
        string $path
    ) {

        return $this->storage()
            ->readStream($path);
    }


    public function exists(
        string $path
    ): bool {

        return $this->storage()
            ->exists($path);
    }


    public function size(
        string $path
    ): ?int {

        return $this->storage()
            ->size($path);
    }


    public function delete(
        string $path
    ): bool {

        return $this->storage()
            ->delete($path);
    }


    public function deleteDirectory(
        string $path
    ): bool {

        return $this->storage()
            ->deleteDirectory($path);
    }


    public function copy(
        string $from,
        string $to
    ): bool {

        return $this->storage()
            ->copy($from, $to);
    }


    public function move(
        string $from,
        string $to
    ): bool {

        return $this->storage()
            ->move($from, $to);
    }


    public function publicUrl(
        string $path
    ): ?string {

        return $this->storage()
            ->publicUrl($path);
    }


    public function resolvePath(
        string $path
    ): string {

        return $this->storage()
            ->resolvePath($path);
    }


    public function physicalPath(
        string $path
    ): string {

        return $this->storage()
            ->physicalPath($path);
    }
}
