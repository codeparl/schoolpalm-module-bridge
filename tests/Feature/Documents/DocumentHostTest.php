<?php

use SchoolPalm\ModuleBridge\Facades\Host\DocumentHost;
use SchoolPalm\ModuleBridge\Facades\Host\StorageHost;
use UnnovateBrains\DocumentBuilder\Support\DocumentResult;

it('generates a document using resolved school context', function () {


    $result =
        DocumentHost::pdf()
        ->fromArray([
            [
                'name' => 'Student One'
            ]
        ])
        ->view('students')
        ->saveTo('documents/reports/students.pdf')
        ->filename('students.pdf')
        ->save();



    expect($result)
        ->not()
        ->toBeNull();



    expect(
        $result->getFilename()
    )
        ->toBe('students.pdf');



    /*
    |--------------------------------------------------------------------------
    | Verify tenant isolation
    |--------------------------------------------------------------------------
    */
    expect(
        StorageHost::exists(
            'documents/reports/students.pdf'
        )
    )
        ->toBeTrue();
});

it('generates chunked document and merges final output using document host', function () {


    /*
    |--------------------------------------------------------------------------
    | Create many records to force chunking
    |--------------------------------------------------------------------------
    */

    $students = [];


    for ($i = 1; $i <= 600; $i++) {

        $students[] = [
            'id' => $i,
            'name' => "Student {$i}",
        ];
    }



    /*
    |--------------------------------------------------------------------------
    | Execute chunked generation
    |--------------------------------------------------------------------------
    */

    $result =
        DocumentHost::pdf()
        ->fromArray($students)
        ->view('students')
        ->chunk(100)
        ->merge(true)
        ->sync()
        ->filename('students-full.pdf')
        ->saveTo(
            'documents/reports/students-full.pdf'
        )
        ->save();



    /*
    |--------------------------------------------------------------------------
    | Verify result
    |--------------------------------------------------------------------------
    */

    expect($result)
        ->toBeInstanceOf(
            DocumentResult::class
        );



    expect(
        $result->getFilename()
    )
        ->toBe(
            'students-full.pdf'
        );



    expect(
        $result->isComplete()
    )
        ->toBeTrue();



    /*
    |--------------------------------------------------------------------------
    | Verify final storage
    |--------------------------------------------------------------------------
    */

    expect(
        StorageHost::exists(
            'documents/reports/students-full.pdf'
        )
    )
        ->toBeTrue();



    /*
    |--------------------------------------------------------------------------
    | Verify file is not empty
    |--------------------------------------------------------------------------
    */

    expect(
        StorageHost::get(
            'documents/reports/students-full.pdf'
        )
    )
        ->not()
        ->toBeEmpty();
});

it('restores context when document generation runs in queue', function () {


    $result =
        DocumentHost::pdf()
        ->fromArray([
            [
                'name' => 'Queued Student'
            ]
        ])
        ->view('students')
        ->queue()
        ->dispatch();



    expect($result)
        ->not()
        ->toBeNull();
});
