<?php

namespace App\Entities;

/**
 *
 */
class Address
{
    public $data;
    protected $region;

    /**
     * @return void
     */
    public function __construct(array $attribs, string $region)
    {
        $this->data = $attribs;
        $this->region = $region;
    }

    public function getRegion(): string
    {
        return $this->region;
    }

    public function getAllocationId(): ?string
    {
        return $this->data['AllocationId'] ?? null;
    }

    public function getPublicIp(): string
    {
        return $this->data['PublicIp'];
    }

    /**
     * @return string
     */
    public function getDomain(): string
    {
        return $this->data['Domain'];
    }

    /**
     * @param string $tagKey
     * @param string|null $defaultValue
     * @return null|string
     */
    public function getTag(string $tagKey, string $defaultValue = null): ?string
    {
        if (!isset($this->data['Tags'])) {
            return $defaultValue;
        }

        foreach ($this->data['Tags'] as $tag) {
            $key = $tag['Key'];
            $value = $tag['Value'];
            if (strtolower($key) == strtolower($tagKey)) {
                return $value;
            }
        }

        return $defaultValue;
    }

    /**
     * @return null|string
     */
    public function getInstanceId(): ?string
    {
        return $this->data['InstanceId'] ?? null;
    }

    public function getCustomTags()
    {
        return [
            'BU'            => $this->getTag('bu'),
            'Name'          => $this->getTag('name'),
            'Project'       => $this->getTag('project'),
            'Environment'   => $this->getTag('environment'),
            'AWS Type'      => $this->getTag('aws type'),
            'Instance Name' => $this->getTag('instance name')
        ];
    }

    /**
     * @return array
     */
    public function dump(): array
    {
        return [
            'region'         => $this->region,
            'public-ip'      => $this->getPublicIp(),
            'domain'         => $this->getDomain(),
            'instance-id'    => $this->getInstanceId(),
            'association-id' => $this->getAllocationId(),
            'tag'            => $this->getCustomTags(),
        ];
    }
}
