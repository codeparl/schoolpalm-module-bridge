<?php

use Illuminate\Support\Facades\Queue;
use SchoolPalm\ModuleBridge\Facades\Host\DocumentHost;
use SchoolPalm\ModuleBridge\Facades\Host\StorageHost;
use Symfony\Component\HttpFoundation\Response;
use UnnovateBrains\DocumentBuilder\Support\DocumentResult;

it('generates a document using resolved school context', function () {
    $result = DocumentHost::pdf()
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

    expect(
        StorageHost::exists(
            'documents/reports/students.pdf'
        )
    )
        ->toBeTrue();
});

it('downloads a generated document response directly', function () {
    $response = DocumentHost::pdf()
        ->fromArray([
            [
                'name' => 'Downloadable Student'
            ]
        ])
        ->view('students')
        ->filename('student-download.pdf')
        ->download();

    expect($response)
        ->toBeInstanceOf(Response::class);

    expect($response->headers->get('content-type'))
        ->toContain('application/pdf');

    expect($response->headers->get('content-disposition'))
        ->toContain('attachment')
        ->toContain('student-download.pdf');
});

it('generates chunked document and merges final output using document host', function () {
    $students = [];

    for ($i = 1; $i <= 600; $i++) {
        $students[] = [
            'id' => $i,
            'name' => "Student {$i}",
        ];
    }

    $result = DocumentHost::pdf()
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

    expect(
        StorageHost::exists(
            'documents/reports/students-full.pdf'
        )
    )
        ->toBeTrue();

    expect(
        StorageHost::get(
            'documents/reports/students-full.pdf'
        )
    )
        ->not()
        ->toBeEmpty();
});

it('restores context when document generation runs in queue', function () {
    Queue::fake();

    $result = DocumentHost::pdf()
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

it('resolves module view prefix automatically using context module_key', function () {
    $result = DocumentHost::withContext(['module_key' => 'UnnovateBrains\\Common\\Staff'])
        ->pdf()
        ->fromArray([
            ['name' => 'Module Student']
        ])
        ->view('students')
        ->saveTo('documents/reports/module-students.pdf')
        ->filename('module-students.pdf')
        ->save();

    expect($result)
        ->not()
        ->toBeNull();

    expect(
        StorageHost::exists(
            'documents/reports/module-students.pdf'
        )
    )
        ->toBeTrue();
});
