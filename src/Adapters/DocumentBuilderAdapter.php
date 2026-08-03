<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Adapters;

use BadMethodCallException;
use UnnovateBrains\DocumentBuilder\DocumentBuilder;
use UnnovateBrains\DocumentBuilder\Support\DocumentResult;

/**
 * Adapter around DocumentBuilder.
 *
 * Automatically resolves SchoolPalm runtime context and prevents
 * modules from manually overriding protected context values.
 *
 * @method self engine(string $engine)
 * @method self option(string $key, mixed $value)
 * @method self options(array $options)
 * @method self context(array $context)
 * @method self withContext(string $key, mixed $value)
 * @method self view(string $view, array $data = array (
 * @method self templateEngine(string $engine)
 * @method self fromArray(array $data)
 * @method self fromCollection(Illuminate\Support\Collection $collection)
 * @method self fromQuery(string $model)
 * @method self fromModel(Illuminate\Database\Eloquent\Model $model)
 * @method self fromJson(string $json)
 * @method self fromSource(UnnovateBrains\DocumentBuilder\Contracts\Source $source)
 * @method void registerSource(string $type, string $sourceClass)
 * @method self tenant(mixed $tenant)
 * @method self school(mixed $school)
 * @method self academicYear(mixed $year)
 * @method self term(mixed $term)
 * @method self user(mixed $user)
 * @method self locale(string $locale)
 * @method self timezone(string $timezone)
 * @method self metadata(mixed $metadata)
 * @method self filename(string $filename)
 * @method self chunk(int $size)
 * @method self merge(bool $merge = true)
 * @method self queue()
 * @method self sync()
 * @method self withoutQueue()
 * @method self transform(mixed $transformer)
 * @method UnnovateBrains\DocumentBuilder\Support\ExecutionPlan compilePlan()
 * @method self driverConfig(array $config)
 * @method array getDriverConfig()
 * @method self setDriverOption(string $driver, string $key, mixed $value)
 * @method self setDriverOptions(string $driver, array $options)
 * @method self saveTo(string $path)
 * @method mixed save()
 * @method Symfony\Component\HttpFoundation\Response download()
 * @method Symfony\Component\HttpFoundation\Response stream()
 * @method mixed dispatch()
 * @method self columns(array $columns)
 * @method self sheet(string $name)
 * @method self freezeRows(int $rows)
 * @method self freezeColumns(int $columns)
 * @method self autoSize(bool $enabled = true)
 * @method self headers(bool $enabled = true)
 * @method self protectSheet(string $password = NULL)
 * @method self protectWorkbook(string $password = NULL)
 * @method self gridLines(bool $enabled = true)
 * @method self excelConfig(array $config)
 * @method self pageSize(string $size)
 * @method self orientation(string $orientation)
 * @method self margins(array $margins)
 * @method self header(bool $enabled = true)
 * @method self footer(bool $enabled = true)
 * @method self headerContent(string $content)
 * @method self footerContent(string $content)
 * @method self addWatermark(string $text)
 * @method self pdfMetadata(array $metadata)
 * @method self pdfConfig(array $config)
 * @method self width(int $width)
 * @method self height(int $height)
 * @method self size(int $width, int $height)
 * @method self createImage(int $width, int $height)
 * @method self canvas(int $width, int $height)
 * @method self background(string $color)
 * @method self transparent(bool $enabled = true)
 * @method self fromImage(string $path)
 * @method self fromUrl(string $url)
 * @method self fromBase64(string $data)
 * @method self fromSvg(string $svg)
 * @method self fromText(string $text)
 * @method self from(string $text)
 * @method self resize(int $width, int $height)
 * @method self fit(int $width, int $height)
 * @method self contain(int $width, int $height)
 * @method self scale(float $factor)
 * @method self keepAspectRatio(bool $enabled = true)
 * @method self crop(int $width, int $height)
 * @method self cropPosition(int $x, int $y)
 * @method self rotate(float $degrees)
 * @method self flipHorizontal()
 * @method self flipVertical()
 * @method self quality(int $quality)
 * @method self optimize(bool $enabled = true)
 * @method self progressive(bool $enabled = true)
 * @method self stripMetadata(bool $enabled = true)
 * @method self convert(string $type)
 * @method self watermark(string $path)
 * @method self watermarkOpacity(int $opacity)
 * @method self watermarkPosition(string $position)
 * @method self watermarkOffset(int $x, int $y)
 * @method self text(string $text)
 * @method self textOptions(array $options)
 * @method self grayscale()
 * @method self sepia()
 * @method self blur(int $amount = 5)
 * @method self sharpen(int $amount = 10)
 * @method self brightness(int $level)
 * @method self contrast(int $level)
 * @method self imageConfig(array $config)
 * @method self font(string $font)
 * @method self fontSize(int $size)
 * @method self fontColor(string $color)
 * @method self qrCode(string $data)
 * @method self qrSize(int $size)
 * @method self qrMargin(int $margin)
 * @method self barcode(string $data)
 * @method self barcodeFormat(string $format)
 * @method self barcodeHeight(int $height)
 * @method self barcodeWidth(int $width)
 */
final class DocumentBuilderAdapter
{
    public function __construct(
        protected DocumentBuilder $builder
    ) {}

    public function save(): mixed
    {
        return $this->builder->save();
    }

    public function download()
    {
        return $this->builder->download();
    }

    public function stream()
    {
        return $this->builder->stream();
    }

    public function dispatch()
    {
        return $this->builder->dispatch();
    }


    public function builder(): DocumentBuilder
    {
        return $this->builder;
    }


    public function __call(
        string $method,
        array $arguments
    ) {

        if (in_array($method, [
            'tenant',
            'school',
            'user',
            'academicYear',
            'term',
            'locale',
            'timezone',
            'context',
            'withContext',
        ], true)) {

            throw new BadMethodCallException(
                sprintf(
                    'The [%s] method is managed automatically by SchoolPalm DocumentHost.',
                    $method
                )
            );
        }


        if (! method_exists($this->builder, $method)) {

            throw new BadMethodCallException(
                sprintf(
                    'Method [%s] does not exist on DocumentBuilder.',
                    $method
                )
            );
        }


        $result =
            $this->builder->{$method}(...$arguments);


        if ($result instanceof DocumentBuilder) {

            $this->builder = $result;

            return $this;
        }


        return $result;
    }
}
