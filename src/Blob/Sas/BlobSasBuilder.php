<?php

declare(strict_types=1);

namespace AzureOss\Storage\Blob\Sas;

use AzureOss\Storage\Blob\Exceptions\UnableToGenerateSasException;
use AzureOss\Storage\Common\ApiVersion;
use AzureOss\Storage\Common\Auth\StorageSharedKeyCredential;
use AzureOss\Storage\Common\Sas\SasIpRange;
use AzureOss\Storage\Common\Sas\SasProtocol;
use GuzzleHttp\Psr7\Query;

final class BlobSasBuilder
{
    private string $version;
    private string $containerName;
    private \DateTime|\DateTimeImmutable $expiresOn;
    private ?string $blobName = null;
    private \DateTime|\DateTimeImmutable|null $startsOn = null;
    private ?string $permissions = null;
    private ?string $identifier = null;
    private ?string $cacheControl = null;
    private ?string $contentDisposition = null;
    private ?string $contentEncoding = null;
    private ?string $contentLanguage = null;
    private ?string $contentType = null;
    private ?string $encryptionScope = null;
    private ?SasIpRange $ipRange = null;
    private ?\DateTimeInterface $snapshotTime = null;
    private ?SasProtocol $protocol = null;

    public static function new(): self
    {
        return new self();
    }

    public function setContainerName(string $value): BlobSasBuilder
    {
        $this->containerName = $value;

        return $this;
    }

    public function setBlobName(string $value): BlobSasBuilder
    {
        $this->blobName = $value;

        return $this;
    }

    public function setExpiresOn(\DateTime|\DateTimeImmutable $value): self
    {
        $this->expiresOn = $value;

        return $this;
    }

    public function setPermissions(string $value): self
    {
        $this->permissions = $value;

        return $this;
    }

    public function setIdentifier(string $value): self
    {
        $this->identifier = $value;

        return $this;
    }

    public function setStartsOn(\DateTime $value): self
    {
        $this->startsOn = $value;

        return $this;
    }

    public function setCacheControl(string $value): self
    {
        $this->cacheControl = $value;

        return $this;
    }

    public function setContentDisposition(string $value): self
    {
        $this->contentDisposition = $value;

        return $this;
    }

    public function setContentEncoding(string $value): self
    {
        $this->contentEncoding = $value;

        return $this;
    }

    public function setContentLanguage(string $value): self
    {
        $this->contentLanguage = $value;

        return $this;
    }

    public function setContentType(string $value): self
    {
        $this->contentType = $value;

        return $this;
    }

    public function setEncryptionScope(string $value): self
    {
        $this->encryptionScope = $value;

        return $this;
    }

    public function setIPRange(SasIpRange $value): self
    {
        $this->ipRange = $value;

        return $this;
    }

    public function setSnapshotTime(\DateTimeInterface $value): self
    {
        $this->snapshotTime = $value;

        return $this;
    }

    public function setProtocol(SasProtocol $value): self
    {
        $this->protocol = $value;

        return $this;
    }

    public function setVersion(string $value): self
    {
        $this->version = $value;

        return $this;
    }

    public function build(StorageSharedKeyCredential $sharedKeyCredential): string
    {
        if ($this->identifier === null && $this->permissions === null) {
            throw new UnableToGenerateSasException();
        }

        $signedStart = $this->startsOn !== null ? $this->dateTo8601Zulu($this->startsOn) : null;
        $signedExpiry = $this->dateTo8601Zulu($this->expiresOn);
        $signedResource = $this->blobName ? "b" : "c";
        $signedIp = $this->ipRange !== null ? (string) $this->ipRange : null;
        $signedProtocol = $this->protocol?->value;
        $signedVersion = $this->version ?? ApiVersion::LATEST->value;
        $signedSnapshotTime = $this->snapshotTime ? (string) $this->snapshotTime->getTimestamp() : null;
        $canonicalizedResource = $this->getCanonicalizedResource($sharedKeyCredential->accountName);

        $stringToSign = [
            $this->permissions,
            $signedStart,
            $signedExpiry,
            $canonicalizedResource,
            $this->identifier,
            $signedIp,
            $signedProtocol,
            $signedVersion,
            $signedResource,
            $signedSnapshotTime,
            $this->encryptionScope,
            $this->cacheControl,
            $this->contentDisposition,
            $this->contentEncoding,
            $this->contentLanguage,
            $this->contentType,
        ];

        $stringToSign = array_map(fn($str) => urldecode($str ?? ""), $stringToSign);
        $stringToSign = implode("\n", $stringToSign);

        $signature = urlencode($sharedKeyCredential->computeHMACSHA256($stringToSign));

        return Query::build(array_filter([
            "st" => $signedStart,
            "se" => $signedExpiry,
            "sv" => $signedVersion,
            "sr" => $signedResource,
            "sip" => $signedIp,
            "sig" => $signature,
            "spr" => $signedProtocol,
            "sst" => $signedSnapshotTime,
            "sp" => $this->permissions,
            "si" => $this->identifier,
            "rscc" => $this->cacheControl,
            "rscd" => $this->contentDisposition,
            "rsce" => $this->contentEncoding,
            "rscl" => $this->contentLanguage,
            "rsct" => $this->contentType,
            "ses" => $this->encryptionScope,
        ]), false);
    }

    private function getCanonicalizedResource(string $accountName): string
    {
        $resource = "/blob/$accountName/$this->containerName";

        if ($this->blobName !== null) {
            $resource .= "/$this->blobName";
        }

        return $resource;
    }

    private function dateTo8601Zulu(\DateTime|\DateTimeImmutable $date): string
    {
        return (clone $date)
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s\Z');
    }
}