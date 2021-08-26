<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Batches\FileHandlers\Strategy\Handlers;


use App\Domain\Models\AdminUser;
use App\Domain\Models\Batch;
use App\Exceptions\BatchHandlerExceptions\BatchHandlerException;
use App\Infrastructure\Services\Interfaces\BatchHandler\BatchHandlerInterface;
use ArrayObject;
use League\Csv\Exception as LeagueException;
use League\Csv\Reader;
use Throwable;

/**
 * Class BaseBatchHandler
 * @package App\Infrastructure\Services\Batches\FileHandlers\Strategy\Handlers
 */
abstract class BaseBatchHandler implements BatchHandlerInterface
{

    /**
     * @var Throwable|null
     */
    protected ?Throwable $error;

    /**
     * @var Reader
     */
    protected Reader $stream;

    /**
     * @var int
     */
    protected int $headersCount = 1;

    /**
     * @var array
     */
    protected array $orderHeader;

    /**
     * @var int
     */
    protected int $rowNumber;

    /**
     * @var int|null
     */
    protected ?int $currentOrderRowNum = null;

    /**
     * @var array
     */
    protected array $currentRow = [];

    /**
     * @var ArrayObject
     */
    protected ArrayObject $currentOrder;

    /**
     * @var bool
     */
    protected bool $hasHeaderIndicator = false;

    /**
     * @var AdminUser
     */
    protected AdminUser $shipper;
    /**
     * @var Batch
     */
    protected Batch $batch;

    /**
     * @var string|null
     */
    protected ?string $originLine = null;

    /**
     * @var array|null
     */
    protected ?array $originCurrentOrder = null;

    /**
     * @var array
     */
    protected array $dump = [];

    /**
     * @var array
     */
    protected array $adaptedOrderHeaderFields;

    /**
     * BaseBatchHandler constructor.
     * @param Reader $stream
     * @param Batch $batch
     * @throws LeagueException
     */
    public function __construct(Reader $stream, Batch $batch)
    {
        $this->stream = $stream;
        $this->batch = $batch;
        $this->orderHeader = $stream->fetchOne(0);
        $this->adaptedOrderHeaderFields = $this->buildAdaptedHeader($this->orderHeader, static::getOrderHeaderFields());
        $this->rowNumber = $this->headersCount;
    }

    /**
     * @param array $batchHeader
     * @param array $headerPattern
     * @return array
     */
    protected function buildAdaptedHeader(array $batchHeader, array $headerPattern): array
    {
        $explodedPattern = self::explodeHeaderPattern($headerPattern);
        $validHeader = self::validHeader($batchHeader);
        $result = [];
        array_walk($explodedPattern, function (&$item, $key) use ($validHeader, &$result) {
            $intersect = array_intersect($item, $validHeader);
            if (($found = array_shift($intersect)) !== null) {
                $result[$key] = $found;
            }
        });

        return $result;
    }

    /**
     * @return array|null
     */
    public function getNextRow(): ?array
    {
        $this->incRow();
        return $this->getCurrentRow();
    }

    protected function incRow(): void
    {
        $this->rowNumber++;
    }

    /**
     * @return array|null
     */
    public function getCurrentRow(): ?array
    {
        try {
            $this->currentRow = $this->stream->fetchOne($this->rowNumber);
            $this->setOriginLine();
            return $this->currentRow;
        } catch (LeagueException $exception) {
            $this->error = $exception;
            return null;
        }
    }

    /**
     * @return Throwable|null
     */
    public function getError(): ?Throwable
    {
        return $this->error;
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return $this->stream->count();
    }


    /**
     * @return bool
     */
    public function eof(): bool
    {
        return $this->count() < ($this->rowNumber + 1);
    }

    /**
     * @return int|null
     */
    public function getCurrentOrderRowNum(): ?int
    {
        return $this->currentOrderRowNum;
    }

    protected function setOriginLine(): void
    {
        if (!$this->eof()) {
            $this->originLine = rtrim(implode($this->stream->getDelimiter(), $this->currentRow), ',');
        }
    }

    /**
     * @return string
     */
    public function getOriginLine(): string
    {
        return $this->originLine;
    }

    /**
     * @return array|null
     */
    public function getOriginCurrentOrder(): ?array
    {
        return $this->originCurrentOrder;
    }

    /**
     * @param array|null $originCurrentOrder
     */
    public function setOriginCurrentOrder(?array $originCurrentOrder): void
    {
        $this->originCurrentOrder = $originCurrentOrder;
    }

    /**
     * @param string $field
     * @return string
     */
    protected static function validField(string $field): string
    {
        return str_replace([' ', '-'], '', strtolower($field));
    }

    /**
     * @param array $data
     * @param array $byHeader
     * @param array $headerPattern
     * @return array
     */
    protected function prepareData(array $data, array $byHeader, array $headerPattern): array
    {
        return $this->withValidFieldsKeys($this->setDataFieldNames($data, $byHeader), $headerPattern);
    }


    /**
     * @param array $data
     * @param array $byHeader
     * @return array
     */
    protected function setDataFieldNames(array $data, array $byHeader): array
    {
        return array_slice(array_map(function ($value) use ($data) {
            return $data[$value];
        }, array_flip($byHeader)), (int)$this->hasHeaderIndicator);
    }

    /**
     * @param array $data
     * @param array $headerPattern
     * @return array
     */
    protected function withValidFieldsKeys(array $data, array $headerPattern): array
    {
        $return = [];
        foreach ($data as $key => $value) {
            $return[$this->getValidFieldKey($key, $headerPattern)] = $value;
        }

        return $return;
    }

    /**
     * @param string $field
     * @param array $headerPattern
     * @return false|int|string
     */
    public function getValidFieldKey(string $field, array $headerPattern)
    {
        return array_search(self::validField($field), self::validHeader($headerPattern));
    }

    /**
     * @param array $header
     * @return array
     */
    public static function validHeader(array $header): array
    {
        return array_map(['self', 'validField'], $header);
    }

    /**
     * @param array $header
     * @param array $byHeaderPattern
     * @return bool
     */
    protected function validateHeader(array $header, array $byHeaderPattern): bool
    {
        $result = [];
        foreach ($header as $field) {
            if (!in_array(self::validField($field), self::validHeader($byHeaderPattern))) {
                $result[] = $field;
            }
        }
        if (!empty($result)) {
            $this->error = new BatchHandlerException(self::class . " => Invalid Names In Header: " . json_encode($result));
            return false;
        }
        return true;
    }

    /**
     * @param bool $asArray
     * @return ArrayObject|array|null
     */
    public function getParcel(bool $asArray = true)
    {
        if (!$this->currentOrder->parcel) {
            return null;
        }

        return $asArray ? $this->currentOrder->parcel->getArrayCopy() : $this->currentOrder->parcel;
    }

    /**
     * @param bool $asArray
     * @return ArrayObject|array|null
     */
    public function getParcelItems(bool $asArray = true)
    {
        if (!$this->currentOrder->parcelItems) {
            return null;
        }

        return $asArray ? $this->currentOrder->parcelItems->getArrayCopy() : $this->currentOrder->parcelItems;
    }

    protected function setDump(): void
    {
        if (!$this->eof()) {
            $this->dump["Line #{$this->rowNumber}"] = $this->getOriginLine();
        }
    }

    /**
     * @return array
     */
    public function getDump(): array
    {
        return $this->dump;
    }

    /**
     * @param Reader $reader
     * @return bool
     * @throws LeagueException
     */
    public static function isMyHeader(Reader $reader): bool
    {
        $header = $reader->fetchOne(0);
        $myHeaderFields = array_merge(...array_values(self::explodeHeaderPattern(static::getOrderHeaderFields())));
        foreach ($header as $field) {
            if (!in_array(self::validField($field), $myHeaderFields)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array $headerPattern
     * @return array
     */
    protected static function explodeHeaderPattern(array $headerPattern): array
    {
        return array_map(function ($headerItem) {
            return array_map(['self', 'validField'], (is_array($headerItem) ? $headerItem : explode('|', $headerItem)));
        }, $headerPattern);
    }

}
