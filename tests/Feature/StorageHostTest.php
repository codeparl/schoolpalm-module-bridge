<?php

declare(strict_types=1);

use Illuminate\Contracts\Filesystem\Factory as StorageFactory;
use SchoolPalm\ModuleBridge\Contracts\Host\ContextHost;
use SchoolPalm\ModuleBridge\Facades\Host\StorageHost;

beforeEach(function () {

    /*
    |--------------------------------------------------------------------------
    | Clean storage
    |--------------------------------------------------------------------------
    */

    app(StorageFactory::class)
        ->disk('local')
        ->deleteDirectory('tenants');
});



it('stores files using storage host api', function () {

    $path = StorageHost::put(
        'reports/test.txt',
        'Hello World'
    );


    expect($path)
        ->toContain(
            'tenants/tenant_demo_001'
        );


    expect($path)
        ->toContain(
            'schools/1'
        );


    expect(
        StorageHost::exists(
            'reports/test.txt'
        )
    )
        ->toBeTrue();


    expect(
        StorageHost::get(
            'reports/test.txt'
        )
    )
        ->toBe('Hello World');
});



it('automatically scopes storage using current context', function () {


    /*
    |--------------------------------------------------------------------------
    | Fake runtime context
    |--------------------------------------------------------------------------
    */

    $this->app->instance(
        ContextHost::class,
        new class implements ContextHost {

            public function tenant(): ?object
            {
                return (object) [
                    'id' => 'tenant-abc',
                ];
            }


            public function school(): ?object
            {
                return (object) [
                    'id' => 'school-xyz',
                ];
            }


            public function user(): ?object
            {
                return null;
            }
        }
    );



    $path = StorageHost::put(
        'documents/report.pdf',
        'PDF DATA'
    );



    expect($path)
        ->toBe(
            'tenants/tenant-abc/schools/school-xyz/documents/report.pdf'
        );



    expect(
        StorageHost::exists(
            'documents/report.pdf'
        )
    )
        ->toBeTrue();
});
